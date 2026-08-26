#!/usr/bin/env python3
"""Reliable, non-blocking ZKTeco ADMS listener.

Devices are acknowledged only after their inbound message is committed to the
local SQLite outbox.  Laravel calls are made by a small, fixed worker pool, so
a slow or unavailable Laravel process never occupies an ADMS request thread.
"""

import argparse
import json
import logging
import os
import re
import sqlite3
import threading
import time
import urllib.error
import uuid
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse
from urllib.request import Request, urlopen

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
LOG_DIR = os.path.join(BASE_DIR, "logs")
os.makedirs(LOG_DIR, exist_ok=True)

LARAVEL_URL = os.environ.get("LARAVEL_URL", "http://127.0.0.1:8000").rstrip("/")
SAVE_RAW = os.environ.get("ADMS_SAVE_RAW", "0").strip().lower() in {"1", "true", "yes"}
if os.environ.get("ADMS_SAVE_RAW", "") == "":
    SAVE_RAW = True  # TEMP DEBUG
VERBOSE_LOG = os.environ.get("ADMS_VERBOSE_LOG", "0").strip().lower() in {"1", "true", "yes"}
OUTBOX_PATH = os.environ.get("ADMS_OUTBOX_PATH", os.path.join(LOG_DIR, "adms-outbox.sqlite3"))
FORWARD_WORKERS = max(1, int(os.environ.get("ADMS_FORWARD_WORKERS", "4")))
REQUEST_WORKERS = max(1, int(os.environ.get("ADMS_REQUEST_WORKERS", "32")))
HTTP_TIMEOUT = max(1, int(os.environ.get("ADMS_LARAVEL_TIMEOUT", "10")))
MAX_REQUEST_BYTES = max(1024, int(os.environ.get("ADMS_MAX_REQUEST_BYTES", str(8 * 1024 * 1024))))
COMMAND_REFRESH_SECONDS = max(1, int(os.environ.get("ADMS_COMMAND_REFRESH_SECONDS", "2")))
MARK_SENDING_COALESCE_SECONDS = max(1, int(os.environ.get("ADMS_MARK_SENDING_COALESCE_SECONDS", "5")))
COMMAND_CACHE_TTL_SECONDS = max(600, int(os.environ.get("ADMS_COMMAND_CACHE_TTL_SECONDS", "2400")))
FACE_SERIAL_COOLDOWN_SECONDS = max(2, int(os.environ.get("ADMS_FACE_SERIAL_COOLDOWN_SECONDS", "5")))
MAX_OUTBOX_ATTEMPTS = max(5, int(os.environ.get("ADMS_MAX_OUTBOX_ATTEMPTS", "30")))
USER_COMMAND_TYPES = {"user_create", "user_update", "user_delete"}
NO_ACK_COMMAND_TYPES = {"restart", "refresh_config"}

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s %(message)s",
    handlers=[logging.FileHandler(os.path.join(LOG_DIR, "adms.log"), encoding="utf-8"), logging.StreamHandler()],
)
logger = logging.getLogger("ADMS")
routine_logger = logging.getLogger("ADMS.routine")

TABLE_ATTLOG = "ATTLOG"
TABLE_BIODATA = "BIODATA"
TABLE_USERINFO = "USERINFO"
TABLE_OPERLOG = "OPERLOG"
TABLE_OPTIONS = "OPTIONS"
TABLE_PHOTO = "PHOTO"
TABLE_USERPIC = "USERPIC"
INBOUND_TABLES = {TABLE_ATTLOG, TABLE_BIODATA, TABLE_USERINFO, TABLE_OPERLOG, TABLE_OPTIONS, TABLE_PHOTO, TABLE_USERPIC}


def generate_correlation_id() -> str:
    return uuid.uuid4().hex[:16]


def extract_sn(path: str) -> str:
    return parse_qs(urlparse(path).query).get("SN", ["UNKNOWN"])[0]


def extract_table(path: str) -> str:
    return parse_qs(urlparse(path).query).get("table", [""])[0]


