[CmdletBinding()]
param(
    [switch] $SkipBuild,
    [switch] $NoBridge,
    [switch] $NoClean,
    # 011/SERVER: headless server mode -- no console interaction (no Q key),
    # silent supervision loop only. Interactive behaviour stays the default.
    [switch] $NonInteractive,
    # 011/SERVER: override the advertised LAN IP (auto-detected when empty).
    [string] $ServerIp = '',
    [switch] $Help
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if ([string]::IsNullOrWhiteSpace($ServerIp)) {
    # 011/SERVER: advertise a private LAN IPv4 (RFC1918) so a fresh machine
    # needs no manual IP edits and VPN/virtual adapters are skipped;
    # explicit -ServerIp always wins.
    $candidates = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -ne '127.0.0.1' } |
        Select-Object -ExpandProperty IPAddress
    $ServerIp = $candidates | Where-Object { $_ -match '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)' } | Select-Object -First 1
    if ([string]::IsNullOrWhiteSpace($ServerIp)) { $ServerIp = $candidates | Select-Object -First 1 }
    if ([string]::IsNullOrWhiteSpace($ServerIp)) { $ServerIp = '127.0.0.1' }
}
$LaravelPort = 8000
$ReverbPort = 8080
$AdmsPort = 8081
$BridgePort = 5000
$Python = Join-Path $Root 'zkteco-service\venv\Scripts\python.exe'

if ($Help) {
    Write-Host 'Usage: Start-HRM-Windows.bat [-SkipBuild] [-NoBridge] [-NoClean] [-NonInteractive] [-ServerIp 10.10.250.2]'
    Write-Host '  -SkipBuild     : skip npm run build'
    Write-Host '  -NoBridge      : do not start ZKTeco bridge (port 5000)'
    Write-Host '  -NoClean       : do not kill old services before start (default: clean)'
    Write-Host '  -NonInteractive: headless server mode -- no Q key, silent supervision loop'
    exit 0
}

Add-Type @'
using System;
using System.Runtime.InteropServices;

public static class HrmJob {
    [StructLayout(LayoutKind.Sequential)]
    public struct IoCounters {
        public ulong ReadOperationCount, WriteOperationCount, OtherOperationCount;
        public ulong ReadTransferCount, WriteTransferCount, OtherTransferCount;
    }

    [StructLayout(LayoutKind.Sequential)]
    public struct BasicLimitInformation {
        public long PerProcessUserTimeLimit, PerJobUserTimeLimit;
        public uint LimitFlags;
        public UIntPtr MinimumWorkingSetSize, MaximumWorkingSetSize;
        public uint ActiveProcessLimit;
        public IntPtr Affinity;
        public uint PriorityClass, SchedulingClass;
    }

    [StructLayout(LayoutKind.Sequential)]
    public struct ExtendedLimitInformation {
        public BasicLimitInformation BasicLimitInformation;
        public IoCounters IoInfo;
        public UIntPtr ProcessMemoryLimit, JobMemoryLimit, PeakProcessMemoryUsed, PeakJobMemoryUsed;
    }

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode)]
    public static extern IntPtr CreateJobObject(IntPtr attributes, string name);

    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern bool SetInformationJobObject(IntPtr job, int infoClass, IntPtr info, uint length);

    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern bool AssignProcessToJobObject(IntPtr job, IntPtr process);

    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern bool CloseHandle(IntPtr handle);
}
'@

function Test-HrmPortFree {
    param([int] $Port, [string] $Name)

    $listener = Get-HrmPortListener -Port $Port
    if ($listener) {
        # Last-ditch attempt: kill the stubborn process
        Write-Host "  Port $Port occupied by PID $listener - force killing..." -ForegroundColor DarkYellow
        & taskkill.exe /PID $listener /F 2>&1 | Out-Null
        Start-Sleep -Seconds 2
        $listener = Get-HrmPortListener -Port $Port
        if ($listener) {
            throw "$Name cannot start because port $Port is still in use by PID $listener after force kill."
        }
    }
}

