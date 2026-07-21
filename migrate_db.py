#!/usr/bin/env python3
"""
HRM Migration Script: PostgreSQL (ZKTeco/BioStar) → MySQL (Laravel HRM)
Reads a pg_dump COPY SQL file and imports all data into the HRM database.

Usage: python migrate_db.py
"""

import re
import sys
import json
import hashlib
from datetime import datetime, date, time
from collections import OrderedDict

import pymysql

# ──────────────────── CONFIG ────────────────────
DUMP_FILE = r"D:\hrm_alepair\20260720142938.sql"

DB_CONFIG = {
    "host": "127.0.0.1",
    "user": "root",
    "password": "",
    "database": "hrmair",
    "charset": "utf8mb4",
}

COMPANY_ID = 1
BRANCH_ID = 1
SUBORDINATION_CODE = "ALEPPO-AIRPORT"
SUBORDINATION_NAME = "مطار حلب"
ASSIGNMENT_START_DATE = "2026-07-01"
EMAIL_DOMAIN = "aleppo-airport.com"

# ──────────────────── PARSER ────────────────────

def parse_pg_dump(filepath):
    """Parse a PostgreSQL pg_dump file and extract COPY data for all tables."""
    tables = {}
    current_table = None
    current_columns = None
    current_rows = []

    with open(filepath, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            line = line.rstrip("\n").rstrip("\r")

            if current_table is None:
                m = re.match(r"^COPY public\.(\w+)\s*\((.+?)\)\s*FROM\s*stdin;", line)
                if m:
                    current_table = m.group(1)
                    cols_raw = m.group(2)
                    current_columns = [c.strip().strip('"') for c in cols_raw.split(",")]
                    current_rows = []
                continue

            if line == "\\.":
                tables[current_table] = {"columns": current_columns, "rows": current_rows}
                current_table = None
                current_columns = None
                current_rows = []
                continue

            values = parse_copy_line(line)
            if len(values) == len(current_columns):
                current_rows.append(values)
            elif len(values) < len(current_columns):
                # Pad with None for missing trailing columns
                while len(values) < len(current_columns):
                    values.append(None)
                current_rows.append(values)
            else:
                # More values than columns — truncate or skip
                current_rows.append(values[:len(current_columns)])

    return tables


def parse_copy_line(line):
    """Parse a single line of PostgreSQL COPY (tab-separated) data."""
    values = []
    current = ""
    i = 0
    while i < len(line):
        ch = line[i]
        if ch == "\\":
            if i + 1 < len(line):
                nxt = line[i + 1]
                if nxt == "N":
                    values.append(None)
                    # Skip past \N; find next tab or end
                    i += 2
                    if i < len(line) and line[i] == "\t":
                        i += 1
                    current = ""
                    continue
                elif nxt == "t":
                    current += "\t"
                    i += 2
                    continue
                elif nxt == "n":
                    current += "\n"
                    i += 2
                    continue
                elif nxt == "r":
                    current += "\r"
                    i += 2
                    continue
                elif nxt == "\\":
                    current += "\\"
                    i += 2
                    continue
                else:
                    current += ch
                    i += 1
                    continue
            else:
                current += ch
                i += 1
                continue
        elif ch == "\t":
            values.append(convert_value(current))
            current = ""
            i += 1
        else:
            current += ch
            i += 1

    values.append(convert_value(current))
    return values


def convert_value(val):
    """Convert COPY value to Python type."""
    if val is None:
        return None
    return val


def as_dict(row, columns):
    """Convert a row (list of values) to a dict keyed by column name."""
    return OrderedDict(zip(columns, row))


# ──────────────────── HELPERS ────────────────────

def safe_int(val, default=None):
    if val is None or val == "" or val == "\\N":
        return default
    try:
        return int(val)
    except (ValueError, TypeError):
        return default


def safe_str(val, default=None):
    if val is None or val == "\\N":
        return default
    return str(val).strip()


def safe_date(val):
    if val is None or val == "\\N" or val == "":
        return None
    try:
        val_str = str(val).strip()
        # Handle various date formats
        for fmt in ["%Y-%m-%d", "%Y-%m-%d %H:%M:%S", "%Y-%m-%d %H:%M:%S%z", "%Y-%m-%d %H:%M:%S+00"]:
            try:
                # Remove timezone info
                if "+" in val_str:
                    val_str = val_str.split("+")[0].strip()
                return datetime.strptime(val_str, "%Y-%m-%d %H:%M:%S").strftime("%Y-%m-%d %H:%M:%S")
            except ValueError:
                continue
        # Try date only
        return datetime.strptime(val_str[:10], "%Y-%m-%d").strftime("%Y-%m-%d %H:%M:%S")
    except Exception:
        return None


def safe_time(val):
    """Convert time string to MySQL time format."""
    if val is None or val == "\\N" or val == "":
        return "00:00:00"
    val_str = str(val).strip()
    # Handle "HH:MM:SS" or "HH:MM:SS+tz"
    if "+" in val_str:
        val_str = val_str.split("+")[0].strip()
    parts = val_str.split(":")
    if len(parts) >= 2:
        return f"{parts[0].zfill(2)}:{parts[1].zfill(2)}:{parts[2].zfill(2) if len(parts) > 2 else '00'}"
    return "00:00:00"


def safe_bool(val, default=False):
    if val is None or val == "\\N":
        return default
    val_str = str(val).strip().lower()
    if val_str in ("t", "true", "1", "yes"):
        return True
    if val_str in ("f", "false", "0", "no"):
        return False
    return default


def safe_float(val, default=0.0):
    if val is None or val == "\\N" or val == "":
        return default
    try:
        return float(val)
    except (ValueError, TypeError):
        return default


def now():
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


# ──────────────────── MYSQL ────────────────────

class MySQL:
    def __init__(self, config):
        self.conn = pymysql.connect(
            host=config["host"],
            user=config["user"],
            password=config["password"],
            database=config["database"],
            charset=config["charset"],
            autocommit=False,
        )
        self.cursor = self.conn.cursor()
        self.cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
        self.conn.commit()

    def commit(self):
        self.conn.commit()

    def execute(self, sql, params=None):
        self.cursor.execute(sql, params or ())

    def last_id(self):
        return self.cursor.lastrowid

    def select_one(self, sql, params=None):
        self.cursor.execute(sql, params or ())
        row = self.cursor.fetchone()
        if row:
            cols = [d[0] for d in self.cursor.description]
            return dict(zip(cols, row))
        return None

    def select_all(self, sql, params=None):
        self.cursor.execute(sql, params or ())
        cols = [d[0] for d in self.cursor.description]
        return [dict(zip(cols, r)) for r in self.cursor.fetchall()]

    def upsert(self, table, data, unique_cols, id_col="id"):
        """Insert or update if exists by unique columns. Returns the id."""
        where = " AND ".join(f"`{c}` = %s" for c in unique_cols)
        where_vals = [data[c] for c in unique_cols]
        existing = self.select_one(f"SELECT `{id_col}` FROM `{table}` WHERE {where}", where_vals)
        if existing:
            set_clause = ", ".join(f"`{k}` = %s" for k in data.keys() if k not in unique_cols and k != id_col)
            set_vals = [data[k] for k in data.keys() if k not in unique_cols and k != id_col]
            if set_clause:
                self.execute(
                    f"UPDATE `{table}` SET {set_clause} WHERE {where}",
                    set_vals + where_vals,
                )
            return existing[id_col]
        else:
            cols = ", ".join(f"`{k}`" for k in data.keys())
            placeholders = ", ".join("%s" for _ in data)
            vals = list(data.values())
            self.execute(f"INSERT INTO `{table}` ({cols}) VALUES ({placeholders})", vals)
            return self.last_id()

    def insert(self, table, data):
        """Insert and return last id."""
        cols = ", ".join(f"`{k}`" for k in data.keys())
        placeholders = ", ".join("%s" for _ in data)
        vals = list(data.values())
        self.execute(f"INSERT INTO `{table}` ({cols}) VALUES ({placeholders})", vals)
        return self.last_id()

    def close(self):
        self.cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
        self.conn.commit()
        self.cursor.close()
        self.conn.close()


# ──────────────────── MIGRATOR ────────────────────

class Migrator:
    def __init__(self):
        print(f"Loading dump file: {DUMP_FILE}")
        print("Parsing PostgreSQL COPY data...")
        self.tables = parse_pg_dump(DUMP_FILE)
        print(f"Found {len(self.tables)} tables with data:")
        for tname, tdata in sorted(self.tables.items()):
            print(f"  {tname}: {len(tdata['rows'])} rows")

        print(f"\nConnecting to MySQL ({DB_CONFIG['host']})...")
        self.db = MySQL(DB_CONFIG)
        print("Connected.")

        # ID maps: old_id → new_id
        self.dept_map = {}       # old department id → new department id
        self.pos_map = {}        # old position id → new position id
        self.time_sched_map = {} # time_interval alias → new time_schedule id
        self.rotation_map = {}   # rotation name → new rotation id
        self.rot_group_map = {}  # (rotation_name, group_name) → new rotation_group_id
        self.user_map = {}       # old employee id → new user id
        self.group_to_target = {}  # old group_id → (rotation_id, rotation_group_id)

        # Verify company & branch exist
        company = self.db.select_one("SELECT id FROM companies WHERE id = %s", (COMPANY_ID,))
        if not company:
            print(f"ERROR: Company id={COMPANY_ID} not found in database!")
            sys.exit(1)
        print(f"Company id={COMPANY_ID} exists: OK")

        branch = self.db.select_one("SELECT id FROM branches WHERE id = %s", (BRANCH_ID,))
        if not branch:
            print(f"ERROR: Branch id={BRANCH_ID} not found in database!")
            sys.exit(1)
        print(f"Branch id={BRANCH_ID} exists: OK")

    def run(self):
        try:
            self.migrate_subordination()
            self.migrate_departments()
            self.migrate_positions()
            self.migrate_time_schedules()
            self.migrate_rotations_and_groups()
            self.migrate_users()
            self.migrate_rotation_assignments()
            self.migrate_vacation_types()
            self.migrate_vacation_requests()
            self.db.conn.commit()
            print("\n" + "=" * 60)
            print("MIGRATION COMPLETED SUCCESSFULLY!")
            print("=" * 60)
        except Exception as e:
            self.db.conn.rollback()
            print(f"\nERROR: {e}")
            import traceback
            traceback.print_exc()
            sys.exit(1)
        finally:
            self.db.close()

    # ─── STEP 1: Subordination ───
    def migrate_subordination(self):
        print("\n[1/11] Creating subordination 'مطار حلب'...")
        sid = self.db.upsert(
            "subordinations",
            {
                "code": SUBORDINATION_CODE,
                "name_ar": SUBORDINATION_NAME,
                "name_en": "Aleppo Airport",
                "status": 1,
                "sort_order": 1,
                "created_at": now(),
                "updated_at": now(),
            },
            unique_cols=["code"],
        )
        self.subordination_id = sid
        print(f"  Subordination id={sid}")

    # ─── STEP 2: Departments ───
    def migrate_departments(self):
        print("\n[2/11] Migrating departments...")
        tdata = self.tables.get("personnel_department")
        if not tdata:
            print("  No personnel_department data found, skipping.")
            return
        for row in tdata["rows"]:
            d = as_dict(row, tdata["columns"])
            old_id = safe_int(d.get("id"))
            code = safe_str(d.get("dept_code"), "")
            name = safe_str(d.get("dept_name"), code)
            parent_old = safe_int(d.get("parent_dept_id"))

            dept_data = OrderedDict([
                ("company_id", COMPANY_ID),
                ("branch_id", BRANCH_ID),
                ("department_code", code),
                ("department_name", name),
                ("parent_id", None),
                ("status", 1),
                ("created_at", now()),
                ("updated_at", now()),
            ])

            if name == "Department" or code == "1":
                # Skip the default placeholder department
                self.dept_map[old_id] = None
                continue

            new_id = self.db.upsert(
                "departments",
                dept_data,
                unique_cols=["branch_id", "department_code"],
            )
            self.dept_map[old_id] = new_id

        # Second pass: fix parent references
        for row in tdata["rows"]:
            d = as_dict(row, tdata["columns"])
            old_id = safe_int(d.get("id"))
            parent_old = safe_int(d.get("parent_dept_id"))
            if parent_old and parent_old in self.dept_map and self.dept_map[parent_old]:
                new_id = self.dept_map.get(old_id)
                if new_id:
                    self.db.execute(
                        "UPDATE departments SET parent_id = %s WHERE id = %s",
                        (self.dept_map[parent_old], new_id),
                    )

        count = len([v for v in self.dept_map.values() if v is not None])
        print(f"  Migrated {count} departments")

    # ─── STEP 3: Positions ───
    def migrate_positions(self):
        print("\n[3/11] Migrating positions...")
        tdata = self.tables.get("personnel_position")
        if not tdata:
            print("  No personnel_position data, skipping.")
            return
        for row in tdata["rows"]:
            d = as_dict(row, tdata["columns"])
            old_id = safe_int(d.get("id"))
            code = safe_str(d.get("position_code"), "")
            name = safe_str(d.get("position_name"), code)

            if name == "Position" or code == "1":
                self.pos_map[old_id] = None
                continue

            pos_data = OrderedDict([
                ("company_id", COMPANY_ID),
                ("branch_id", BRANCH_ID),
                ("position_code", code),
                ("position_name", name),
                ("status", 1),
                ("created_at", now()),
                ("updated_at", now()),
            ])
            new_id = self.db.upsert(
                "positions",
                pos_data,
                unique_cols=["position_code"],
            )
            self.pos_map[old_id] = new_id
        count = len([v for v in self.pos_map.values() if v is not None])
        print(f"  Migrated {count} positions")

    # ─── STEP 4: Time Schedules ───
    def migrate_time_schedules(self):
        print("\n[4/11] Creating time schedules...")

        schedules = [
            {
                "name": "دورية إداري",
                "in_time": "08:00:00",
                "out_time": "15:00:00",
                "alias": "إداري",
            },
            {
                "name": "دورية 1-3",
                "in_time": "08:00:00",
                "out_time": "18:00:00",
                "alias": "1-3 days",
            },
            {
                "name": "دورية 3-9",
                "in_time": "08:00:00",
                "out_time": "18:00:00",
                "alias": "3-9 days",
            },
            {
                "name": "دورية 7-21",
                "in_time": "08:00:00",
                "out_time": "18:00:00",
                "alias": "days 7-21",
            },
        ]

        # Read source time intervals for dynamic data
        ti_table = self.tables.get("att_timeinterval")
        self.ti_by_alias = {}
        if ti_table:
            for row in ti_table["rows"]:
                d = as_dict(row, ti_table["columns"])
                self.ti_by_alias[safe_str(d.get("alias"))] = d

        break_table = self.tables.get("att_breaktime")
        break_data = None
        if break_table and break_table["rows"]:
            break_data = as_dict(break_table["rows"][0], break_table["columns"])

        for sched in schedules:
            alias = sched["alias"]
            ti = self.ti_by_alias.get(alias, {})

            sched_data = OrderedDict([
                ("company_id", COMPANY_ID),
                ("name", sched["name"]),
                ("in_time", sched["in_time"]),
                ("out_time", sched["out_time"]),
                ("is_multi_day", 0),
                ("late_margin", safe_int(ti.get("allow_late"), 0)),
                ("early_margin", safe_int(ti.get("allow_leave_early"), 0)),
                ("created_at", now()),
                ("updated_at", now()),
            ])

            import copy
            sched_copy = copy.deepcopy(sched_data)
            new_id = self.db.upsert(
                "att_time_schedules",
                sched_copy,
                unique_cols=["name", "company_id"],
            )
            self.time_sched_map[sched["name"]] = new_id

        print(f"  Created {len(schedules)} time schedules")

        # Update margin values from source att_timeinterval
        print("  Updating margin values from att_timeinterval...")
        margin_map = {
            "دورية إداري": "إداري",
            "دورية 1-3": "1-3 days",
            "doria_3_9": "3-9 days",
            "دورية 3-9": "3-9 days",
            "دورية 7-21": "days 7-21",
        }
        for sched_name, alias in margin_map.items():
            ts_id = self.time_sched_map.get(sched_name)
            ti = self.ti_by_alias.get(alias)
            if ts_id and ti:
                self.db.execute(
                    """UPDATE att_time_schedules
                       SET in_ahead_margin = %s, in_above_margin = %s,
                           out_ahead_margin = %s, out_above_margin = %s
                       WHERE id = %s""",
                    (
                        safe_int(ti.get("in_ahead_margin"), 0),
                        safe_int(ti.get("in_above_margin"), 0),
                        safe_int(ti.get("out_ahead_margin"), 0),
                        safe_int(ti.get("out_above_margin"), 0),
                        ts_id,
                    ),
                )
                print(f"    {sched_name}: in_ahead={ti.get('in_ahead_margin')}, in_above={ti.get('in_above_margin')}, out_ahead={ti.get('out_ahead_margin')}, out_above={ti.get('out_above_margin')}")

        # Break time
        if break_data:
            print("  Adding break time...")
            # Get the first time schedule (إداري or any)
            sched_id = list(self.time_sched_map.values())[0] if self.time_sched_map else None
            if sched_id:
                break_name = safe_str(break_data.get("alias"), "بصمة ثالثة")
                period_start = safe_time(break_data.get("period_start"))
                duration = safe_int(break_data.get("duration"), 60)
                # Calculate break_end from period_start + duration
                try:
                    parts = period_start.split(":")
                    h = int(parts[0])
                    m = int(parts[1])
                    total_m = h * 60 + m + duration
                    end_h = (total_m // 60) % 24
                    end_m = total_m % 60
                    break_end = f"{end_h:02d}:{end_m:02d}:00"
                except Exception:
                    break_end = "18:00:00"

                self.db.upsert(
                    "att_time_schedule_breaks",
                    OrderedDict([
                        ("schedule_id", sched_id),
                        ("break_start", period_start),
                        ("duration", duration),
                        ("break_end", break_end),
                        ("created_at", now()),
                        ("updated_at", now()),
                    ]),
                    unique_cols=["schedule_id", "break_start"],
                )

    # ─── STEP 5: Rotations and Groups ───
    def migrate_rotations_and_groups(self):
        print("\n[5/11] Creating rotations and groups...")

        rotations_config = [
            {
                "name": "دورية إداري",
                "description": "دوام إداري (5 أيام عمل + 2 راحة)",
                "pattern": [1, 1, 1, 1, 1, 0, 0],
                "cycle_length": 7,
                "work_days_count": 5,
                "rest_days_count": 2,
                "number_of_groups": 1,
                "groups": ["A"],
                "time_schedule": "دورية إداري",
                "color": "#009d00",
            },
            {
                "name": "دورية 1-3",
                "description": "دورية 1-3 (يوم عمل + 3 راحة)",
                "pattern": [1, 0, 0, 0],
                "cycle_length": 4,
                "work_days_count": 1,
                "rest_days_count": 3,
                "number_of_groups": 4,
                "groups": ["A", "B", "C", "D"],
                "time_schedule": "دورية 1-3",
                "color": "#be0000",
            },
            {
                "name": "دورية 3-9",
                "description": "دورية 3-9 (3 أيام عمل + 9 راحة)",
                "pattern": [1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                "cycle_length": 12,
                "work_days_count": 3,
                "rest_days_count": 9,
                "number_of_groups": 4,
                "groups": ["A", "B", "C", "D"],
                "time_schedule": "دورية 3-9",
                "color": "#003ba2",
            },
            {
                "name": "دورية 7-21",
                "description": "دورية 7-21 (7 أيام عمل + 21 راحة)",
                "pattern": [1]*7 + [0]*21,
                "cycle_length": 28,
                "work_days_count": 7,
                "rest_days_count": 21,
                "number_of_groups": 4,
                "groups": ["A", "B", "C", "D"],
                "time_schedule": "دورية 7-21",
                "color": "#cadd1b",
            },
            {
                "name": "دورية غير معروف",
                "description": "موظفون غير مرتبطين بدورية محددة",
                "pattern": [1, 1, 1, 1, 1, 0, 0],
                "cycle_length": 7,
                "work_days_count": 5,
                "rest_days_count": 2,
                "number_of_groups": 1,
                "groups": ["A"],
                "time_schedule": "دورية إداري",
                "color": "#888888",
            },
        ]

        for rc in rotations_config:
            ts_id = self.time_sched_map.get(rc["time_schedule"])
            # Get margin values from source att_timeinterval
            alias_map = {
                "دورية إداري": "إداري",
                "دورية 1-3": "1-3 days",
                "دورية 3-9": "3-9 days",
                "دورية 7-21": "days 7-21",
                "دورية غير معروف": "إداري",
            }
            ti = self.ti_by_alias.get(alias_map.get(rc["name"], ""), {})
            in_ahead_min = safe_int(ti.get("in_ahead_margin"), 0)
            in_above_min = safe_int(ti.get("in_above_margin"), 0)
            out_ahead_min = safe_int(ti.get("out_ahead_margin"), 0)
            out_above_min = safe_int(ti.get("out_above_margin"), 0)

            # Get time schedule in/out times to convert margins to actual times
            ts_name = rc["time_schedule"]
            ts_data = {
                "دورية إداري": ("08:00:00", "15:00:00"),
                "دورية 1-3": ("08:00:00", "18:00:00"),
                "دورية 3-9": ("08:00:00", "18:00:00"),
                "دورية 7-21": ("08:00:00", "18:00:00"),
                "دورية غير معروف": ("08:00:00", "15:00:00"),
            }
            in_time_str, out_time_str = ts_data.get(ts_name, ("08:00:00", "17:00:00"))
            in_h, in_m = int(in_time_str[:2]), int(in_time_str[3:5])
            out_h, out_m = int(out_time_str[:2]), int(out_time_str[3:5])

            def mins_to_time(h, m, offset):
                total = h * 60 + m + offset
                if total < 0: total = 0
                hh = (total // 60) % 24
                mm = total % 60
                return f"{hh:02d}:{mm:02d}:00"

            in_ahead = mins_to_time(in_h, in_m, -in_ahead_min)
            in_above = mins_to_time(in_h, in_m, in_above_min)
            out_ahead = mins_to_time(out_h, out_m, -out_ahead_min)
            out_above = mins_to_time(out_h, out_m, out_above_min)

            rotation_data = OrderedDict([
                ("company_id", COMPANY_ID),
                ("name", rc["name"]),
                ("description", rc["description"]),
                ("anchor_start_date", ASSIGNMENT_START_DATE),
                ("pattern", json.dumps(rc["pattern"], ensure_ascii=False)),
                ("cycle_length", rc["cycle_length"]),
                ("work_days_count", rc["work_days_count"]),
                ("rest_days_count", rc["rest_days_count"]),
                ("number_of_groups", rc["number_of_groups"]),
                ("overtime_enabled", 0),
                ("work_on_holidays", 0),
                ("grace_minutes", 0),
                ("color", rc["color"]),
                ("in_ahead_margin", in_ahead),
                ("in_above_margin", in_above),
                ("out_ahead_margin", out_ahead),
                ("out_above_margin", out_above),
                ("created_at", now()),
                ("updated_at", now()),
            ])

            rot_id = self.db.upsert(
                "att_rotations",
                rotation_data,
                unique_cols=["name", "company_id"],
            )
            self.rotation_map[rc["name"]] = rot_id

            # Create groups
            for idx, gname in enumerate(rc["groups"]):
                # Calculate group start_date: anchor + offset by group_index
                from datetime import datetime, timedelta
                anchor = datetime.strptime(ASSIGNMENT_START_DATE, "%Y-%m-%d")
                group_start = (anchor + timedelta(days=idx)).strftime("%Y-%m-%d")

                group_data = OrderedDict([
                    ("rotation_id", rot_id),
                    ("name", gname),
                    ("group_index", idx),
                    ("time_schedule_id", ts_id),
                    ("start_date", group_start),
                    ("created_at", now()),
                    ("updated_at", now()),
                ])
                gid = self.db.upsert(
                    "att_rotation_groups",
                    group_data,
                    unique_cols=["rotation_id", "name"],
                )
                self.rot_group_map[(rc["name"], gname)] = gid

        print(f"  Created {len(rotations_config)} rotations with {sum(len(rc['groups']) for rc in rotations_config)} groups")

    # ─── STEP 6: Users ───
    def migrate_users(self):
        print("\n[6/11] Migrating employees to users...")
        tdata = self.tables.get("personnel_employee")
        if not tdata:
            print("  No personnel_employee data, skipping.")
            return

        # Read employment data for employment_type and hire_date
        emp_table = self.tables.get("personnel_employment")
        emp_by_employee = {}
        if emp_table:
            for row in emp_table["rows"]:
                d = as_dict(row, emp_table["columns"])
                eid = safe_int(d.get("employee_id"))
                if eid not in emp_by_employee:
                    emp_by_employee[eid] = d

        count = 0
        for row in tdata["rows"]:
            d = as_dict(row, tdata["columns"])
            old_id = safe_int(d.get("id"))
            emp_code = safe_str(d.get("emp_code"), "")
            first_name = safe_str(d.get("first_name"), "")
            last_name = safe_str(d.get("last_name"), "")
            full_name = f"{first_name} {last_name}".strip()
            if not full_name:
                full_name = emp_code

            mobile = safe_str(d.get("mobile"), "")
            phone = safe_str(d.get("contact_tel"), "") or safe_str(d.get("office_tel"), "")
            address = safe_str(d.get("address"))
            national_id = safe_str(d.get("ssn"))
            gender_val = safe_str(d.get("gender"), "").upper()
            gender = "male" if gender_val == "M" else ("female" if gender_val == "F" else None)
            is_active = safe_bool(d.get("is_active"), True)
            emp_status = safe_int(d.get("status"), 0)
            user_status = 1 if (is_active and emp_status != 100) else 0
            email_val = safe_str(d.get("email"))
            if not email_val or email_val == "":
                email_val = f"{emp_code}@{EMAIL_DOMAIN}"

            hire_date = None
            hire_date_raw = safe_str(d.get("hire_date"))
            emp_rec = emp_by_employee.get(old_id, {})
            if emp_rec:
                hire_date = safe_date(emp_rec.get("start_date")) or safe_date(d.get("hire_date"))
            else:
                hire_date = safe_date(d.get("hire_date"))

            dept_old = safe_int(d.get("department_id"))
            pos_old = safe_int(d.get("position_id"))
            new_dept_id = self.dept_map.get(dept_old)
            new_pos_id = self.pos_map.get(pos_old)

            user_data = OrderedDict([
                ("employee_code", emp_code),
                ("name", full_name),
                ("first_name", first_name),
                ("last_name", last_name),
                ("email", email_val),
                ("password", ""),
                ("phone", phone or mobile or ""),
                ("national_id", national_id),
                ("gender", gender),
                ("address", address),
                ("hire_date", hire_date),
                ("employment_type", "full_time"),
                ("status", user_status),
                ("is_active_employee", 1 if user_status == 1 else 0),
                ("must_change_password", 0),
                ("failed_login_attempts", 0),
                ("company_id", COMPANY_ID),
                ("branch_id", BRANCH_ID),
                ("department_id", new_dept_id),
                ("position_id", new_pos_id),
                ("subordination_id", self.subordination_id),
            ])

            # Check if employee already exists by employee_code
            existing = self.db.select_one(
                "SELECT id FROM users WHERE employee_code = %s", (emp_code,)
            )
            if existing:
                new_user_id = existing["id"]
                # Update
                set_parts = []
                set_vals = []
                for k, v in user_data.items():
                    if k != "employee_code":
                        set_parts.append(f"`{k}` = %s")
                        set_vals.append(v)
                set_parts.append("`updated_at` = %s")
                set_vals.append(now())
                self.db.execute(
                    f"UPDATE users SET {', '.join(set_parts)} WHERE employee_code = %s",
                    set_vals + [emp_code],
                )
            else:
                user_data["created_at"] = now()
                user_data["updated_at"] = now()
                new_user_id = self.db.insert("users", user_data)

            self.user_map[old_id] = new_user_id
            count += 1

        print(f"  Migrated {count} employees")

    # ─── STEP 7: Rotation Assignments ───
    def migrate_rotation_assignments(self):
        print("\n[7/11] Creating rotation assignments...")

        # Build group_id → target mapping from source data
        # Source: att_attgroup → att_groupschedule → att_attshift
        # We map to: rotation + rotation_group

        # First, build the mapping from source group names to target rotations
        group_table = self.tables.get("att_attgroup")
        groupschedule_table = self.tables.get("att_groupschedule")

        old_group_name_to_id = {}
        old_group_id_to_name = {}
        if group_table:
            for row in group_table["rows"]:
                d = as_dict(row, group_table["columns"])
                gid = safe_int(d.get("id"))
                gname = safe_str(d.get("name"), "")
                old_group_name_to_id[gname] = gid
                old_group_id_to_name[gid] = gname

        # source group_name → (target rotation name, target group letter)
        group_name_to_target = {
            "إداري": ("دورية إداري", "A"),
            "الفئة الأولى 1 - 3": ("دورية 1-3", "A"),
            "الفئة الثانية 1 - 3": ("دورية 1-3", "B"),
            "الفئة الثالثة 1 - 3": ("دورية 1-3", "C"),
            "الفئة الرابعة 1 - 3": ("دورية 1-3", "D"),
            "الفئة الأولى 3 - 9": ("دورية 3-9", "A"),
            "الفئة الثانية 3 - 9": ("دورية 3-9", "B"),
            "الفئة الثالثة 3 - 9": ("دورية 3-9", "C"),
            "الفئة الرابعة 3 - 9": ("دورية 3-9", "D"),
            "الفئة الأولى 7- 21": ("دورية 7-21", "A"),
            "الفئة الثانبة 7- 21": ("دورية 7-21", "B"),
            "الفئة الثالثة 7- 21": ("دورية 7-21", "C"),
            "الفئة الرابعة 7- 21": ("دورية 7-21", "D"),
        }

        # Build old_group_id → (rotation_id, rotation_group_id)
        for old_gname, target in group_name_to_target.items():
            old_gid = old_group_name_to_id.get(old_gname)
            if old_gid:
                rot_name, group_letter = target
                rot_id = self.rotation_map.get(rot_name)
                rg_id = self.rot_group_map.get((rot_name, group_letter))
                if rot_id and rg_id:
                    self.group_to_target[old_gid] = (rot_id, rg_id)

        # Now read att_attemployee to assign employees
        att_emp_table = self.tables.get("att_attemployee")
        if not att_emp_table:
            print("  No att_attemployee data, skipping.")
            return

        count_assigned = 0
        unknown_rot_id = self.rotation_map.get("دورية غير معروف")
        unknown_rg_id = self.rot_group_map.get(("دورية غير معروف", "A"))

        for row in att_emp_table["rows"]:
            d = as_dict(row, att_emp_table["columns"])
            old_emp_id = safe_int(d.get("emp_id"))
            old_group_id = safe_int(d.get("group_id"))
            enable_att = safe_bool(d.get("enable_attendance"), True)

            new_user_id = self.user_map.get(old_emp_id)
            if not new_user_id:
                continue

            if old_group_id and old_group_id in self.group_to_target:
                rot_id, rg_id = self.group_to_target[old_group_id]
            else:
                # Send to "غير معروف"
                if not unknown_rot_id or not unknown_rg_id:
                    continue
                rot_id = unknown_rot_id
                rg_id = unknown_rg_id

            # Check existing assignment (don't duplicate)
            existing_assign = self.db.select_one(
                """SELECT id FROM att_rotation_assignments
                   WHERE employee_id = %s AND rotation_id = %s AND rotation_group_id = %s
                   AND start_date = %s AND end_date IS NULL""",
                (new_user_id, rot_id, rg_id, ASSIGNMENT_START_DATE),
            )
            if existing_assign:
                continue

            # Deactivate old assignments for this employee (set end_date)
            self.db.execute(
                """UPDATE att_rotation_assignments SET end_date = %s
                   WHERE employee_id = %s AND end_date IS NULL""",
                (ASSIGNMENT_START_DATE, new_user_id),
            )

            assign_data = OrderedDict([
                ("employee_id", new_user_id),
                ("rotation_id", rot_id),
                ("rotation_group_id", rg_id),
                ("start_date", ASSIGNMENT_START_DATE),
                ("end_date", None),
                ("created_at", now()),
                ("updated_at", now()),
            ])
            self.db.insert("att_rotation_assignments", assign_data)
            count_assigned += 1

        print(f"  Created {count_assigned} rotation assignments")

    # ─── STEP 8: Vacation Types ───
    def migrate_vacation_types(self):
        print("\n[8/11] Creating vacation types...")
        paycode_table = self.tables.get("att_paycode")
        if not paycode_table:
            print("  No att_paycode data, skipping.")
            return

        # Only code_type = 3 (leave codes)
        vacation_type_map = {
            "AL": ("إجازة إدارية", "Administrative Leave", "#4CAF50", True),
            "SL": ("إجازة مرضية", "Sick Leave", "#F44336", True),
            "CL": ("إجازة عرضية", "Casual Leave", "#2196F3", True),
            "ML": ("إجازة أمومة", "Maternity Leave", "#E91E63", True),
            "COL": ("إجازة إنسانية", "Compassionate Leave", "#9C27B0", True),
            "BT": ("مهمة سفر", "Business Travel", "#FF9800", True),
            "CPL": ("إجازة تعويضية", "Compensatory Leave", "#00BCD4", True),
            "MB": ("مبادلة", "Swap Leave", "#795548", True),
        }

        self.vacation_type_code_map = {}
        count = 0
        sort = 0
        for row in paycode_table["rows"]:
            d = as_dict(row, paycode_table["columns"])
            code_type = safe_int(d.get("code_type"))
            paycode_code = safe_str(d.get("code"))
            paycode_name = safe_str(d.get("name"))

            if code_type != 3:
                continue

            if paycode_code in vacation_type_map:
                name_ar, name_en, color, is_paid = vacation_type_map[paycode_code]
            else:
                name_ar = paycode_name or paycode_code
                name_en = paycode_code
                color = "#607D8B"
                is_paid = safe_bool(d.get("is_paid"), True)

            sort += 1
            vt_data = OrderedDict([
                ("code", paycode_code),
                ("name_ar", name_ar),
                ("name_en", name_en),
                ("color", color),
                ("is_paid", 1 if is_paid else 0),
                ("requires_approval", 1),
                ("deducts_from_balance", 1),
                ("is_active", 1),
                ("sort_order", sort),
                ("created_at", now()),
                ("updated_at", now()),
            ])

            vt_id = self.db.upsert(
                "vacation_types",
                vt_data,
                unique_cols=["code"],
            )
            self.vacation_type_code_map[paycode_code] = vt_id
            count += 1

        print(f"  Created {count} vacation types")

    # ─── STEP 9: Vacation Requests ───
    def migrate_vacation_requests(self):
        print("\n[9/11] Migrating leave requests...")
        leave_table = self.tables.get("att_leave")
        wf_table = self.tables.get("workflow_workflowinstance")

        if not leave_table or not wf_table:
            print("  No leave/workflow data, skipping.")
            return

        # Build workflow instance → employee mapping
        wf_map = {}
        for row in wf_table["rows"]:
            d = as_dict(row, wf_table["columns"])
            wf_id = safe_int(d.get("id"))
            emp_id = safe_int(d.get("employee_id"))
            wf_map[wf_id] = emp_id

        count = 0
        for row in leave_table["rows"]:
            d = as_dict(row, leave_table["columns"])
            wf_id = safe_int(d.get("workflowinstance_ptr_id"))
            paycode = safe_str(d.get("pay_code_id"))
            leave_days = safe_float(d.get("leave_day"), 0)
            start_time_raw = safe_str(d.get("start_time"))
            end_time_raw = safe_str(d.get("end_time"))
            apply_reason = safe_str(d.get("apply_reason"), "")

            old_emp_id = wf_map.get(wf_id)
            if not old_emp_id:
                continue
            new_user_id = self.user_map.get(old_emp_id)
            if not new_user_id:
                continue

            # Find paycode name from paycode table
            paycode_name = None
            paycode_table = self.tables.get("att_paycode")
            if paycode_table:
                for prow in paycode_table["rows"]:
                    pd = as_dict(prow, paycode_table["columns"])
                    if str(safe_int(pd.get("id"))) == str(paycode):
                        paycode_name = safe_str(pd.get("code"))
                        break

            if not paycode_name:
                paycode_name = "AL"

            vt_id = self.vacation_type_code_map.get(paycode_name)
            if not vt_id:
                continue

            # Parse dates
            start_date = None
            end_date = None
            if start_time_raw:
                try:
                    if "+" in start_time_raw:
                        start_time_raw = start_time_raw.split("+")[0].strip()
                    start_dt = datetime.strptime(start_time_raw[:19], "%Y-%m-%d %H:%M:%S")
                    start_date = start_dt.strftime("%Y-%m-%d")
                except Exception:
                    continue
            if end_time_raw:
                try:
                    if "+" in end_time_raw:
                        end_time_raw = end_time_raw.split("+")[0].strip()
                    end_dt = datetime.strptime(end_time_raw[:19], "%Y-%m-%d %H:%M:%S")
                    end_date = end_dt.strftime("%Y-%m-%d")
                except Exception:
                    end_date = start_date

            if not start_date or not end_date:
                continue

            # Compute days_count
            days_count = int(leave_days) if leave_days > 0 else 1

            # Check if already exists (by user + start_date + end_date + vacation_type)
            existing = self.db.select_one(
                """SELECT id FROM user_vacation_requests
                   WHERE user_id = %s AND vacation_type_id = %s
                   AND start_date = %s AND end_date = %s""",
                (new_user_id, vt_id, start_date, end_date),
            )
            if existing:
                continue

            vr_data = OrderedDict([
                ("user_id", new_user_id),
                ("vacation_type_id", vt_id),
                ("start_date", start_date),
                ("end_date", end_date),
                ("days_count", days_count),
                ("status", "approved"),
                ("reason", apply_reason or ""),
                ("decided_at", now()),
                ("created_at", now()),
                ("updated_at", now()),
            ])

            self.db.insert("user_vacation_requests", vr_data)
            count += 1

        print(f"  Migrated {count} leave requests")


# ──────────────────── MAIN ────────────────────

def main():
    print("=" * 60)
    print("  HRM Database Migration Tool")
    print("  PostgreSQL → MySQL (Laravel HRM)")
    print("=" * 60)
    print(f"  Source: {DUMP_FILE}")
    print(f"  Target: {DB_CONFIG['user']}@{DB_CONFIG['host']}/{DB_CONFIG['database']}")
    print(f"  Company ID: {COMPANY_ID}, Branch ID: {BRANCH_ID}")
    print("=" * 60)

    migrator = Migrator()
    migrator.run()


if __name__ == "__main__":
    main()
