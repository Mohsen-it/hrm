#!/usr/bin/env python3
"""
ZKTeco ADMS Server — Production-Grade Two-Way Synchronization

Supports the complete ZKTeco ADMS protocol:
  - POST /iclock/cdata          → Inbound data (ATTLOG, BIODATA, USERINFO, OPERLOG, OPTIONS, PHOTO, USERPIC)
  - GET  /iclock/getrequest     → Device polls for pending commands
  - POST /iclock/ping           → Device heartbeat / registration
  - GET  /iclock/cdata          → Device pull for options/configuration

Every inbound payload is validated, parsed, classified, and forwarded to Laravel.
Every outbound command is fetched from the Laravel command queue via API.
"""

import argparse
import json
import logging
import os
import sys
import time
import urllib.request
import urllib.error
import uuid
from datetime import datetime
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs, unquote_plus

LOG_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "logs")
os.makedirs(LOG_DIR, exist_ok=True)

LARAVEL_URL = os.environ.get("LARAVEL_URL", "http://127.0.0.1:8000")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s %(message)s",
    handlers=[
        logging.FileHandler(os.path.join(LOG_DIR, "adms.log"), encoding="utf-8"),
        logging.StreamHandler(),
    ],
)

logger = logging.getLogger("ADMS")

# ---------------------------------------------------------------------------
# Table type constants
# ---------------------------------------------------------------------------
TABLE_ATTLOG = "ATTLOG"
TABLE_BIODATA = "BIODATA"
TABLE_USERINFO = "USERINFO"
TABLE_OPERLOG = "OPERLOG"
TABLE_OPTIONS = "OPTIONS"
TABLE_PHOTO = "PHOTO"
TABLE_USERPIC = "USERPIC"

INBOUND_TABLES = {
    TABLE_ATTLOG,
    TABLE_BIODATA,
    TABLE_USERINFO,
    TABLE_OPERLOG,
    TABLE_OPTIONS,
    TABLE_PHOTO,
    TABLE_USERPIC,
}


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
def generate_correlation_id() -> str:
    return uuid.uuid4().hex[:16]


def extract_sn(path: str) -> str:
    query = parse_qs(urlparse(path).query)
    return query.get("SN", ["UNKNOWN"])[0]


def extract_table(path: str) -> str:
    query = parse_qs(urlparse(path).query)
    return query.get("table", [""])[0]


def extract_query_params(path: str) -> dict:
    query = parse_qs(urlparse(path).query)
    return {k: v[0] if len(v) == 1 else v for k, v in query.items()}


def save_raw(prefix: str, content: str) -> str:
    filename = f"{prefix}_{datetime.now().strftime('%Y%m%d_%H%M%S_%f')}.txt"
    path = os.path.join(LOG_DIR, filename)
    with open(path, "w", encoding="utf-8", errors="ignore") as f:
        f.write(content)
    return path


def classify_payload(body: str, table_param: str, path: str) -> str:
    """Classify the inbound payload into a known ADMS table type."""
    if table_param:
        t = table_param.upper()
        if t in INBOUND_TABLES:
            return t

    body_upper = body.upper().strip()

    if body_upper.startswith("BIODATA") or ("PIN=" in body_upper and "TMP=" in body_upper):
        return TABLE_BIODATA
    if body_upper.startswith("ATTLOG") or (body_upper.startswith("ATT") and "\t" in body):
        return TABLE_ATTLOG
    if "USERPIC" in body_upper and "CONTENT=" in body_upper:
        return TABLE_USERPIC
    if body_upper.startswith("USERINFO") or "PIN=" in body_upper:
        return TABLE_USERINFO
    if body_upper.startswith("OPERLOG") or body_upper.startswith("OPLOG"):
        return TABLE_OPERLOG

    lines = body.strip().split("\n")
    for line in lines[:5]:
        ls = line.strip()
        if ls.startswith("ATT") and "\t" in ls:
            return TABLE_ATTLOG
        if ls.upper().startswith("BIODATA"):
            return TABLE_BIODATA

    return "UNKNOWN"


