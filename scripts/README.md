# Windows HRM launcher

Run `Start-HRM-Windows.bat` from a normal Command Prompt to start the HRM
stack in a controlled Windows installation:

```bat
scripts\Start-HRM-Windows.bat
```

It starts one instance of each required service and keeps it restarted if it
exits:

| Service | Port | Purpose |
|---|---:|---|
| Laravel | 8000 | HRM HTTP application |
| Queue worker | — | queued broadcasts and attendance processing |
| Reverb | 8080 | real-time WebSocket events |
| ADMS server | 8081 | ZKTeco device listener |

The script checks `.env`, Composer dependencies, Node dependencies, Python
venv, database connectivity, and that the ports are free. It also builds the
frontend assets. Use `--skip-build` only when assets have already been built.

`adms_server.py` is the listener for attendance devices, and `app.py` is the
ZKTeco bridge used by fingerprint operations. Both are started by default:

```bat
scripts\Start-HRM-Windows.bat
```

Use `--no-bridge` only when the bridge is not needed.

Do not run `composer run dev`, `php artisan queue:listen`, another
`queue:work`, or another Reverb process while the launcher is running.

When started by double-click, the launcher keeps its window open after a
successful start so the status remains visible. Set `HRM_NO_PAUSE=1` before
invoking it from automation to return immediately instead.

For a true unattended production host, install the four commands as Windows
services through NSSM or Task Scheduler (configured with automatic restart).
This launcher is intended for a Windows server/controlled local deployment;
Laravel's `artisan serve` should be replaced by IIS or Nginx + PHP-FPM for an
Internet-facing production server.
