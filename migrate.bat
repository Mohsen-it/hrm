@echo off
setlocal
REM =============================================================
REM HRM Data Migration Tool - Batch Launcher
REM =============================================================
REM This script migrates data from the ZKTeco/PostgreSQL dump
REM into the Laravel HRM application database.
REM
REM Usage:
REM   migrate.bat                    - Run all steps
REM   migrate.bat --dry-run          - Dry run (no data inserted)
REM   migrate.bat --step companies   - Run specific step only
REM   migrate.bat --start-from employees  - Start from specific step
REM =============================================================

set SCRIPT_DIR=%~dp0
set VENV_PYTHON=%SCRIPT_DIR%venv\Scripts\python.exe
set MIGRATE_SCRIPT=%SCRIPT_DIR%migrate_to_laravel.py

if not exist "%VENV_PYTHON%" (
    echo ERROR: Python venv not found at %VENV_PYTHON%
    echo Please set up the Python venv first:
    echo   cd zkteco-service
    echo   python -m venv venv
    echo   venv\Scripts\pip install -r requirements.txt
    pause
    exit /b 1
)

if not exist "%MIGRATE_SCRIPT%" (
    echo ERROR: Migration script not found at %MIGRATE_SCRIPT%
    pause
    exit /b 1
)

REM Clean environment to avoid Python corruption from system paths
set PYTHONHOME=
set PYTHONPATH=

echo ============================================================
echo HRM Data Migration Tool
echo ============================================================
echo.
echo Starting migration...
echo.

"%VENV_PYTHON%" "%MIGRATE_SCRIPT%" %*

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Migration FAILED with error code %ERRORLEVEL%
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo Migration completed successfully!
echo.
echo Next steps:
echo   1. Run attendance processing:
echo      php artisan attendance:process-raw-logs
echo   2. Generate daily summaries:
echo      php artisan attendance:generate-daily-summaries
echo   3. Run attendance recalculation (optional):
echo      php artisan attendance:recalculate-range --from=2025-01-01 --to=2026-12-31
echo.
pause
endlocal