def extract_query_params(path: str) -> dict:
    return {key: value[0] if len(value) == 1 else value for key, value in parse_qs(urlparse(path).query).items()}


def save_raw(prefix: str, content: str) -> str:
    path = os.path.join(LOG_DIR, f"{prefix}_{datetime.now().strftime('%Y%m%d_%H%M%S_%f')}.txt")
    with open(path, "w", encoding="utf-8", errors="ignore") as stream:
        stream.write(content)
    return path


def classify_payload(body: str, table_param: str, _path: str) -> str:
    if table_param.upper() in INBOUND_TABLES:
        return table_param.upper()
    upper = body.upper().strip()
    if upper.startswith("BIODATA") or ("PIN=" in upper and "TMP=" in upper):
        return TABLE_BIODATA
    if upper.startswith("ATTLOG") or (upper.startswith("ATT") and "\t" in body):
        return TABLE_ATTLOG
    if "USERPIC" in upper and "CONTENT=" in upper:
        return TABLE_USERPIC
    if upper.startswith("USERINFO") or "PIN=" in upper:
        return TABLE_USERINFO
    if upper.startswith("OPERLOG") or upper.startswith("OPLOG"):
        return TABLE_OPERLOG
    return "UNKNOWN"


def _endpoint_for(table_type: str) -> str:
    return {
        TABLE_ATTLOG: "/api/attendance-integration/push/adms",
        TABLE_BIODATA: "/api/attendance-integration/push/biodata",
        TABLE_USERPIC: "/api/attendance-integration/push/userpic",
    }.get(table_type, "/api/attendance-integration/push/adms")


def laravel_request(method: str, endpoint: str, data: dict | None = None) -> dict | None:
    """Run only in an outbox worker; never from a device request handler."""
    result, _status = laravel_request_raw(method, endpoint, data)
    return result


def laravel_request_raw(method: str, endpoint: str, data: dict | None = None) -> tuple[dict | None, int]:
    """Like laravel_request but also returns the HTTP status (0 on transport error)."""
    try:
        body = json.dumps(data).encode("utf-8") if data is not None else None
        request = Request(f"{LARAVEL_URL}{endpoint}", data=body, method=method)
        if body is not None:
            request.add_header("Content-Type", "application/json")
        with urlopen(request, timeout=HTTP_TIMEOUT) as response:
            if response.status >= 400:
                raise RuntimeError(f"Laravel returned HTTP {response.status}")
            raw = response.read().decode("utf-8")
            return (json.loads(raw) if raw else {}), response.status
    except Exception as exc:
        status = 0
        if isinstance(exc, urllib.error.HTTPError):
            status = exc.code
        logger.warning("Laravel %s %s failed: %s", method, endpoint, str(exc)[:300])
        return None, status


