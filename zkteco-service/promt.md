# Role: Senior Enterprise System Architect & Integration Engineer
# Objective: Refactor a ZKTeco Biometric System to a 100% ADMS (Push Protocol) Architecture.

## Context
We are migrating our current ZKTeco integration from a mixed architecture (ADMS for attendance + `pyzk` TCP/IP for user push) to a **Pure ADMS Architecture**. 
The system consists of:
1. **Laravel (Main Backend):** Master data management, reporting, and UI.
2. **Python Middleware (ADMS Server):** Interacts with ZKTeco 880 Plus devices via HTTP Push Protocol.
3. **ZKTeco 880 Plus Devices:** Identical devices on the same local network, running the exact same ZKFace algorithm.

## Workflow Requirements
The system must support the following exact lifecycle without any direct TCP/IP connections (`pyzk` is strictly forbidden):
1. **Employee CRUD Lifecycle (Laravel to Devices):** 
   - **Create/Update:** Modifying or adding a user in Laravel must queue a `DATA UPDATE USER` command.
   - **Delete:** Deleting a user in Laravel must queue a `DATA DELETE USER` command for all devices.
2. **Biometric Enrollment:** Employee registers Face/Fingerprint on *Device A*. Device A pushes this `BIODATA` to the Python ADMS server via HTTP POST.
3. **Biometric Synchronization:** Python Server detects new `BIODATA`, saves it, and immediately queues a command to push this exact template to *all other devices*.
4. **Attendance Logging:** Real-time and offline attendance logs pushed from devices must be parsed and saved to the database.

## 1. Database Architecture (MySQL/PostgreSQL)
Design the necessary Laravel Migrations for:
- `employees`: id, pin, name, privilege.
- `biometrics`: id, employee_pin, bio_type (1=Finger, 9=Face), major_ver, minor_ver, template_data (Base64 string).
- `device_commands`: id, device_sn, command_string, status (pending, executed, failed), created_at.
- `attendance_logs`: id, employee_pin, punch_time, punch_type, device_sn.

## 2. API Endpoints Implementation (Python - Flask or FastAPI)
Write the robust Python logic for the standard ZKTeco ADMS endpoints. Ensure strict adherence to the ZKTeco plain-text formatting.

### A. `POST /iclock/cdata` (Data Receiver)
Handle incoming data from devices. Parse the raw text body:
- **If Attendance Log (`table=ATTLOG`):** Extract PIN, timestamp, and status. Save to `attendance_logs`.
- **If Biometric Data (`table=BIODATA`):** Extract `PIN`, `Type` (1 or 9), `MajorVer`, `MinorVer`, and `Tmp` (Base64). 
  - Save to `biometrics` table. (Crucial: DO NOT decode the Base64 template, save it exactly as received).
  - Automatically generate and inject a `DATA UPDATE BIODATA` command into the `device_commands` table for *all other registered devices* except the one that sent it.

### B. `GET /iclock/getrequest` (Command Dispatcher)
- When a device polls this endpoint (using its `SN` in the query params), query the `device_commands` table for any `status = 'pending'` commands for this specific `SN`.
- Format the response strictly as ZKTeco commands, ensuring precise syntax:
  `C:123:DATA UPDATE USER PIN=1001\tName=Ahmad\tPri=0\tGrp=1\tTZ=1`
  `C:124:DATA UPDATE BIODATA PIN=1001\tNo=0\tType=9\tMajorVer=10\tMinorVer=0\tValid=1\tTmp=[Base64_String]`
  `C:125:DATA DELETE USER PIN=1001`
- Return multiple commands if available, separated by `\n`.
- Update the served commands' status to `dispatched` or `processing`.

### C. `POST /iclock/devicecmd` (Command Acknowledgment)
- Devices hit this endpoint to confirm command execution.
- Parse the incoming text for the `id` (e.g., `123` from `id=123`) and `Return` code.
- If `Return=0`, update the `device_commands` table status to `executed`. Otherwise, mark as `failed`.

## 3. Strict Engineering Constraints & Rules
- **Idempotency (Crucial):** Sync logic must prevent infinite loops. If Device A sends a face template, the server saves it and sends it to Device B. When Device B acknowledges the save, it must NOT trigger a new `POST /iclock/cdata` back to the server. 
- **Template Integrity:** Face templates (Type 9) and Finger templates (Type 1) must remain untouched in their Base64 format. No encoding/decoding should occur in Python.
- **Response Formatting:** Ensure HTTP responses to the devices explicitly use `Content-Type: text/plain` and the correct line endings (`\n`).

## Task Output Request
Based on the above spec, provide:
1. The complete Laravel Migration files for the required tables.
2. The exact Python code (FastAPI/Flask) implementing the 3 endpoints with robust regex/text-parsing logic for ZKTeco ADMS raw formats.
3. The Laravel helper classes or Jobs that handle the Employee lifecycle, specifically:
   - Job to insert `DATA UPDATE USER` into `device_commands` (triggered on Create or Update).
   - Job to insert `DATA DELETE USER` into `device_commands` (triggered on Delete).