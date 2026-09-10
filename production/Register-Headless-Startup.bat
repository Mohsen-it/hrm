@echo off
setlocal EnableExtensions
rem 011/SERVER — one-time elevated registration of headless autostart.
rem Run this file AS ADMINISTRATOR (right-click > Run as administrator).
rem It replaces the logon task with: At Startup + SYSTEM + hidden +
rem restart-on-failure. The previous task definition is backed up at
rem production\HRM-Startup-backup.xml before replacement.

net session >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Please right-click and choose "Run as administrator".
    pause
    exit /b 1
)

schtasks /Create /TN "HRM-Startup" /XML "%~dp0HRM-Startup.xml" /F
if errorlevel 1 (
    echo [ERROR] Task registration failed. See message above.
    pause
    exit /b 1
)

echo.
echo [OK] HRM-Startup is now headless: At Startup, SYSTEM, hidden, restart-on-failure.
echo      Verify: schtasks /query /tn "HRM-Startup" /v /fo LIST
echo      Test:   schtasks /Run /TN "HRM-Startup"  (then check ports 8000/8080/8081/5000)
pause
