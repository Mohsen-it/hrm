# 6. ADMS Integration Guide

## What is ADMS?

ADMS (Auto Data Master Server) is ZKTeco's proprietary push protocol. ZKTeco devices can be configured to **push** attendance records to a server URL in real-time rather than waiting for the server to pull data. This enables near-instant attendance recording.

## How It Works

```
┌─────────────────┐     POST /api/attendance-integration/push/adms     ┌──────────────────┐
│  ZKTeco Device  │ ──────────────────────────────────────────────────→ │  HRM Server       │
│  (ADMS Client)  │                                                    │  (ADMS Receiver)  │
│                 │ ←────────────────────────────────────────────────── │                   │
│                 │     { success: true, received: 5, processed: 5 }   │                   │
└─────────────────┘                                                    └──────────────────┘
```

## Device Configuration

### On the ZKTeco Device

1. Navigate to **COMM.** → **ADMS** or **Network** → **ADMS Settings**
2. Set **Enable ADMS** = **Yes**
3. Set **Server URL** = `http://{your-server}/api/attendance-integration/push/adms`
4. Set **Server Port** = `80` (or your web server port)
5. Set **Push Interval** = recommended: `1` (pushes immediately after each punch)
6. Set **Enable Attendance Push** = **Yes**

### Device Token (Recommended)

1. Generate a token: `php artisan tinker --execute="echo bcrypt('your-device-token');"`
2. Store the hashed token in the database:
   ```sql
   UPDATE fingerprint_devices SET api_token = '$2y$12$...' WHERE serial_number = 'ZK_MAIN_001';
   ```
3. Configure the device to send the token (varies by firmware):
   - Some devices support custom HTTP headers
   - Alternative: add `api_token` parameter to the server URL

## ADMS Payload Format

### Format 1: Tab-Separated Text (Most Common)

```
ATT\t\t{user_id}\t{timestamp}\t{status}\n
```

Example:
```
ATT		1001	2026-07-15 08:00:00	0
ATT		1001	2026-07-15 17:00:00	1
```

- `user_id` — Employee ID stored on the device (maps to `employees.employee_code`)
- `timestamp` — `YYYY-MM-DD HH:MM:SS` format
- `status` — ZKTeco status code:
  - `0` = Check-in (fingerprint)
  - `1` = Check-out (fingerprint)
  - `2` = Break-out
  - `3` = Break-in
  - `4` = Overtime-in

### Format 2: JSON Array

```json
{
    "SN": "ZK_MAIN_001",
    "attendance": [
        {"user_id": "EMP001", "timestamp": "2026-07-15 08:00:00", "status": 0}
    ]
}
```

### Format 3: Single Row JSON

```json
{
    "SN": "ZK_MAIN_001",
    "user_id": "EMP001",
    "timestamp": "2026-07-15 08:00:00",
    "punch_type": "check_in",
    "status": 0
}
```

## HTTP Method Handling

The ADMS endpoint accepts both `GET` and `POST` requests. Some ZKTeco firmware versions use GET with query parameters instead of POST with a body. The `ZKTecoAdmsParser` handles both cases.

## Troubleshooting ADMS

### Device not pushing

1. Verify ADMS is enabled on the device
2. Check the server URL is reachable from the device's network
3. Check firewall rules allow HTTP traffic from device IP
4. Verify the device serial number exists in `fingerprint_devices` table
5. Check `attendance_push` log channel for incoming requests

### Duplicate records

The duplicate detection window is 30 seconds (configurable). If the device pushes the same punch multiple times within this window, only the first is processed.

### User not matched

The system matches `device_user_id` against `users.employee_code`. Ensure:
1. Employee codes in the HRM system match the user IDs stored on the device
2. The `employee_code` field is populated for all employees
