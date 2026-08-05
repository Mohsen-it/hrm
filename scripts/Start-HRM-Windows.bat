@echo off
setlocal EnableExtensions
set "LAUNCHER=%~f0"
set "LAUNCHER_DIR=%~dp0"

rem ---------------------------------------------------------------------------
rem HRM Windows service launcher
rem Starts exactly one instance of Laravel, Queue, Reverb, and ADMS.
rem Do not run composer run dev, queue:listen, queue:work, or reverb:start
rem separately while this launcher is running.
rem ---------------------------------------------------------------------------

if /I "%~1"=="--laravel-server" goto :LaravelServer
if /I "%~1"=="--queue-worker" goto :QueueWorker
if /I "%~1"=="--reverb-server" goto :ReverbServer
if /I "%~1"=="--adms-server" goto :AdmsServer
if /I "%~1"=="--bridge-server" goto :BridgeServer
if /I "%~1"=="--help" goto :Usage

set "ROOT=%~dp0.."
for %%I in ("%ROOT%") do set "ROOT=%%~fI"
rem Start the ZKTeco bridge by default; it is used by fingerprint operations.
set "START_BRIDGE=1"
set "SKIP_BUILD=0"

:ParseArguments
if "%~1"=="" goto :ArgumentsParsed
if /I "%~1"=="--bridge" (
    set "START_BRIDGE=1"
    shift
    goto :ParseArguments
)
if /I "%~1"=="--no-bridge" (
    set "START_BRIDGE=0"
    shift
    goto :ParseArguments
)
if /I "%~1"=="--skip-build" (
    set "SKIP_BUILD=1"
    shift
    goto :ParseArguments
)
echo [ERROR] Unknown option: %~1
goto :Usage

:ArgumentsParsed
echo.
echo === HRM service preflight ===
echo Root: %ROOT%

if not exist "%ROOT%\.env" (call :Fail "Missing %ROOT%\.env" & exit /b 1)
if not exist "%ROOT%\vendor\autoload.php" (call :Fail "Composer dependencies are missing. Run composer install first." & exit /b 1)
if not exist "%ROOT%\node_modules" (call :Fail "Node dependencies are missing. Run npm install first." & exit /b 1)
if not exist "%ROOT%\zkteco-service\adms_server.py" (call :Fail "ADMS server file is missing." & exit /b 1)
if not exist "%ROOT%\zkteco-service\venv\Scripts\python.exe" (call :Fail "Missing Python virtual environment. Run zkteco-service\install-deps.bat first." & exit /b 1)

where php >nul 2>&1 || (call :Fail "PHP is not available in PATH." & exit /b 1)
where npm.cmd >nul 2>&1 || (call :Fail "Node.js/npm is not available in PATH." & exit /b 1)

call :AssertPortFree 8000 Laravel || exit /b 1
call :AssertPortFree 8080 Reverb || exit /b 1
call :AssertPortFree 8081 ADMS || exit /b 1
if "%START_BRIDGE%"=="1" call :AssertPortFree 5000 "ZKTeco bridge" || exit /b 1

pushd "%ROOT%"
php artisan migrate:status > "%ROOT%\storage\logs\hrm-migration-status.log" 2>&1 || (
    popd
    call :Fail "Database preflight failed. See storage\logs\hrm-migration-status.log"
    exit /b 1
)

echo Clearing cached Laravel configuration...
php artisan optimize:clear || (
    popd
    call :Fail "Could not clear Laravel caches."
    exit /b 1
)

if "%SKIP_BUILD%"=="0" (
    echo Building frontend assets for production...
    call npm.cmd run build || (
        popd
        call :Fail "Frontend build failed."
        exit /b 1
    )
)
popd

echo Starting Laravel on http://0.0.0.0:8000 ...
call :StartService --laravel-server "%ROOT%" || (call :Fail "Could not launch Laravel." & exit /b 1)
call :WaitForPort 8000 Laravel 20 || (call :Fail "Laravel did not start. See storage\logs\hrm-laravel-server.log" & exit /b 1)

echo Starting queue worker...
call :StartService --queue-worker "%ROOT%" || (call :Fail "Could not launch the queue worker." & exit /b 1)

echo Starting Reverb on ws://127.0.0.1:8080 ...
call :StartService --reverb-server "%ROOT%" || (call :Fail "Could not launch Reverb." & exit /b 1)
call :WaitForPort 8080 Reverb 20 || (call :Fail "Reverb did not start. See storage\logs\hrm-reverb.log" & exit /b 1)

echo Starting ADMS on http://0.0.0.0:8081 ...
call :StartService --adms-server "%ROOT%\zkteco-service" || (call :Fail "Could not launch ADMS." & exit /b 1)
call :WaitForPort 8081 ADMS 20 || (call :Fail "ADMS did not start. See zkteco-service\logs\adms.log" & exit /b 1)

if "%START_BRIDGE%"=="1" (
    echo Starting optional ZKTeco bridge on http://127.0.0.1:5000 ...
    call :StartService --bridge-server "%ROOT%\zkteco-service" || (call :Fail "Could not launch the ZKTeco bridge." & exit /b 1)
    call :WaitForPort 5000 "ZKTeco bridge" 20 || (call :Fail "ZKTeco bridge did not start. See zkteco-service\logs\bridge.log" & exit /b 1)
)

echo.
echo [OK] HRM services are running:
echo      Laravel: http://SERVER-IP:8000
echo      Reverb:  ws://127.0.0.1:8080
echo      ADMS:    http://SERVER-IP:8081
if "%START_BRIDGE%"=="1" echo      Bridge:  http://127.0.0.1:5000
echo.
echo Keep this window open only to read status. The child services restart after a crash.
echo To stop them, close their HRM windows or stop their explicitly named processes.
if /I "%HRM_NO_PAUSE%"=="1" exit /b 0
echo.
echo Press any key to close this launcher window.
pause >nul
exit /b 0

