@echo off
setlocal EnableExtensions

rem The PowerShell supervisor owns every HRM process in a Windows Job Object.
rem Closing this CMD window closes the job and terminates all child services.
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0Start-HRM-Windows.ps1" %*
set "HRM_EXIT_CODE=%ERRORLEVEL%"

if not "%HRM_EXIT_CODE%"=="0" (
    echo.
    echo [ERROR] HRM services did not start. Read the message above.
    if /I not "%HRM_NO_PAUSE%"=="1" pause
)

exit /b %HRM_EXIT_CODE%
