@echo off
REM Hikvision ISAPI Microservice Startup Script for Windows

echo Starting Hikvision ISAPI Microservice...

REM Check if virtual environment exists
if not exist "venv\" (
    echo Creating virtual environment...
    python -m venv venv
)

REM Activate virtual environment
echo Activating virtual environment...
call venv\Scripts\activate.bat

REM Fix Python path conflict with ZKBioTime
set PYTHONHOME=C:\Users\pc\AppData\Local\Programs\Python\Python315
set PATH=%~dp0venv\Scripts;C:\Users\pc\AppData\Local\Programs\Python\Python315;%PATH%

REM Install dependencies
echo Installing dependencies...
pip install -q -r requirements.txt

REM Start the Hikvision service
echo Starting Hikvision service on port 5001...
echo.
python hikvision_service.py

pause
