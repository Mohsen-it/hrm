# HRM — Deploy on a new Windows machine (011/SERVER)

Goal: the new machine boots the full stack by itself — no CMD, no manual steps.

## 0. What "server" means here (proven on the current machine)

| Layer | What | Autostart |
|---|---|---|
| MySQL, Nginx, PHP-CGI pool, queue worker | NSSM Windows services (`HRM-*`, Automatic) | At boot, no logon |
| Laravel, Reverb, ADMS, bridge, scheduler, queue workers | Supervisor `scripts\Start-HRM-Windows.ps1` via `HRM-AutoStart.vbs` in the user Startup folder | At logon, hidden window |
| Queue-worker safety net | Task `HRM Queue Worker Watchdog` (every 5 min) | Always |

Copy this project directory to the new machine (same path recommended,
e.g. `D:\hrm`), then follow the steps below IN ORDER.

## 1. Prerequisites (install once)

- Windows 10/11 Pro, PHP 8.3+ (Laragon full), Composer, Node.js 20 LTS,
  Python 3.11+ (with `py` launcher), MySQL 8.x, Git.

Audit the machine first (changes nothing):

```bat
powershell -ExecutionPolicy Bypass -File production\Setup-NewMachine.ps1 -CheckOnly
```

## 2. Install (step-by-step, each step self-verifies)

```bat
powershell -ExecutionPolicy Bypass -File production\Setup-NewMachine.ps1
```

This runs: `composer install` → `npm install` → `npm run build` →
venv + `requirements.txt` (via `python -m pip`, never `pip.exe`) →
`.env` from template (stops and asks you to fill it) →
`key:generate` → `migrate --force` → logon autostart (VBS).

## 3. Configure `.env`

Copy `production\.env.production.example` → `.env` and fill:
`APP_URL`, `DB_*`, `REVERB_*`, `VITE_REVERB_HOST` (server LAN IP),
mail settings. The supervisor auto-detects the LAN IP for display;
pass `-ServerIp x.x.x.x` only to override it.

## 4. Production services (no-logon operation)

For a TRUE standalone server (works after power loss with nobody logging
on), register the NSSM layer as on the current machine
(see `C:\nssm\install-hrm-production.ps1` for the exact commands):
`HRM-MySQL`, `HRM-Nginx`, `HRM-PHP-Pool`, `HRM-Queue` (all Automatic).
The VBS logon starter remains as the second layer for the artisan stack.

## 5. Verify after first reboot (nobody touches anything)

- `netstat -ano | findstr "LISTENING"` shows 8000 / 8080 / 8081 / 5000.
- `http://SERVER-IP/login` returns 200.
- `storage\logs\hrm-startup.log` has a fresh `Starting...` line
  (only when the SYSTEM/task path is used).
- No console window appears on screen.

## 6. Files in this folder

| File | Purpose |
|---|---|
| `start-production.ps1` | Headless wrapper: runs the supervisor IN-PROCESS (task-stop kills the stack cleanly, never orphans) |
| `HRM-Startup.xml` | DISABLED BY DEFAULT — one-shot boot task kept as documented fallback only (it conflicts with the VBS supervisor; enable only after removing the VBS layer) |
| `HRM-Startup-backup.xml` | Original task definition before our changes |
| `Register-Headless-Startup.bat` | Run-as-admin helper that registers the XML above |
| `Setup-NewMachine.ps1` | New-machine installer (`-CheckOnly` audits, full run installs) |
| `.env.production.example` | Complete production env template (values blanked) |
| `DEPLOY-NEW-MACHINE.md` | This file |

## 7. Long-term rules (why this stays healthy)

- **ASCII-only in `.ps1` files.** A non-ASCII char (e.g. em-dash) inside a
  `"double-quoted"` PowerShell string breaks parsing on Windows PowerShell
  5.1 with ANSI locale — the script dies instantly with exit code 1 and NO
  log line. This exact bug caused the `HRM-Startup` exit-1 mystery on
  2026-09-10 (a dash in a log message). Comments are unaffected, but keep
  the whole file ASCII anyway. Verify with:
  `ParseFile` (the legacy `PSParser::Tokenize` does NOT catch it).

- Logs: supervisor discards `artisan serve` stdout, keeps stderr; oversized
  logs (≥50MB) are archived at boot; stdout archives older than 14 days
  are deleted at boot. Never add unbounded `>> log` redirection.
- Python: always `python -m pip`, never `pip.exe` (breaks on Windows).
- One supervisor only: never run the `.bat` AND the task AND the VBS at
  the same time — duplicate supervisors fight over the same ports.