function Wait-HrmPort {
    param([int] $Port, [string] $Name, [int] $Seconds = 20)

    for ($second = 0; $second -lt $Seconds; $second++) {
        if (Get-HrmPortListener -Port $Port) {
            return
        }

        Start-Sleep -Seconds 1
    }

    throw "$Name did not start on port $Port within $Seconds seconds."
}

function Get-HrmPortListener {
    param([int] $Port)

    $pattern = '^\s*TCP\s+\S+:' + $Port + '\s+\S+\s+LISTENING\s+(\d+)\s*$'
    foreach ($line in (netstat.exe -ano -p tcp)) {
        if ($line -match $pattern) {
            return $Matches[1]
        }
    }

    return $null
}

function Stop-HrmOldServices {
    Write-Host 'Cleaning old HRM services (killing orphans)...' -ForegroundColor Yellow

    # 1) Kill known HRM process patterns first (orphans not holding ports)
    $patterns = @(
        'adms_server\.py',
        'queue:work',
        'reverb:start',
        'schedule:work',
        'artisan serve',
        # 011/P0-7: bridge was missing from cleanup -- old bridge held port 5000,
        # new bridge failed to start, and the fatal throw killed ALL services.
        # Pattern matches the bridge command: `set ... & python.exe app.py`
        'app\.py'
    )
    foreach ($pat in $patterns) {
        try {
            $procs = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -match $pat }
            foreach ($p in $procs) {
                if ($p.ProcessId -eq $PID) { continue }
                Write-Host "  Killing orphan $($p.Name) (PID $($p.ProcessId)) [$pat]..." -ForegroundColor DarkYellow
                & taskkill.exe /PID $p.ProcessId /F 2>&1 | Out-Null
            }
        } catch {}
    }

    # 2) Kill stray python app.py (bridge)
    try {
        $bridges = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -eq 'python.exe' -and $_.CommandLine -match 'app\.py' }
        foreach ($p in $bridges) {
            if ($p.ProcessId -eq $PID) { continue }
            Write-Host "  Killing orphan bridge python (PID $($p.ProcessId))..." -ForegroundColor DarkYellow
            & taskkill.exe /PID $p.ProcessId /F 2>&1 | Out-Null
        }
    } catch {}

    # 3) Kill anything still listening on HRM ports (up to 3 rounds)
    $hrmPorts = @($script:LaravelPort, $script:ReverbPort, $script:AdmsPort)
    if (-not $script:NoBridge) { $hrmPorts += $script:BridgePort }

    for ($round = 1; $round -le 3; $round++) {
        $anyKilled = $false
        foreach ($port in $hrmPorts) {
            $listenerPid = Get-HrmPortListener -Port $port
            if ($listenerPid) {
                $anyKilled = $true
                try {
                    $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$listenerPid" -ErrorAction SilentlyContinue
                    $name = if ($proc) { $proc.Name } else { "PID $listenerPid" }
                    Write-Host "  [round $round] Killing $name (PID $listenerPid) on port $port..." -ForegroundColor DarkYellow
                    & taskkill.exe /PID $listenerPid /F 2>&1 | Out-Null
                } catch {}
            }
        }
        if (-not $anyKilled) { break }
        Start-Sleep -Seconds 2
    }

    # 4) Nuclear option: kill ALL php.exe except ourselves
    try {
        $phpProcs = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -eq 'php.exe' }
        foreach ($p in $phpProcs) {
            if ($p.ProcessId -eq $PID) { continue }
            Write-Host "  Killing stray php.exe (PID $($p.ProcessId))..." -ForegroundColor DarkYellow
            & taskkill.exe /PID $p.ProcessId /F 2>&1 | Out-Null
        }
    } catch {}

    Start-Sleep -Seconds 3

    # 5) Final verify
    foreach ($port in $hrmPorts) {
        $still = Get-HrmPortListener -Port $port
        if ($still) {
            Write-Host "  WARNING: port $port still in use by PID $still after cleanup." -ForegroundColor Red
        }
    }

    Write-Host '  Cleanup done.' -ForegroundColor Green
}