:LaravelServer
call :SetRoot
cd /d "%ROOT%"
:LaravelLoop
echo [%date% %time%] Starting Laravel >> "%ROOT%\storage\logs\hrm-laravel-server.log"
php artisan serve --host=0.0.0.0 --port=8000 >> "%ROOT%\storage\logs\hrm-laravel-server.log" 2>&1
echo [%date% %time%] Laravel stopped; retrying in 3 seconds. >> "%ROOT%\storage\logs\hrm-laravel-server.log"
%SystemRoot%\System32\timeout.exe /t 3 /nobreak >nul
goto :LaravelLoop

:QueueWorker
call :SetRoot
cd /d "%ROOT%"
:QueueLoop
echo [%date% %time%] Starting queue worker >> "%ROOT%\storage\logs\hrm-queue.log"
php artisan queue:work --queue=default --tries=3 --timeout=90 --sleep=1 --memory=512 >> "%ROOT%\storage\logs\hrm-queue.log" 2>&1
echo [%date% %time%] Queue worker stopped; retrying in 3 seconds. >> "%ROOT%\storage\logs\hrm-queue.log"
%SystemRoot%\System32\timeout.exe /t 3 /nobreak >nul
goto :QueueLoop

:ReverbServer
call :SetRoot
cd /d "%ROOT%"
:ReverbLoop
echo [%date% %time%] Starting Reverb >> "%ROOT%\storage\logs\hrm-reverb.log"
php artisan reverb:start --host=0.0.0.0 --port=8000 >> "%ROOT%\storage\logs\hrm-reverb.log" 2>&1
echo [%date% %time%] Reverb stopped; retrying in 3 seconds. >> "%ROOT%\storage\logs\hrm-reverb.log"
%SystemRoot%\System32\timeout.exe /t 3 /nobreak >nul
goto :ReverbLoop

:AdmsServer
call :SetRoot
cd /d "%ROOT%\zkteco-service"
:AdmsLoop
echo [%date% %time%] Starting ADMS >> "%ROOT%\zkteco-service\logs\adms-launcher.log"
"%ROOT%\zkteco-service\venv\Scripts\python.exe" adms_server.py --host 0.0.0.0 --port 8081 --laravel http://127.0.0.1:8000 >> "%ROOT%\zkteco-service\logs\adms-launcher.log" 2>&1
echo [%date% %time%] ADMS stopped; retrying in 3 seconds. >> "%ROOT%\zkteco-service\logs\adms-launcher.log"
%SystemRoot%\System32\timeout.exe /t 3 /nobreak >nul
goto :AdmsLoop

:BridgeServer
call :SetRoot
cd /d "%ROOT%\zkteco-service"
:BridgeLoop
echo [%date% %time%] Starting ZKTeco bridge >> "%ROOT%\zkteco-service\logs\bridge.log"
"%ROOT%\zkteco-service\venv\Scripts\python.exe" app.py >> "%ROOT%\zkteco-service\logs\bridge.log" 2>&1
echo [%date% %time%] ZKTeco bridge stopped; retrying in 3 seconds. >> "%ROOT%\zkteco-service\logs\bridge.log"
%SystemRoot%\System32\timeout.exe /t 3 /nobreak >nul
goto :BridgeLoop

:SetRoot
set "ROOT=%LAUNCHER_DIR%.."
for %%I in ("%ROOT%") do set "ROOT=%%~fI"
exit /b 0

:StartService
set "HRM_SERVICE_COMMAND=call "%LAUNCHER%" %~1"
powershell -NoProfile -Command "Start-Process -FilePath $env:ComSpec -ArgumentList '/d','/c',$env:HRM_SERVICE_COMMAND -WorkingDirectory '%~2' -WindowStyle Hidden"
set "HRM_SERVICE_EXIT=%errorlevel%"
set "HRM_SERVICE_COMMAND="
exit /b %HRM_SERVICE_EXIT%

:AssertPortFree
powershell -NoProfile -Command "$listener = @(Get-NetTCPConnection -State Listen -LocalPort %~1 -ErrorAction SilentlyContinue 2^>$null); if ($listener.Count -gt 0) { Write-Host ('Port %~1 is already used by PID ' + $listener[0].OwningProcess); exit 1 }; exit 0"
if errorlevel 1 (
    call :Fail "%~2 cannot start because port %~1 is already in use. Stop the old process first."
    exit /b 1
)
exit /b 0

:WaitForPort
set "WAIT_PORT=%~1"
set "WAIT_NAME=%~2"
set "WAIT_SECONDS=%~3"
for /L %%S in (1,1,%WAIT_SECONDS%) do (
    powershell -NoProfile -Command "if (Get-NetTCPConnection -State Listen -LocalPort %WAIT_PORT% -ErrorAction SilentlyContinue) { exit 0 } exit 1" >nul 2>&1 && exit /b 0
    %SystemRoot%\System32\timeout.exe /t 1 /nobreak >nul
)
echo [ERROR] Timed out waiting for %WAIT_NAME% on port %WAIT_PORT%.
exit /b 1

:Fail
echo.
echo [ERROR] %~1
if /I "%HRM_NO_PAUSE%"=="1" exit /b 1
echo.
echo Press any key to close this window.
pause >nul
exit /b 1

:Usage
echo Usage: %~nx0 [--skip-build] [--bridge ^| --no-bridge]
echo.
echo   --skip-build  Do not run npm run build before starting services.
echo   --bridge      Start zkteco-service\app.py on port 5000 (default).
echo   --no-bridge   Do not start zkteco-service\app.py.
exit /b 1