# ---------------------------------------------------------------------------
# Laravel API helpers
# ---------------------------------------------------------------------------
def _laravel_request(method: str, endpoint: str, data: dict = None, timeout: int = 30) -> dict | None:
    """Make an HTTP request to the Laravel backend."""
    try:
        url = f"{LARAVEL_URL}{endpoint}"
        body = json.dumps(data).encode("utf-8") if data else None
        req = urllib.request.Request(url, data=body, headers={"Content-Type": "application/json"})
        req.get_method = lambda: method
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        if e.code >= 500:
            logger.warning("Laravel HTTP %s %s → %s", method, endpoint, e.code)
        else:
            logger.error("Laravel HTTP %s %s → %s: %s", method, endpoint, e.code, e.read().decode("utf-8", errors="ignore")[:200])
        return None
    except urllib.error.URLError as e:
        # Connection refused - Laravel is down, log once per minute
        if not hasattr(_laravel_request, '_last_warn'):
            _laravel_request._last_warn = {}
        key = f"{method}:{endpoint}"
        now = time.time()
        if key not in _laravel_request._last_warn or now - _laravel_request._last_warn[key] > 60:
            logger.warning("Laravel unreachable %s %s: %s", method, endpoint, str(e.reason)[:100])
            _laravel_request._last_warn[key] = now
        return None
    except Exception as e:
        logger.error("Laravel request failed %s %s: %s", method, endpoint, e)
        return None


def forward_to_laravel(serial: str, body: str, table_type: str, correlation_id: str, query_params: dict = None) -> bool:
    """Forward inbound device data to the appropriate Laravel endpoint."""
    if not body or not body.strip():
        return False

    endpoint_map = {
        TABLE_ATTLOG: "/api/attendance-integration/push/adms",
        TABLE_BIODATA: "/api/attendance-integration/push/biodata",
        TABLE_USERPIC: "/api/attendance-integration/push/userpic",
    }

    if table_type in endpoint_map:
        endpoint = endpoint_map[table_type]
    elif table_type in (TABLE_USERINFO, TABLE_OPERLOG, TABLE_OPTIONS, TABLE_PHOTO):
        endpoint = "/api/attendance-integration/push/adms"
    else:
        endpoint = "/api/attendance-integration/push/adms"

    payload = {
        "SN": serial,
        "Body": body,
        "_correlation_id": correlation_id,
        "_table": table_type,
    }
    if query_params:
        payload["_query"] = query_params

    result = _laravel_request("POST", endpoint, payload, timeout=60)
    if result:
        logger.info("[%s] Forwarded %s → Laravel OK: %s", correlation_id, table_type, result.get("message", "ok"))
        return True
    return False


def fetch_commands(serial: str) -> list:
    """Fetch pending commands for this device from the Laravel command queue."""
    result = _laravel_request("GET", f"/api/adms/commands?SN={serial}", timeout=10)
    if result and result.get("success"):
        return result.get("commands", [])
    return []


def report_command_result(serial: str, command_id: int, status: str, result_data: dict = None) -> bool:
    """Report the result of a command execution back to Laravel."""
    payload = {
        "SN": serial,
        "command_id": command_id,
        "status": status,
    }
    if result_data:
        payload["result"] = result_data
    resp = _laravel_request("POST", "/api/adms/commands/result", payload, timeout=10)
    return resp is not None


def report_heartbeat(serial: str, ip: str, info: dict = None) -> bool:
    """Report device heartbeat / registration to Laravel."""
    payload = {
        "SN": serial,
        "ip": ip,
    }
    if info:
        payload["info"] = info
    resp = _laravel_request("POST", "/api/adms/heartbeat", payload, timeout=10)
    return resp is not None


def _parse_device_info(info_str: str) -> dict:
    """Parse the INFO parameter from getrequest.
    
    Format: ZMM720-NF43HB-Ver1.2.16,859,1144,486,10.10.250.8,10,12,12,551,111,1,595,0
    Fields: platform, user_count, face_count, fp_count, ip, admin_count, ...
    """
    if not info_str:
        return {}
    
    parts = info_str.split(",")
    if len(parts) < 2:
        return {"raw_info": info_str}
    
    info = {
        "platform": parts[0] if len(parts) > 0 else "",
        "user_count": int(parts[1]) if len(parts) > 1 and parts[1].isdigit() else 0,
        "face_count": int(parts[2]) if len(parts) > 2 and parts[2].isdigit() else 0,
        "fp_count": int(parts[3]) if len(parts) > 3 and parts[3].isdigit() else 0,
        "device_ip": parts[4] if len(parts) > 4 else "",
    }
    
    # Parse platform/version from "ZMM720-NF43HB-Ver1.2.16"
    if info["platform"]:
        info["firmware"] = info["platform"]
    
    return info