class PersistentOutbox:
    """Crash-safe SQLite outbox with leases and exponential retry.

    Each operation is committed before the device response is sent.  A lease
    lets another worker recover a job if the process dies during delivery.
    """

    def __init__(self, path: str):
        self.path = path
        self.wake_event = threading.Event()
        self.stop_event = threading.Event()
        self._init_database()

    def _connect(self) -> sqlite3.Connection:
        connection = sqlite3.connect(self.path, timeout=5, isolation_level=None)
        connection.row_factory = sqlite3.Row
        connection.execute("PRAGMA busy_timeout = 5000")
        return connection

    def _init_database(self) -> None:
        with self._connect() as connection:
            connection.execute("PRAGMA journal_mode = WAL")
            connection.execute("PRAGMA synchronous = FULL")
            connection.execute("""CREATE TABLE IF NOT EXISTS outbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT NOT NULL,
                payload TEXT NOT NULL, state TEXT NOT NULL DEFAULT 'pending',
                attempts INTEGER NOT NULL DEFAULT 0, available_at REAL NOT NULL,
                lease_until REAL, last_error TEXT, created_at REAL NOT NULL
            )""")
            connection.execute("CREATE INDEX IF NOT EXISTS outbox_ready ON outbox(state, available_at)")
            connection.execute("""CREATE TABLE IF NOT EXISTS command_cache (
                serial TEXT PRIMARY KEY, commands TEXT NOT NULL, refreshed_at REAL NOT NULL,
                requested_at REAL NOT NULL DEFAULT 0
            )""")

    def enqueue(self, kind: str, payload: dict) -> None:
        now = time.time()
        with self._connect() as connection:
            connection.execute(
                "INSERT INTO outbox (kind, payload, available_at, created_at) VALUES (?, ?, ?, ?)",
                (kind, json.dumps(payload, separators=(",", ":")), now, now),
            )
        self.wake_event.set()

    def request_command_refresh(self, serial: str) -> None:
        """Coalesce rapid device polls into one durable Laravel command pull."""
        now = time.time()
        with self._connect() as connection:
            row = connection.execute("SELECT requested_at FROM command_cache WHERE serial = ?", (serial,)).fetchone()
            if row and now - row["requested_at"] < COMMAND_REFRESH_SECONDS:
                return
            connection.execute(
                "INSERT INTO command_cache(serial, commands, refreshed_at, requested_at) VALUES (?, '[]', 0, ?) "
                "ON CONFLICT(serial) DO UPDATE SET requested_at = excluded.requested_at",
                (serial, now),
            )
        self.enqueue("fetch_commands", {"serial": serial})

    def cached_commands(self, serial: str) -> list:
        with self._connect() as connection:
            row = connection.execute("SELECT commands FROM command_cache WHERE serial = ?", (serial,)).fetchone()
        if not row:
            return []
        try:
            return json.loads(row["commands"])
        except json.JSONDecodeError:
            return []

    def save_commands(self, serial: str, commands: list) -> None:
        now = time.time()
        # Replace cache entirely with fresh Laravel response. Old merge logic
        # kept phantom commands that were already deleted/completed in Laravel,
        # causing the ADMS to serve non-existent commands to devices.
        with self._connect() as connection:
            connection.execute(
                "INSERT INTO command_cache(serial, commands, refreshed_at, requested_at) VALUES (?, ?, ?, ?) "
                "ON CONFLICT(serial) DO UPDATE SET commands=excluded.commands, refreshed_at=excluded.refreshed_at",
                (serial, json.dumps(commands, separators=(",", ":")), now, now),
            )

    def remove_cached_command(self, serial: str, command_id: int) -> None:
        commands = [item for item in self.cached_commands(serial) if item.get("id") != command_id]
        with self._connect() as connection:
            connection.execute(
                "INSERT INTO command_cache(serial, commands, refreshed_at, requested_at) VALUES (?, ?, ?, ?) "
                "ON CONFLICT(serial) DO UPDATE SET commands=excluded.commands, refreshed_at=excluded.refreshed_at",
                (serial, json.dumps(commands, separators=(",", ":")), time.time(), time.time()),
            )

    def claim(self) -> sqlite3.Row | None:
        now = time.time()
        with self._connect() as connection:
            connection.execute("BEGIN IMMEDIATE")
            connection.execute("UPDATE outbox SET state='pending', lease_until=NULL WHERE state='processing' AND lease_until < ?", (now,))
            row = connection.execute(
                "SELECT * FROM outbox WHERE state='pending' AND available_at <= ? ORDER BY id LIMIT 1", (now,)
            ).fetchone()
            if row:
                connection.execute("UPDATE outbox SET state='processing', lease_until=? WHERE id=?", (now + HTTP_TIMEOUT + 30, row["id"]))
            connection.execute("COMMIT")
            return row

    def complete(self, job_id: int) -> None:
        with self._connect() as connection:
            connection.execute("DELETE FROM outbox WHERE id = ?", (job_id,))

    def retry(self, job_id: int, attempts: int, error: str) -> None:
        # Cap delay at five minutes while retaining the job forever for operators.
        delay = min(300, 2 ** min(attempts, 8))
        with self._connect() as connection:
            connection.execute(
                "UPDATE outbox SET state='pending', attempts=?, available_at=?, lease_until=NULL, last_error=? WHERE id=?",
                (attempts, time.time() + delay, error[:1000], job_id),
            )

    def run_worker(self, worker_number: int) -> None:
        while not self.stop_event.is_set():
            job = self.claim()
            if not job:
                self.wake_event.wait(1)
                self.wake_event.clear()
                continue
            try:
                payload = json.loads(job["payload"])
                if self._deliver(job["kind"], payload):
                    self.complete(job["id"])
                elif job["attempts"] + 1 >= MAX_OUTBOX_ATTEMPTS:
                    logger.error(
                        "Outbox job %s (%s) dropped after %d attempts: %s",
                        job["id"], job["kind"], job["attempts"] + 1, job["last_error"],
                    )
                    self.complete(job["id"])
                else:
                    self.retry(job["id"], job["attempts"] + 1, "Laravel did not acknowledge request")
            except Exception as exc:
                logger.exception("Outbox worker %d failed job %s", worker_number, job["id"])
                if job["attempts"] + 1 >= MAX_OUTBOX_ATTEMPTS:
                    self.complete(job["id"])
                else:
                    self.retry(job["id"], job["attempts"] + 1, str(exc))

    def _deliver(self, kind: str, payload: dict) -> bool:
        if kind == "forward":
            result = laravel_request("POST", _endpoint_for(payload["table"]), payload["data"])
            return result is not None
        if kind == "heartbeat":
            return laravel_request("POST", "/api/adms/heartbeat", payload) is not None
        if kind == "command_result":
            result, status = laravel_request_raw("POST", "/api/adms/commands/result", payload)
            # Laravel ACKs unknown/stale results with HTTP 404 semantics in the
            # body; anything >= 400 that is not a permanent rejection is retried.
            if result is not None:
                return True
            return status in (404, 410)
        if kind == "mark_sending":
            result, status = laravel_request_raw("POST", "/api/adms/commands/sending", payload)
            if result is not None:
                return True
            return status in (404, 410)
        if kind == "fetch_commands":
            serial = payload["serial"]
            result = laravel_request("GET", f"/api/adms/commands?SN={serial}")
            if result is None:
                return False
            if result.get("success"):
                self.save_commands(serial, result.get("commands", []))
            return True
        raise ValueError(f"Unknown outbox job kind: {kind}")

    def start(self) -> list[threading.Thread]:
        threads = []
        for number in range(1, FORWARD_WORKERS + 1):
            thread = threading.Thread(target=self.run_worker, args=(number,), name=f"adms-outbox-{number}", daemon=True)
            thread.start()
            threads.append(thread)
        return threads

    def stop(self) -> None:
        self.stop_event.set()
        self.wake_event.set()