function New-HrmJob {
    $job = [HrmJob]::CreateJobObject([IntPtr]::Zero, $null)
    if ($job -eq [IntPtr]::Zero) {
        throw 'Could not create the Windows Job Object.'
    }

    $info = New-Object HrmJob+ExtendedLimitInformation
    # JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE
    $info.BasicLimitInformation.LimitFlags = 0x00002000
    $size = [Runtime.InteropServices.Marshal]::SizeOf([type] 'HrmJob+ExtendedLimitInformation')
    $memory = [Runtime.InteropServices.Marshal]::AllocHGlobal($size)

    try {
        [Runtime.InteropServices.Marshal]::StructureToPtr($info, $memory, $false)
        if (-not [HrmJob]::SetInformationJobObject($job, 9, $memory, [uint32] $size)) {
            throw 'Could not configure automatic HRM process cleanup.'
        }
    }
    finally {
        [Runtime.InteropServices.Marshal]::FreeHGlobal($memory)
    }

    return $job
}

function Start-HrmProcess {
    param(
        [IntPtr] $Job,
        [string] $Name,
        [string] $WorkingDirectory,
        [string] $Command,
        [string] $LogPath,
        # 011/P0-5 (long-term operation): when set, stdout (operational noise)
        # is discarded and only stderr (real errors) is appended to the log.
        # Opt-in per service -- default keeps the old `>> log 2>&1` behaviour.
        [switch] $DiscardStdout
    )

    if ($DiscardStdout) {
        $arguments = '/d /c "{0} >NUL 2>> ""{1}"""' -f $Command, $LogPath
    } else {
        $arguments = '/d /c "{0} >> ""{1}"" 2>&1"' -f $Command, $LogPath
    }
    $process = Start-Process -FilePath $env:ComSpec -ArgumentList $arguments -WorkingDirectory $WorkingDirectory -WindowStyle Hidden -PassThru

    if (-not [HrmJob]::AssignProcessToJobObject($Job, $process.Handle)) {
        $process.Kill($true)
        throw "Could not attach $Name to the HRM supervisor."
    }

    [PSCustomObject]@{
        Name = $Name
        Command = $Command
        WorkingDirectory = $WorkingDirectory
        LogPath = $LogPath
        Process = $process
    }
}

function Restart-HrmProcess {
    param([IntPtr] $Job, $Service)

    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Restarting $($Service.Name)..." -ForegroundColor Yellow
    $Service.Process = (Start-HrmProcess -Job $Job -Name $Service.Name -WorkingDirectory $Service.WorkingDirectory -Command $Service.Command -LogPath $Service.LogPath).Process
}

Write-Host ''
Write-Host '=== HRM service preflight ==='
Write-Host "Root: $Root"

# --- CLEAN FIRST: kill any old HRM services so we start on a clean slate ---
if (-not $NoClean) {
    Stop-HrmOldServices
} else {
    Write-Host 'Skipping cleanup (--NoClean).' -ForegroundColor DarkGray
}

foreach ($path in @(
    (Join-Path $Root '.env'),
    (Join-Path $Root 'vendor\autoload.php'),
    (Join-Path $Root 'node_modules'),
    (Join-Path $Root 'zkteco-service\adms_server.py'),
    $Python
)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Required file or directory is missing: $path"
    }
}

foreach ($port in @($LaravelPort, $ReverbPort, $AdmsPort)) {
    Test-HrmPortFree -Port $port -Name 'HRM service'
}
if (-not $NoBridge) {
    Test-HrmPortFree -Port $BridgePort -Name 'ZKTeco bridge'
}