# ---------------------------------------------------------------------------
# ADMS Response builders
# ---------------------------------------------------------------------------
def build_get_option_response(sn: str) -> str:
    """Build the response for GET /iclock/cdata (device pull for options)."""
    return (
        f"GET OPTION FROM: {sn}\r\n"
        "ATTLOGStamp=None\r\n"
        "OPERLOGStamp=None\r\n"
        "ATTPHOTOStamp=None\r\n"
        "ErrorDelay=60\r\n"
        "Delay=30\r\n"
        "TransTimes=00:00;23:59\r\n"
        "TransInterval=1\r\n"
        "TransFlag=1111111111\r\n"
        "TimeZone=3\r\n"
        "Realtime=1\r\n"
        "Encrypt=0\r\n"
    )


def build_get_request_response(commands: list) -> str:
    """Build the response for GET /iclock/getrequest.
    
    If there are pending commands, format them according to ZKTeco ADMS protocol.
    If no commands, return 'OK' to acknowledge.
    """
    if not commands:
        return "OK"

    lines = []
    for cmd in commands:
        cmd_id = cmd.get("id", 0)
        cmd_type = cmd.get("command_type", "")
        cmd_body = cmd.get("command_body", "")

        lines.append(f"CMD {cmd_id} {cmd_body}")

    return "\r\n".join(lines) + "\r\n"


def build_ping_response(sn: str) -> str:
    """Build the response for POST /iclock/ping."""
    return "PONG\r\n"