OUTBOX: PersistentOutbox | None = None


def build_get_option_response(sn: str) -> str:
    """Terminal registration options.

    Mirrors the option set announced by proven multi-bio ADMS servers
    (thai_zkt / ZKBio-style): ServerVer + PushProtVer 3.x plus
    MultiBioDataSupport tells hybrid firmware (iFace 880 Plus, Push
    2.0.33S) that this server accepts ``DATA UPDATE biodata`` records.
    Without it the terminal runs strict legacy mode and rejects every
    biodata write with Return=-30, making face distribution impossible.
    """
    return "\r\n".join([
        f"GET OPTION FROM: {sn}",
        "ATTLOGStamp=None",
        "OPERLOGStamp=None",
        "ATTPHOTOStamp=None",
        "ErrorDelay=60",
        "Delay=10",
        "TransTimes=00:00;23:59",
        "TransInterval=1",
        "TransFlag=1111111111",
        "TimeZone=3",
        "Realtime=1",
        "Encrypt=0",
        "ServerVer=3.1.2",
        "PushProtVer=3.1.2",
        "SupportPing=1",
        "PushOptionsFlag=1",
        "MaxPostSize=1048576",
        "MultiBioDataSupport=0:1:1:0:0:0:0:0:1:0",
        "MultiBioPhotoSupport=0:0:0:0:0:0:0:0:0:0",
        "BioDataFun=1",
        "BioPhotoFun=1",
        "Stamp=0",
        "OpStamp=0",
        "PhotoStamp=0",
        "",
    ])


