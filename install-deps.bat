@echo off
set PYTHONHOME=
set PYTHONIOENCODING=
set PYTHONPATH=
rem 011/SERVER: relative paths — works from any install location (old absolute Desktop path removed).
rem NOTE: uses "python -m pip" (not pip.exe) — pip.exe launchers break easily on Windows.
if not exist "%~dp0zkteco-service\venv\Scripts\python.exe" (
    echo [ERROR] venv not found. Create it first: py -3 -m venv "%~dp0zkteco-service\venv"
    exit /b 1
)
"%~dp0zkteco-service\venv\Scripts\python.exe" -m pip install -r "%~dp0zkteco-service\requirements.txt"