Push-Location $Root
try {
    # 011/SERVER: at boot MySQL may still be starting when the logon starter
    # fires -- retry the DB preflight instead of dying instantly (previously a
    # single early failure meant a SILENT dead stack in a hidden window).
    $dbReady = $false
    for ($attempt = 1; $attempt -le 6; $attempt++) {
        & php artisan migrate:status *> (Join-Path $Root 'storage\logs\hrm-migration-status.log')
        if ($LASTEXITCODE -eq 0) { $dbReady = $true; break }
        Write-Host "  Database not ready (attempt $attempt/6) - waiting 10s..." -ForegroundColor Yellow
        Start-Sleep -Seconds 10
    }
    if (-not $dbReady) { throw 'Database preflight failed after 6 attempts. See storage\logs\hrm-migration-status.log.' }

    Write-Host 'Clearing cached Laravel configuration...'
    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) { throw 'Could not clear Laravel caches.' }

    if (-not $SkipBuild) {
        Write-Host 'Building frontend assets for production...'
        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
    }
}
finally {
    Pop-Location
}

# --- SAFE LOG ROTATION (011/P0-1): archive oversized stdout logs before starting services ---
# Old services were stopped above, so no open file handles exist at this point.
# Rename-only (never delete): history is preserved, and the original path keeps being used.
$HrmLogRotateThresholdMB = 50
# 011/P0-6: stdout archives are 100% request noise (verified 2026-09-09:
# 2,142,043 lines, 0 errors -- real errors live in daily laravel-*.log).
# Keep archives one retention cycle, then auto-delete. Matches LOG_DAILY_DAYS.
$HrmLogArchiveRetentionDays = 14
foreach ($logName in @('hrm-laravel-server.log', 'hrm-queue.log', 'hrm-scheduler.log')) {
    $logFile = Join-Path $Root ("storage\logs\$logName")
    if (Test-Path -LiteralPath $logFile) {
        $sizeMB = ((Get-Item -LiteralPath $logFile).Length / 1MB)
        if ($sizeMB -ge $HrmLogRotateThresholdMB) {
            $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
            $archived = Join-Path $Root ("storage\logs\$([IO.Path]::GetFileNameWithoutExtension($logName))-$stamp.log")
            Move-Item -LiteralPath $logFile -Destination $archived
            Write-Host "  Archived oversized log $logName ($([math]::Round($sizeMB, 1)) MB) to $(Split-Path $archived -Leaf)." -ForegroundColor DarkYellow
        }
    }
    # Retention: delete only stamped archives older than the retention window.
    # The live file (`$logName` exactly) never matches the `-*` pattern.
    # 011/P1-C: same rotation for the Python service logs (bridge.log grows
    # with Werkzeug request lines + restart cycles, same unbounded class).
    $archivePattern = "$([IO.Path]::GetFileNameWithoutExtension($logName))-*.log"
    $bridgeLogs = @(
        (Join-Path $Root 'zkteco-service\logs\bridge.log'),
        (Join-Path $Root 'zkteco-service\logs\adms-launcher.log'),
        # 011/P1-D: ADMS routine INFO lines (per device command) -- same noise class.
        (Join-Path $Root 'zkteco-service\logs\adms.log')
    )
    foreach ($bridgeLog in $bridgeLogs) {
        if (Test-Path -LiteralPath $bridgeLog) {
            $blogSizeMB = ((Get-Item -LiteralPath $bridgeLog).Length / 1MB)
            if ($blogSizeMB -ge $HrmLogRotateThresholdMB) {
                $bstamp = Get-Date -Format 'yyyyMMdd-HHmmss'
                $barchived = Join-Path (Split-Path $bridgeLog -Parent) ("$([IO.Path]::GetFileNameWithoutExtension($bridgeLog))-$bstamp.log")
                Move-Item -LiteralPath $bridgeLog -Destination $barchived
                Write-Host "  Archived oversized log $(Split-Path $bridgeLog -Leaf) ($([math]::Round($blogSizeMB, 1)) MB)." -ForegroundColor DarkYellow
            }
        }
    }
    Get-ChildItem -Path (Join-Path $Root 'storage\logs') -Filter $archivePattern -File -ErrorAction SilentlyContinue |
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$HrmLogArchiveRetentionDays) } |
        ForEach-Object {
            Remove-Item -LiteralPath $_.FullName -Force
            Write-Host "  Deleted expired log archive $($_.Name) (older than $HrmLogArchiveRetentionDays days)." -ForegroundColor DarkGray
        }
}