def _format_device_command(command: dict) -> str:
    """Format one ADMS command using the tracked ``C:{id}:`` envelope.

    Every command type is wrapped as ``C:{id}:{body}`` so the terminal
    acknowledges it via ``POST /iclock/devicecmd`` with ``ID={id}``.
    The legacy bare ``CMD {id} {body}`` form is NOT part of the push
    protocol: terminals silently ignore those lines, the command never
    gets acknowledged, and user records never reach the device (which
    then rejects every face/fingerprint template with Return=-3).

    Legacy bodies that already start with a nested op-code (e.g.
    ``C:11#PIN`` deletes or ``C:18#...`` time syncs) keep working because
    terminals accept one level of nesting inside the envelope.
    """
    command_id = command.get("id", 0)
    body = str(command.get("command_body", "")).replace("\r", "").replace("\n", "").strip()

    return f"C:{command_id}:{body}"


def build_get_request_response(commands: list) -> str:
    if not commands:
        return "OK"
    return "\r\n".join(_format_device_command(command) for command in commands) + "\r\n"


def parse_command_result(body: str) -> dict | None:
    values = parse_qs(body.strip(), keep_blank_values=True)
    try:
        command_id = int((values.get("ID") or values.get("id") or [""])[0])
    except (TypeError, ValueError):
        return None
    returned = str((values.get("Return") or values.get("return") or [""])[0])
    success = returned.strip().lower() in {"0", "ok", "success"}
    return {"command_id": command_id, "status": "completed" if success else "failed", "return_code": returned,
            "error_message": None if success else f"Device returned {returned or 'unknown'}"}


# ---------------------------------------------------------------------------
# Command delivery tracking and face pacing
# ---------------------------------------------------------------------------

class CommandDeliveryTracker:
    """In-memory, per-serial state about what has actually been served."""

    def __init__(self):
        self._lock = threading.Lock()
        self._first_served_at = {}
        self._marked = {}
        self._last_mark_enqueue = {}

    def note_served(self, serial: str, command_id: int, now: float | None = None) -> float:
        now = time.time() if now is None else now
        with self._lock:
            first = self._first_served_at.setdefault((serial, command_id), now)
            return first

    def prune(self, serial: str, commands: list, now: float | None = None) -> list:
        if not commands:
            return commands
        now = time.time() if now is None else now
        with self._lock:
            kept = []
            marked = self._marked.get(serial)
            for command in commands:
                cid = command.get("id")
                first = self._first_served_at.get((serial, cid)) if cid is not None else None
                if first is not None and now - first >= COMMAND_CACHE_TTL_SECONDS:
                    self._first_served_at.pop((serial, cid), None)
                    if marked:
                        marked.discard(cid)
                    continue
                kept.append(command)
        return kept

    def ids_to_mark(self, serial: str, served_ids: list) -> list:
        now = time.time()
        with self._lock:
            marked = self._marked.setdefault(serial, set())
            unmarked = [cid for cid in served_ids if cid not in marked]
            if not unmarked:
                return []
            # Mark immediately: gating marks behind a time window left the
            # first delivery of every batch unmarked, so Laravel kept the
            # command 'pending' and the same command was re-served on the
            # next poll (duplicate writes, wasted device buffer).
            self._last_mark_enqueue[serial] = now
            marked.update(unmarked)
            return unmarked

    def clear(self, serial: str, command_id: int) -> None:
        with self._lock:
            self._first_served_at.pop((serial, command_id), None)
            marked = self._marked.get(serial)
            if marked:
                marked.discard(command_id)


OUTBOX: PersistentOutbox | None = None
DELIVERY = CommandDeliveryTracker()


def _command_sort_key(command: dict):
    return (int(command.get("priority", 99)), int(command.get("id", 0)))


