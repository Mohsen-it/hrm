@echo off
setlocal EnableExtensions
REM ============================================================
REM  HRM one-click installer launcher (double-click this file).
REM  Calls Install-HRM-OneClick.ps1 with all args passed through.
REM  Examples:
REM    INSTALL-HRM.bat
REM    INSTALL-HRM.bat -CheckOnly
REM    INSTALL-HRM.bat -Production -Seed -StartAfter -AutoInstall -ServerIp 10.10.250.2
REM  ASCII-only by project rule - never add non-ASCII chars here.
REM ============================================================

set "HERE=%~dp0"
set "PS1=%HERE%Install-HRM-OneClick.ps1"

if not exist "%PS1%" (
    echo [ERROR] Installer script not found: %PS1%
    pause
    exit /b 1
)

REM Re-launch elevated when possible so scheduler/autostart can register.
net session >nul 2>&1
if not "%ERRORLEVEL%"=="0" (
    echo Requesting administrator rights...
    powershell.exe -NoLogo -NoProfile -Command "Start-Process -FilePath '%~f0' -ArgumentList '%*' -Verb RunAs"
    exit /b %ERRORLEVEL%
)

powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%PS1%" %*
set "CODE=%ERRORLEVEL%"

echo.
if "%CODE%"=="0" (
    echo [DONE] HRM install finished successfully.
) else (
    if "%CODE%"=="2" (
        echo [NEXT] .env was created from template - fill it, then run INSTALL-HRM.bat again.
    ) else (
        echo [ERROR] HRM install finished with errors (code %CODE%). See storage\logs\hrm-install-*.log
    )
)
pause
exit /b %CODE%
