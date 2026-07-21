#!/usr/bin/env python3
"""
Activate all employees in the HRM database.

This script sets:
  - status = 1
  - is_active_employee = true
  - termination_date = NULL

It reads database credentials from the Laravel .env file.

Usage:
  python scripts/activate_all_employees.py         # dry-run preview
  python scripts/activate_all_employees.py --apply # actually update
"""

from __future__ import annotations

import argparse
import os
import re
import sys
from pathlib import Path
from typing import Dict


def parse_env(env_path: Path) -> Dict[str, str]:
    """Read key=value pairs from a Laravel .env file."""
    values: Dict[str, str] = {}
    if not env_path.exists():
        return values

    for line in env_path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        match = re.match(r'^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$', line)
        if match:
            key, value = match.groups()
            value = value.strip().strip('"').strip("'")
            values[key] = value
    return values


def get_connection(env: Dict[str, str]):
    """Create a database connection from parsed .env values."""
    driver = env.get("DB_CONNECTION", "mysql")
    host = env.get("DB_HOST", "127.0.0.1")
    port = int(env.get("DB_PORT", "3306"))
    database = env.get("DB_DATABASE", "")
    user = env.get("DB_USERNAME", "")
    password = env.get("DB_PASSWORD", "")

    if driver == "mysql":
        try:
            import pymysql
        except ImportError:
            print("Error: pymysql is required for MySQL. Install it with: pip install pymysql")
            sys.exit(1)
        return pymysql.connect(
            host=host,
            port=port,
            user=user,
            password=password,
            database=database,
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
        )

    if driver == "pgsql":
        try:
            import psycopg2
        except ImportError:
            print("Error: psycopg2 is required for PostgreSQL. Install it with: pip install psycopg2-binary")
            sys.exit(1)
        return psycopg2.connect(
            host=host,
            port=port,
            user=user,
            password=password,
            dbname=database,
        )

    if driver == "sqlite":
        try:
            import sqlite3
        except ImportError:
            print("Error: sqlite3 is required for SQLite.")
            sys.exit(1)
        db_file = database or env.get("DB_DATABASE", "database/database.sqlite")
        return sqlite3.connect(db_file)

    print(f"Error: unsupported database driver '{driver}'")
    sys.exit(1)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Activate all employees in the HRM database."
    )
    parser.add_argument(
        "--env",
        type=Path,
        default=Path(".env"),
        help="Path to the Laravel .env file (default: .env)",
    )
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Actually perform the update. Without this flag the script only previews affected rows.",
    )
    args = parser.parse_args()

    env = parse_env(args.env)
    connection = get_connection(env)

    try:
        with connection.cursor() as cursor:
            cursor.execute(
                """
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN status = 1 AND is_active_employee = 1 AND termination_date IS NULL THEN 1 ELSE 0 END) as already_active
                FROM users
                """
            )
            row = cursor.fetchone()
            total = row["total"] if isinstance(row, dict) else row[0]
            already_active = row["already_active"] if isinstance(row, dict) else row[1]

            cursor.execute(
                """
                SELECT id, employee_code, name, status, is_active_employee, termination_date
                FROM users
                WHERE status != 1 OR is_active_employee != 1 OR termination_date IS NOT NULL
                LIMIT 50
                """
            )
            to_activate = cursor.fetchall()
            to_activate_count = len(to_activate)

            print(f"Total users in database: {total}")
            print(f"Already active:          {already_active}")
            print(f"To activate:             {to_activate_count}")

            if to_activate_count > 0:
                print("\nSample of users that will be activated (showing up to 50):")
                print(f"{'ID':<8} {'Code':<15} {'Name':<30} {'Status':<8} {'Active':<8} {'Termination':<12}")
                print("-" * 85)
                for user in to_activate:
                    if isinstance(user, dict):
                        print(
                            f"{user['id']:<8} {user['employee_code'] or '':<15} "
                            f"{user['name'] or '':<30} {user['status']:<8} "
                            f"{user['is_active_employee']:<8} {user['termination_date'] or '':<12}"
                        )
                    else:
                        print(" ".join(str(c) for c in user))

            if not args.apply:
                print("\nDry-run mode: no changes were made.")
                print("Run again with --apply to activate all employees.")
                return 0

            cursor.execute(
                """
                UPDATE users
                SET status = 1,
                    is_active_employee = 1,
                    termination_date = NULL,
                    updated_at = NOW()
                WHERE status != 1 OR is_active_employee != 1 OR termination_date IS NOT NULL
                """
            )
            connection.commit()
            print(f"\nActivated {cursor.rowcount} employee(s) successfully.")
    finally:
        connection.close()

    return 0


if __name__ == "__main__":
    sys.exit(main())