def _extract_user_pin(body: str) -> str | None:
    match = re.search(r"C:1[01]#([^#\r\n]+)", body)
    if match:
        return match.group(1).strip()
    match = re.search(r"(?:^|\s)PIN=([^\s\r\n]+)", body, re.IGNORECASE)
    return match.group(1).strip() if match else None


def _extract_face_pin(body: str) -> str | None:
    match = re.search(r"(?:^|\s)Pin=([^\s\r\n]+)", body, re.IGNORECASE)
    return match.group(1).strip() if match else None


def resolve_command_id(serial: str, device_command_id: int) -> int | None:
    """Resolve the correct Laravel command_id from a device acknowledgement.

    Returns None when the acknowledgement cannot be attributed to any
    command we recently served for this serial.  Guessing (oldest served,
    single face command, ...) misattributed results across unrelated
    commands, so unknown ids are dropped instead: Laravel ignores them
    gracefully and the outbox never retries them.
    """
    assert OUTBOX is not None
    cached = OUTBOX.cached_commands(serial)
    for cmd in cached:
        if cmd.get("id") == device_command_id:
            return device_command_id

    with DELIVERY._lock:
        if DELIVERY._first_served_at.get((serial, device_command_id)) is not None:
            return device_command_id

    logger.info(
        "Dropping unresolvable DEVICECMD ack ID=%d for SN=%s (not served recently)",
        device_command_id,
        serial,
    )
    return None


def _pace_face_delivery(serial: str, commands: list, now: float | None = None) -> list:
    now = time.time() if now is None else now
    pending_faces = [c for c in commands if c.get("command_type") == "face_template"]
    if not pending_faces:
        return commands
    with DELIVERY._lock:
        for command in pending_faces:
            cid = command.get("id")
            if cid is None:
                continue
            first = DELIVERY._first_served_at.get((serial, cid))
            if first is not None and now - first < FACE_SERIAL_COOLDOWN_SECONDS:
                return [c for c in commands if c.get("command_type") != "face_template"]
    return commands


def _cap_face_commands(commands: list) -> list:
    """Allow up to 5 face commands per device poll for faster distribution.

    The original limit of 1 face command per poll caused distribution of
    thousands of templates to take hours.  5 commands per poll keeps the
    device buffer manageable while being ~5x faster.
    """
    deliverable = []
    face_count = 0
    MAX_FACE_PER_POLL = 5
    for command in commands:
        if command.get("command_type") == "face_template":
            face_count += 1
            if face_count > MAX_FACE_PER_POLL:
                break
        deliverable.append(command)
    return deliverable


USER_COMMAND_HOLD_SECONDS = max(60, int(os.environ.get("ADMS_USER_COMMAND_HOLD_SECONDS", "900")))


def apply_delivery_guards(serial: str, commands: list) -> list:
    now = time.time()
    commands = sorted(commands, key=_command_sort_key)
    commands = _pace_face_delivery(serial, commands, now)

    # Record first-serve time for every delivered command: the tracker uses
    # it for TTL pruning and to attribute device acknowledgements.
    for command in commands:
        cid = command.get("id")
        if cid is not None:
            DELIVERY.note_served(serial, cid, now)

    # Terminals reject face/fingerprint templates with Return=-3 while the
    # user record does not exist yet, so hold templates for a PIN until its
    # user create/update command has been acknowledged (or the hold times
    # out as a safety valve — retries cover a failed user write anyway).
    held_user_pins: set[str] = set()
    for command in commands:
        if command.get("command_type") not in USER_COMMAND_TYPES:
            continue
        pin = _extract_user_pin(str(command.get("command_body", "")))
        if not pin:
            continue
        cid = command.get("id")
        first_served = DELIVERY._first_served_at.get((serial, cid)) if cid is not None else None
        if first_served is None or now - first_served < USER_COMMAND_HOLD_SECONDS:
            held_user_pins.add(pin)

    if not held_user_pins:
        return _cap_face_commands(commands)

    deliverable = []
    for command in commands:
        if command.get("command_type") == "face_template":
            pin = _extract_face_pin(str(command.get("command_body", "")))
            if pin and pin in held_user_pins:
                continue
        deliverable.append(command)
    return _cap_face_commands(deliverable)


