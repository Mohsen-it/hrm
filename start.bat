@echo off
REM ZKTeco Microservice Startup Script for Windows

echo Starting ZKTeco Microservice...

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

REM Check if .env exists
if not exist ".env" (
    echo Creating .env from .env.example...
    copy .env.example .env
)

REM Start the service
echo Starting service on port 5000...
echo.
python app.py

pause