# ---------------------------------------------------------------------------
# HTTP Handler
# ---------------------------------------------------------------------------
class ADMSHandler(BaseHTTPRequestHandler):

    def log_message(self, format, *args):
        logger.info("%s - %s", self.client_address[0], format % args)

    # ---- GET ----
    def do_GET(self):
        sn = extract_sn(self.path)
        parsed = urlparse(self.path)
        path_lower = parsed.path.lower()
        params = extract_query_params(self.path)

        correlation_id = generate_correlation_id()
        client_ip = self.client_address[0]

        logger.info("[%s] GET %s SN=%s IP=%s", correlation_id, self.path, sn, client_ip)

        if "/iclock/getrequest" in path_lower:
            # Device is polling for pending commands
            commands = fetch_commands(sn)
            response = build_get_request_response(commands)
            logger.info("[%s] GETREQUEST SN=%s → %d commands", correlation_id, sn, len(commands))

            # Parse INFO parameter if present (device stats)
            info_str = params.get("INFO", "")
            if info_str:
                device_info = _parse_device_info(info_str)
                if device_info:
                    report_heartbeat(sn, client_ip, device_info)
                    logger.info("[%s] DEVICE_INFO SN=%s: %s", correlation_id, sn, device_info)

        elif "/iclock/cdata" in path_lower:
            # Device is requesting options/config
            response = build_get_option_response(sn)
            logger.info("[%s] GET CDATA (options) SN=%s", correlation_id, sn)

        elif "/iclock/ping" in path_lower:
            # Device heartbeat
            report_heartbeat(sn, client_ip)
            response = build_ping_response(sn)
            logger.info("[%s] PING SN=%s", correlation_id, sn)

        else:
            # Default: return options
            response = build_get_option_response(sn)
            logger.info("[%s] DEFAULT GET SN=%s path=%s", correlation_id, sn, self.path)

        # Save raw for debugging
        save_raw("GET", f"PATH: {self.path}\nSN: {sn}\nIP: {client_ip}\n\nRESPONSE:\n{response}")

        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.send_header("Connection", "close")
        self.end_headers()
        self.wfile.write(response.encode("utf-8", errors="ignore"))

    # ---- POST ----
    def do_POST(self):
        sn = extract_sn(self.path)
        table_param = extract_table(self.path)
        parsed = urlparse(self.path)
        path_lower = parsed.path.lower()
        params = extract_query_params(self.path)

        length = int(self.headers.get("Content-Length", 0))
        body_bytes = self.rfile.read(length) if length > 0 else b""
        body = body_bytes.decode("utf-8", errors="ignore")

        correlation_id = generate_correlation_id()
        client_ip = self.client_address[0]

        logger.info("=" * 80)
        logger.info("[%s] POST %s SN=%s SIZE=%d TABLE=%s IP=%s",
                     correlation_id, self.path, sn, len(body_bytes), table_param, client_ip)

        # ---- Route by path ----
        if "/iclock/ping" in path_lower:
            # Device registration / heartbeat with body
            info = {}
            if body.strip():
                info = self._parse_heartbeat_body(body)
            report_heartbeat(sn, client_ip, info)
            response = "PONG\r\n"
            logger.info("[%s] PING with body SN=%s info=%s", correlation_id, sn, info)

        elif "/iclock/cdata" in path_lower:
            # Inbound data push
            table_type = classify_payload(body, table_param, self.path)
            logger.info("[%s] CDATA classified as TABLE=%s", correlation_id, table_type)

            if table_type == "UNKNOWN":
                logger.warning("[%s] UNKNOWN payload from SN=%s: %s", correlation_id, sn, body[:300])

            if body.strip():
                forward_to_laravel(sn, body, table_type, correlation_id, params)
            response = "OK"

        elif "/iclock/getrequest" in path_lower:
            # Device also POSTs getrequest sometimes
            commands = fetch_commands(sn)
            response = build_get_request_response(commands)
            logger.info("[%s] POST GETREQUEST SN=%s → %d commands", correlation_id, sn, len(commands))

        else:
            # Generic POST — classify and forward
            table_type = classify_payload(body, table_param, self.path)
            logger.info("[%s] GENERIC POST classified as TABLE=%s", correlation_id, table_type)

            if body.strip():
                forward_to_laravel(sn, body, table_type, correlation_id, params)
            response = "OK"

        # Save raw for debugging
        raw_dump = f"PATH: {self.path}\nSN: {sn}\nIP: {client_ip}\nTABLE: {table_param}\n\nHEADERS:\n"
        for k, v in self.headers.items():
            raw_dump += f"{k}: {v}\n"
        raw_dump += f"\nBODY:\n{body[:5000]}\n\nRESPONSE:\n{response}"
        save_raw("POST", raw_dump)

        logger.info("[%s] Response: %s", correlation_id, response.replace("\r\n", "\\r\\n")[:100])
        logger.info("=" * 80)

        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.send_header("Connection", "close")
        self.end_headers()
        self.wfile.write(response.encode("utf-8", errors="ignore"))

    # ---- OPTIONS ----
    def do_OPTIONS(self):
        """Handle CORS preflight and ADMS OPTIONS requests."""
        sn = extract_sn(self.path)
        logger.info("OPTIONS %s SN=%s", self.path, sn)

        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.send_header("Allow", "GET, POST, OPTIONS")
        self.end_headers()
        self.wfile.write(b"OK")

    def _parse_heartbeat_body(self, body: str) -> dict:
        """Parse heartbeat/registration body for device info."""
        info = {}
        for line in body.strip().split("\n"):
            line = line.strip()
            if "=" in line:
                key, _, value = line.partition("=")
                info[key.strip()] = value.strip()
        return info


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main():
    parser = argparse.ArgumentParser(description="ZKTeco ADMS Server")
    parser.add_argument("--port", type=int, default=9000)
    parser.add_argument("--host", default="0.0.0.0")
    parser.add_argument("--laravel", default="http://127.0.0.1:8000")
    args = parser.parse_args()

    global LARAVEL_URL
    LARAVEL_URL = args.laravel.rstrip("/")

    server = HTTPServer((args.host, args.port), ADMSHandler)

    logger.info("=" * 60)
    logger.info("ZKTeco ADMS Server starting on %s:%s", args.host, args.port)
    logger.info("Laravel API: %s", LARAVEL_URL)
    logger.info("Log directory: %s", LOG_DIR)
    logger.info("Protocol endpoints:")
    logger.info("  POST /iclock/cdata      → Inbound data (ATTLOG, BIODATA, USERINFO, etc.)")
    logger.info("  GET  /iclock/getrequest → Device polls for commands")
    logger.info("  POST /iclock/ping       → Device heartbeat / registration")
    logger.info("  GET  /iclock/cdata      → Device pull for options")
    logger.info("=" * 60)

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        logger.info("Shutting down ADMS server")
        server.shutdown()


if __name__ == "__main__":
    main()