# --- OUTBOX VACUUM (011/P1-D, long-term operation) ---
# The ADMS outbox deletes delivered rows but never reclaims pages
# (measured 2026-09-09: 0 rows, 28MB file, 99.8% freelist).
# This runs in the preflight window: old services are stopped above and the
# new ADMS starts below, so no process holds the database. VACUUM then cannot
# block or contend with any writer. Failures only warn -- never stop startup.
$HrmOutboxDb = Join-Path $Root 'zkteco-service\logs\adms-outbox.sqlite3'
if ((Test-Path -LiteralPath $HrmOutboxDb) -and ((Get-Item -LiteralPath $HrmOutboxDb).Length / 1MB) -ge 10) {
    try {
        $vacuumScript = "import sqlite3; con=sqlite3.connect(r'$HrmOutboxDb'); con.execute('VACUUM;'); con.close()"
        & $Python -c $vacuumScript 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) {
            $afterMB = [math]::Round(((Get-Item -LiteralPath $HrmOutboxDb).Length / 1MB), 2)
            Write-Host "  Vacuumed ADMS outbox (now $afterMB MB)." -ForegroundColor DarkGray
        } else {
            Write-Host '  WARNING: ADMS outbox VACUUM failed -- continuing startup.' -ForegroundColor DarkYellow
        }
    } catch {
        Write-Host '  WARNING: ADMS outbox VACUUM skipped -- continuing startup.' -ForegroundColor DarkYellow
    }
}