def enqueue_mark_sending(serial: str, served_ids: list) -> None:
    ids = DELIVERY.ids_to_mark(serial, served_ids)
    if not ids:
        return
    OUTBOX.enqueue("mark_sending", {"SN": serial, "command_ids": ids})


def _serve_commands(serial: str) -> str:
    assert OUTBOX is not None
    commands = DELIVERY.prune(serial, OUTBOX.cached_commands(serial))
    deliverable = apply_delivery_guards(serial, commands)
    served = [c.get("id") for c in deliverable if c.get("id") is not None]
    if served:
        logger.info("SERVING %s for SN=%s: %s", len(served), serial, served)
    enqueue_mark_sending(serial, served)

    for command in deliverable:
        if command.get("command_type") not in NO_ACK_COMMAND_TYPES:
            continue
        cid = command.get("id")
        if cid is None:
            continue
        OUTBOX.remove_cached_command(serial, cid)
        DELIVERY.clear(serial, cid)
        OUTBOX.enqueue("command_result", {
            "SN": serial, "command_id": cid, "status": "completed",
            "return_code": "0", "error_message": None, "result": {"return_code": "0"},
        })

    return build_get_request_response(deliverable)


class BoundedThreadingHTTPServer(ThreadingHTTPServer):
    """ThreadingHTTPServer with a hard upper bound on active request threads."""
    daemon_threads = True
    request_queue_size = 256

    def __init__(self, address, handler_class, max_workers: int):
        self._request_slots = threading.BoundedSemaphore(max_workers)
        super().__init__(address, handler_class)

    def process_request_thread(self, request, client_address):
        try:
            super().process_request_thread(request, client_address)
        finally:
            self._request_slots.release()

    def process_request(self, request, client_address):
        # Back-pressure is bounded by the OS listen backlog instead of unbounded
        # Python threads. ADMS devices will retry a connection if it is busy.
        self._request_slots.acquire()
        try:
            super().process_request(request, client_address)
        except Exception:
            self._request_slots.release()
            raise


