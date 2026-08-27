@echo off
setlocal EnableExtensions

rem HRM clean-start launcher — kills any old HRM orphans (ports 8000/8080/8081/5000,
rem queue workers, ADMS, bridge) then starts all services fresh on a clean slate.
rem Use -NoClean to skip the kill step:  Start-HRM-Windows.bat -NoClean
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