$job = New-HrmJob
$services = [System.Collections.Generic.List[object]]::new()
try {
    # 011/P0-5: `artisan serve` writes one stdout line per HTTP request (~17MB/day,
    # mostly ADMS device polling). Discard stdout, keep stderr (real errors).
    # App errors are additionally preserved in the daily `laravel-*.log` channel.
    $services.Add((Start-HrmProcess -Job $job -Name 'Laravel' -WorkingDirectory $Root -Command "php artisan serve --host=0.0.0.0 --port=$LaravelPort" -LogPath (Join-Path $Root 'storage\logs\hrm-laravel-server.log') -DiscardStdout))
    Wait-HrmPort -Port $LaravelPort -Name 'Laravel'

    $services.Add((Start-HrmProcess -Job $job -Name 'Queue worker (default)' -WorkingDirectory $Root -Command 'php artisan queue:work --queue=default --tries=3 --timeout=180 --sleep=1 --memory=512 --max-jobs=1000 --max-time=3600 --backoff=10' -LogPath (Join-Path $Root 'storage\logs\hrm-queue.log')))
    $services.Add((Start-HrmProcess -Job $job -Name 'Queue worker (attendance)' -WorkingDirectory $Root -Command 'php artisan queue:work --queue=attendance,notifications --tries=3 --timeout=60 --sleep=1 --memory=512 --max-jobs=1000 --max-time=3600 --backoff=10' -LogPath (Join-Path $Root 'storage\logs\hrm-queue-attendance.log')))

    $services.Add((Start-HrmProcess -Job $job -Name 'Reverb' -WorkingDirectory $Root -Command "php artisan reverb:start --host=0.0.0.0 --port=$ReverbPort" -LogPath (Join-Path $Root 'storage\logs\hrm-reverb.log')))
    Wait-HrmPort -Port $ReverbPort -Name 'Reverb'

    $services.Add((Start-HrmProcess -Job $job -Name 'ADMS' -WorkingDirectory (Join-Path $Root 'zkteco-service') -Command "`"$Python`" adms_server.py --host 0.0.0.0 --port $AdmsPort --laravel http://127.0.0.1:$LaravelPort" -LogPath (Join-Path $Root 'zkteco-service\logs\adms-launcher.log')))
    Wait-HrmPort -Port $AdmsPort -Name 'ADMS'

    $services.Add((Start-HrmProcess -Job $job -Name 'Scheduler' -WorkingDirectory $Root -Command 'php artisan schedule:work --verbose --no-interaction' -LogPath (Join-Path $Root 'storage\logs\hrm-scheduler.log')))


    if (-not $NoBridge) {
        # 011/P1-C: quoted SET syntax -- unquoted `set VAR=value & ...` stores a
        # TRAILING SPACE ("0.0.0.0 "), which made Werkzeug's getaddrinfo fail
        # and the bridge never bind port 5000 (reproduced 2026-09-09).
        $bridgeCommand = 'set "ZKTECO_PYTHON_SERVICE_HOST=0.0.0.0" & set "ZKTECO_PYTHON_SERVICE_PORT=' + $BridgePort + '" & "' + $Python + '" app.py'
        $services.Add((Start-HrmProcess -Job $job -Name 'ZKTeco bridge' -WorkingDirectory (Join-Path $Root 'zkteco-service') -Command $bridgeCommand -LogPath (Join-Path $Root 'zkteco-service\logs\bridge.log')))
        # 011/P0-7: bridge is optional -- its failure must not kill the entire system.
        # Old behaviour: `throw` → Job closes → Laravel/Reverb/ADMS/Queue all die.
        # New behaviour: warning + continue -- user restarts bridge separately if needed.
        try {
            Wait-HrmPort -Port $BridgePort -Name 'ZKTeco bridge' -Seconds 30
        } catch {
            Write-Host "  WARNING: $_" -ForegroundColor DarkYellow
            Write-Host "  The ZKTeco bridge did not start. Core services continue without it." -ForegroundColor DarkYellow
            Write-Host "  To retry later: php D:\hrm\zkteco-service\app.py" -ForegroundColor DarkGray
        }
    }

    Write-Host ''
    Write-Host '[OK] HRM services are running:' -ForegroundColor Green
    Write-Host "     Laravel:  http://${ServerIp}:$LaravelPort"
    Write-Host "     Reverb:   ws://${ServerIp}:$ReverbPort"
    Write-Host "     ADMS:     http://${ServerIp}:$AdmsPort"
    Write-Host "     Scheduler: running (every minute)"
    if (-not $NoBridge) { Write-Host "     Bridge:   http://${ServerIp}:$BridgePort" }
    Write-Host ''
    if ($NonInteractive) {
        Write-Host 'Running headless (server mode): supervising services until the task stops this process.' -ForegroundColor Cyan
    } else {
        Write-Host 'Close this CMD window to stop and clean up every HRM process.' -ForegroundColor Cyan
        Write-Host 'Press Q to stop the services cleanly.' -ForegroundColor Cyan
    }

    while ($true) {
        foreach ($service in $services) {
            if ($service.Process.HasExited) {
                Start-Sleep -Seconds 3
                Restart-HrmProcess -Job $job -Service $service
            }
        }

        # 011/SERVER: Console has no input handle headless -- KeyAvailable would throw.
        if (-not $NonInteractive) {
            if ([Console]::KeyAvailable -and [Console]::ReadKey($true).Key -eq [ConsoleKey]::Q) {
                break
            }
        }

        Start-Sleep -Milliseconds 500
    }
}
finally {
    if ($job -and $job -ne [IntPtr]::Zero) {
        [void] [HrmJob]::CloseHandle($job)
    }

    Write-Host 'HRM services have been stopped.' -ForegroundColor Yellow
}