class ADMSHandler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def setup(self):
        super().setup()
        self.connection.settimeout(20)

    def log_message(self, format, *args):
        routine_logger.debug("%s - %s", self.client_address[0], format % args)

    def _respond(self, body: str, status: int = 200) -> None:
        encoded = body.encode("utf-8", errors="ignore")
        self.send_response(status)
        self.send_header("Content-Type", "text/plain")
        self.send_header("Content-Length", str(len(encoded)))
        self.send_header("Connection", "close")
        self.end_headers()
        self.wfile.write(encoded)

    def _queue_heartbeat(self, serial: str, info: dict | None = None) -> None:
        assert OUTBOX is not None
        payload = {"SN": serial, "ip": self.client_address[0]}
        if info:
            payload["info"] = info
        OUTBOX.enqueue("heartbeat", payload)

    def do_GET(self):
        assert OUTBOX is not None
        path = urlparse(self.path).path.lower()
        serial = extract_sn(self.path)
        params = extract_query_params(self.path)
        if "/iclock/getrequest" in path:
            OUTBOX.request_command_refresh(serial)
            response = _serve_commands(serial)
            info = _parse_device_info(str(params.get("INFO", "")))
            if info:
                self._queue_heartbeat(serial, info)
        elif "/iclock/ping" in path:
            self._queue_heartbeat(serial)
            response = "PONG\r\n"
        else:
            response = build_get_option_response(serial)
        if SAVE_RAW:
            save_raw("GET", f"PATH: {self.path}\nSN: {serial}\n\nRESPONSE:\n{response}")
        self._respond(response)

    def do_POST(self):
        assert OUTBOX is not None
        try:
            length = int(self.headers.get("Content-Length", "0"))
        except ValueError:
            self._respond("ERROR", 400)
            return
        if length < 0 or length > MAX_REQUEST_BYTES:
            logger.warning("Rejected oversized ADMS request from %s: %d bytes", self.client_address[0], length)
            self._respond("ERROR", 413)
            return
        body = self.rfile.read(length).decode("utf-8", errors="ignore") if length else ""
        path = urlparse(self.path).path.lower()
        serial = extract_sn(self.path)
        params = extract_query_params(self.path)
        correlation_id = generate_correlation_id()
        is_command_result = "/iclock/devicecmd" in path

        if is_command_result:
            result = parse_command_result(body)
            if result:
                resolved_id = resolve_command_id(serial, result["command_id"])
                if resolved_id is not None:
                    result["command_id"] = resolved_id
                    OUTBOX.remove_cached_command(serial, resolved_id)
                    DELIVERY.clear(serial, resolved_id)
                    OUTBOX.enqueue("command_result", {"SN": serial, **result, "result": {"return_code": result["return_code"]}})
            else:
                logger.warning("[%s] Invalid DEVICECMD acknowledgement from SN=%s", correlation_id, serial)
            response = "OK"
        elif "/iclock/ping" in path:
            self._queue_heartbeat(serial, _parse_heartbeat_body(body))
            response = "PONG\r\n"
        elif "/iclock/getrequest" in path:
            OUTBOX.request_command_refresh(serial)
            response = _serve_commands(serial)
        else:
            if body.strip():
                table = classify_payload(body, extract_table(self.path), self.path)
                payload = {"SN": serial, "Body": body, "_correlation_id": correlation_id, "_table": table}
                if params:
                    payload["_query"] = params
                OUTBOX.enqueue("forward", {"table": table, "data": payload})
                if table == "UNKNOWN":
                    logger.warning("[%s] Queued unknown ADMS payload from SN=%s", correlation_id, serial)
            response = "OK"

        if SAVE_RAW:
            saved_body = "[command acknowledgement redacted]" if is_command_result else body[:5000]
            save_raw("POST", f"PATH: {self.path}\nSN: {serial}\n\nBODY:\n{saved_body}\n\nRESPONSE:\n{response}")
        self._respond(response)

    def do_OPTIONS(self):
        self._respond("OK")


def _parse_device_info(info_str: str) -> dict:
    if not info_str:
        return {}
    parts = info_str.split(",")
    if len(parts) < 2:
        return {"raw_info": info_str}
    return {"platform": parts[0], "firmware": parts[0], "user_count": int(parts[1]) if parts[1].isdigit() else 0,
            "face_count": int(parts[2]) if len(parts) > 2 and parts[2].isdigit() else 0,
            "fp_count": int(parts[3]) if len(parts) > 3 and parts[3].isdigit() else 0,
            "device_ip": parts[4] if len(parts) > 4 else ""}


def _parse_heartbeat_body(body: str) -> dict:
    return {key.strip(): value.strip() for line in body.strip().splitlines() if "=" in line for key, _, value in [line.partition("=")]}


def main():
    global LARAVEL_URL, OUTBOX
    parser = argparse.ArgumentParser(description="Reliable ZKTeco ADMS Server")
    parser.add_argument("--port", type=int, default=9000)
    parser.add_argument("--host", default="0.0.0.0")
    parser.add_argument("--laravel", default=LARAVEL_URL)
    args = parser.parse_args()
    LARAVEL_URL = args.laravel.rstrip("/")
    OUTBOX = PersistentOutbox(OUTBOX_PATH)
    OUTBOX.start()
    server = BoundedThreadingHTTPServer((args.host, args.port), ADMSHandler, REQUEST_WORKERS)
    logger.info("ADMS server on %s:%d; outbox=%s; request workers=%d; Laravel workers=%d", args.host, args.port, OUTBOX_PATH, REQUEST_WORKERS, FORWARD_WORKERS)
    try:
        server.serve_forever(poll_interval=0.5)
    except KeyboardInterrupt:
        logger.info("Shutting down ADMS server")
    finally:
        server.shutdown()
        server.server_close()
        OUTBOX.stop()


if __name__ == "__main__":
    main()
