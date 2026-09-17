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

# 012/STABILITY (long-term): resolve php to a full path once, so the stack
# also starts under SYSTEM where PATH edits could otherwise break it.
$PhpExe = 'php'
try {
    $found = Get-Command php -ErrorAction Stop
    if ($found.Source) { $PhpExe = $found.Source }
} catch {
    throw 'PHP executable not found in PATH. Install Laragon PHP or fix PATH before starting HRM.'
}

# 012/STABILITY: supervisor event log (restarts, backoff, circuit-breaker).
# Console output is invisible headless -- this file is the audit trail.
$SupervisorLog = Join-Path $Root 'storage\logs\hrm-supervisor.log'

function Write-HrmSupervisorLog([string] $Message) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Write-Host $line
    try { Add-Content -LiteralPath $script:SupervisorLog -Value $line -Encoding UTF8 } catch {}
}

# 012/STABILITY: headless = no visible console. Without this trap a fatal
# preflight error (port held by an elevated process, missing php, dead DB)
# vanishes with the hidden window and looks exactly like "the supervisor
# died for no reason". Now every fatal lands in hrm-supervisor.log.
trap {
    try { Write-HrmSupervisorLog "FATAL: $($_.Exception.Message)" } catch {}
    break
}

# 012/STABILITY: restart policy -- exponential-ish backoff plus a circuit
# breaker so a poisoned service can never hot-loop forever.
$HrmBackoffSeconds = @(3, 10, 30, 120)
$HrmCrashWindow = [TimeSpan]::FromMinutes(10)
$HrmCrashLimit = 6
$HrmCooldown = [TimeSpan]::FromMinutes(10)
$HrmHealthCheckEvery = 60   # supervision iterations (~500ms each => ~30s)
$HrmPortGraceFails = 2      # consecutive failed port checks before a hung restart

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
        & taskkill.exe /PID $listener /T /F 2>&1 | Out-Null
        Start-Sleep -Seconds 2
        $listener = Get-HrmPortListener -Port $Port
        if ($listener) {
            # 012/STABILITY: never fatal. A foreign/unkillable holder used to
            # abort the ENTIRE boot (FATAL, all services down). Now we warn
            # and continue -- the supervision loop retries the affected
            # service with backoff, and the circuit breaker bounds the loop.
            Write-Host "  WARNING: port $Port is still held by PID $listener (unkillable or foreign) -- continuing; the supervisor will keep retrying." -ForegroundColor Red
            try { Write-HrmSupervisorLog "WARNING: port $Port still held by PID $listener at preflight -- will retry with backoff." } catch {}
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

    # 1) Kill known HRM process patterns first (orphans not holding ports).
    # NOTE: `reverb:start` is intentionally ABSENT -- port 8080 is owned by
    # the NSSM service HRM-Reverb (see production/DEPLOY-NEW-MACHINE.md).
    # The supervisor must never kill, start, or wait on it.
    $patterns = @(
        'adms_server\.py',
        'queue:work',
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

    # 3) Kill anything still listening on HRM ports (up to 3 rounds).
    # NOTE: ReverbPort (8080) is intentionally ABSENT -- it belongs to the
    # NSSM service HRM-Reverb; the supervisor never touches that port.
    $hrmPorts = @($script:LaravelPort, $script:AdmsPort)
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

    # 4) 012/STABILITY -- scoped sweep (replaces the old nuclear option).
    # The old code killed EVERY php.exe on the machine, including Laragon's
    # php-cgi workers and foreign scheduled tasks. Now only processes whose
    # command line lives under the HRM root are reaped; php-cgi.exe is
    # explicitly never touched.
    try {
        $rootEsc = [regex]::Escape($script:Root)
        $leftovers = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object {
            ($_.Name -eq 'php.exe' -or $_.Name -eq 'python.exe') -and
            $_.Name -ne 'php-cgi.exe' -and
            $_.CommandLine -match $rootEsc
        }
        foreach ($p in $leftovers) {
            if ($p.ProcessId -eq $PID) { continue }
            Write-Host "  Killing lingering HRM $($p.Name) (PID $($p.ProcessId))..." -ForegroundColor DarkYellow
            & taskkill.exe /PID $p.ProcessId /T /F 2>&1 | Out-Null
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
        [switch] $DiscardStdout,
        # 012/STABILITY: TCP port the service must listen on (0 = none).
        # Used by the supervision loop for hung-process (port dead, pid alive)
        # detection and for port-aware restarts.
        [int] $Port = 0
    )

    if ($DiscardStdout) {
        $arguments = '/d /c "{0} >NUL 2>> ""{1}"""' -f $Command, $LogPath
    } else {
        $arguments = '/d /c "{0} >> ""{1}"" 2>&1"' -f $Command, $LogPath
    }
    $process = Start-Process -FilePath $env:ComSpec -ArgumentList $arguments -WorkingDirectory $WorkingDirectory -WindowStyle Hidden -PassThru

    if (-not [HrmJob]::AssignProcessToJobObject($Job, $process.Handle)) {
        # 012/STABILITY: Process.Kill(bool) exists only on .NET Core 3.0+;
        # on Windows PowerShell 5.1 (.NET Framework) it throws
        # "Cannot find an overload for Kill" and killed the whole supervisor.
        # taskkill /T also reaps the cmd wrapper's children (php/python).
        & taskkill.exe /PID $process.Id /T /F 2>&1 | Out-Null
        throw "Could not attach $Name to the HRM supervisor."
    }

    [PSCustomObject]@{
        Name = $Name
        Command = $Command
        WorkingDirectory = $WorkingDirectory
        LogPath = $LogPath
        Port = $Port
        DiscardStdout = [bool] $DiscardStdout
        Process = $process
        CrashTimes = @()
        CooldownUntil = $null
        ConsecutivePortFails = 0
    }
}

function Restart-HrmProcess {
    param([IntPtr] $Job, $Service)

    # 012/STABILITY: the wrapper (cmd.exe) may have exited while its child
    # (php/python) lingers and still holds the port. Reap the whole tree so
    # the replacement never collides with its own ghost. No-op when gone.
    try {
        $oldId = $Service.Process.Id
        if ($oldId) { & taskkill.exe /PID $oldId /T /F 2>&1 | Out-Null }
    } catch {}
    # 012/STABILITY: port-owning services get one clean shot at a free port --
    # a stale holder is killed once instead of failing the bind forever.
    # If the port is STILL held afterwards (unkillable holder, or a sibling
    # supervisor's live service), ABORT this restart instead of binding a
    # duplicate -- Reverb tolerates double-bind via SO_REUSEADDR and the
    # duplicates pile up silently. The crash is counted and backoff continues.
    if ($Service.Port -gt 0) {
        $holder = Get-HrmPortListener -Port $Service.Port
        if ($holder) {
            Write-HrmSupervisorLog "Port $($Service.Port) still held by PID $holder -- force killing before restarting $($Service.Name)..."
            & taskkill.exe /PID $holder /T /F 2>&1 | Out-Null
            Start-Sleep -Seconds 3
            $holder = Get-HrmPortListener -Port $Service.Port
            if ($holder) {
                throw "Aborting restart of $($Service.Name): port $($Service.Port) still held by PID $holder."
            }
        }
    }
    $new = Start-HrmProcess -Job $Job -Name $Service.Name -WorkingDirectory $Service.WorkingDirectory -Command $Service.Command -LogPath $Service.LogPath -Port $Service.Port -DiscardStdout:$Service.DiscardStdout
    $Service.Process = $new.Process
    $Service.ConsecutivePortFails = 0
}

Write-Host ''
Write-Host '=== HRM service preflight ==='
Write-Host "Root: $Root"

# 012/STABILITY -- single-instance mutex. A second supervisor used to start
# (manual run + scheduled task, overlapping restarts) and the two instances
# fought over the same ports: each one killed and restarted the other's
# children forever, with Reverb double-binding 8080 via SO_REUSEADDR as the
# only visible symptom. The loser now exits immediately WITHOUT touching
# anything (in particular before the cleanup below, which would otherwise
# murder the live instance's children).
$HrmMutex = $null
$HrmMutexAcquired = $false
foreach ($mutexName in @('Global\HRM-Supervisor', 'Local\HRM-Supervisor')) {
    try {
        $HrmMutex = New-Object System.Threading.Mutex($false, $mutexName)
        try {
            $HrmMutexAcquired = $HrmMutex.WaitOne(0)
        } catch [System.Threading.AbandonedMutexException] {
            $HrmMutexAcquired = $true  # previous owner died hard -- we own it now
        }
        if ($HrmMutexAcquired) { break }
        try { $HrmMutex.Dispose() } catch {}
        $HrmMutex = $null
    } catch {
        try { if ($HrmMutex) { $HrmMutex.Dispose() } } catch {}
        $HrmMutex = $null
    }
}
if (-not $HrmMutexAcquired) {
    Write-HrmSupervisorLog 'Another HRM supervisor already owns the Global\HRM-Supervisor mutex -- this instance exits without touching any service.'
    exit 0
}

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

foreach ($port in @($LaravelPort, $AdmsPort)) {
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
        & $PhpExe artisan migrate:status *> (Join-Path $Root 'storage\logs\hrm-migration-status.log')
        if ($LASTEXITCODE -eq 0) { $dbReady = $true; break }
        Write-Host "  Database not ready (attempt $attempt/6) - waiting 10s..." -ForegroundColor Yellow
        Start-Sleep -Seconds 10
    }
    if (-not $dbReady) { throw 'Database preflight failed after 6 attempts. See storage\logs\hrm-migration-status.log.' }

    Write-Host 'Clearing cached Laravel configuration...'
    & $PhpExe artisan optimize:clear
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
foreach ($logName in @('hrm-laravel-server.log', 'hrm-queue.log', 'hrm-queue-attendance.log', 'hrm-queue-attendance-2.log', 'hrm-queue-biometrics.log', 'hrm-scheduler.log', 'hrm-schedule-run.log', 'hrm-supervisor.log', 'hrm-reverb.log', 'hrm-queue-watchdog.log')) {
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
    $services.Add((Start-HrmProcess -Job $job -Name 'Laravel' -WorkingDirectory $Root -Command "$PhpExe artisan serve --host=0.0.0.0 --port=$LaravelPort" -LogPath (Join-Path $Root 'storage\logs\hrm-laravel-server.log') -DiscardStdout -Port $LaravelPort))
    Wait-HrmPort -Port $LaravelPort -Name 'Laravel'

    # 012/STABILITY: the port may be open while the app is still booting
    # (migrations replay, route cache). ADMS posts to Laravel on start, so
    # wait for a real 200 from /up -- warning only, never a fatal block.
    $upOk = $false
    for ($i = 1; $i -le 15; $i++) {
        try {
            $r = Invoke-WebRequest -Uri "http://127.0.0.1:$LaravelPort/up" -UseBasicParsing -TimeoutSec 5
            if ($r.StatusCode -eq 200) { $upOk = $true; break }
        } catch {}
        Start-Sleep -Seconds 2
    }
    if (-not $upOk) { Write-Host '  WARNING: Laravel port is open but /up did not return 200 -- continuing startup.' -ForegroundColor DarkYellow }

    $services.Add((Start-HrmProcess -Job $job -Name 'Queue worker (default)' -WorkingDirectory $Root -Command "$PhpExe artisan queue:work --queue=default --tries=3 --timeout=180 --sleep=1 --memory=512 --max-jobs=1000 --max-time=3600 --backoff=10" -LogPath (Join-Path $Root 'storage\logs\hrm-queue.log')))
    # Punch bursts (morning/evening): AttendanceIngestionJob chunks (100 punches
    # each, timeout=180) land on the `attendance` queue. Two workers drain the
    # spike in parallel; --timeout must stay above the job timeout and --sleep=0
    # avoids a 1s idle pause between burst jobs. device_commands distribution
    # is DB-polled by ADMS and never goes through these workers.
    $services.Add((Start-HrmProcess -Job $job -Name 'Queue worker (attendance 1)' -WorkingDirectory $Root -Command "$PhpExe artisan queue:work --queue=attendance,notifications --tries=3 --timeout=200 --sleep=0 --memory=512 --max-jobs=1000 --max-time=3600 --backoff=10" -LogPath (Join-Path $Root 'storage\logs\hrm-queue-attendance.log')))
    $services.Add((Start-HrmProcess -Job $job -Name 'Queue worker (attendance 2)' -WorkingDirectory $Root -Command "$PhpExe artisan queue:work --queue=attendance,notifications --tries=3 --timeout=200 --sleep=0 --memory=512 --max-jobs=1000 --max-time=3600 --backoff=10" -LogPath (Join-Path $Root 'storage\logs\hrm-queue-attendance-2.log')))
    # 012/STABILITY: the `biometrics` queue worker used to live in its own
    # Task Scheduler task ("HRM-temp-biometrics-worker") outside the Job
    # Object, so cleanup kills and restarts never managed it. It is now a
    # first-class supervised service. NOTE: the legacy task used
    # `queue:work biometrics --queue=...`, which treats `biometrics` as a
    # CONNECTION name -- and no such connection exists in config/queue.php,
    # so that worker died instantly since 2026-08-26 (task result -1).
    # The corrected form below drains the `biometrics` QUEUE on the default
    # (database) connection with the legacy tries/timeout/sleep/memory.
    $services.Add((Start-HrmProcess -Job $job -Name 'Queue worker (biometrics)' -WorkingDirectory $Root -Command "$PhpExe artisan queue:work --queue=biometrics --tries=3 --timeout=3500 --sleep=1 --memory=512" -LogPath (Join-Path $Root 'storage\logs\hrm-queue-biometrics.log')))

    # 012/STABILITY: Reverb is intentionally NOT a supervised process.
    # Port 8080 is owned by the NSSM Windows service HRM-Reverb
    # (php artisan reverb:start --host=0.0.0.0 --port=8080, Automatic).
    # A supervisor-owned copy double-bound the port via SO_REUSEADDR and
    # silently split WebSocket clients in half. Expect the port to be held
    # by NSSM -- warn (never touch) when it is dark.
    if (-not (Get-HrmPortListener -Port $ReverbPort)) {
        Write-Host '  WARNING: port 8080 (NSSM HRM-Reverb) is not listening -- check the HRM-Reverb service. Continuing startup.' -ForegroundColor DarkYellow
        try { Write-HrmSupervisorLog 'WARNING: port 8080 (NSSM HRM-Reverb) is dark at boot.' } catch {}
    }

    $services.Add((Start-HrmProcess -Job $job -Name 'ADMS' -WorkingDirectory (Join-Path $Root 'zkteco-service') -Command "`"$Python`" adms_server.py --host 0.0.0.0 --port $AdmsPort --laravel http://127.0.0.1:$LaravelPort" -LogPath (Join-Path $Root 'zkteco-service\logs\adms-launcher.log') -Port $AdmsPort))
    Wait-HrmPort -Port $AdmsPort -Name 'ADMS'

    # 012/STABILITY: the Scheduler is intentionally NOT a supervised process
    # anymore. `schedule:work` is a dev-mode forever-loop; on a server the
    # Laravel-recommended pattern is `schedule:run` once per minute from Task
    # Scheduler ("HRM Scheduler" task). Every scheduled command already uses
    # withoutOverlapping(), so overlapping runners are impossible.


    if (-not $NoBridge) {
        # 011/P1-C: quoted SET syntax -- unquoted `set VAR=value & ...` stores a
        # TRAILING SPACE ("0.0.0.0 "), which made Werkzeug's getaddrinfo fail
        # and the bridge never bind port 5000 (reproduced 2026-09-09).
        $bridgeCommand = 'set "ZKTECO_PYTHON_SERVICE_HOST=0.0.0.0" & set "ZKTECO_PYTHON_SERVICE_PORT=' + $BridgePort + '" & "' + $Python + '" app.py'
        $services.Add((Start-HrmProcess -Job $job -Name 'ZKTeco bridge' -WorkingDirectory (Join-Path $Root 'zkteco-service') -Command $bridgeCommand -LogPath (Join-Path $Root 'zkteco-service\logs\bridge.log') -Port $BridgePort))
        # 011/P0-7: bridge is optional -- its failure must not kill the entire system.
        # Old behaviour: `throw` → Job closes → Laravel/Reverb/ADMS/Queue all die.
        # New behaviour: warning + continue -- user restarts bridge separately if needed.
        # 012/STABILITY: the bridge (CPython imports) is slow to bind; a stale
        # holder from the previous generation is killed once and given a
        # second chance before we degrade to warning (the supervision loop
        # keeps retrying with backoff afterwards).
        try {
            Wait-HrmPort -Port $BridgePort -Name 'ZKTeco bridge' -Seconds 30
        } catch {
            $holder = Get-HrmPortListener -Port $BridgePort
            if ($holder) {
                Write-Host "  Stale holder PID $holder on port $BridgePort -- killing once and retrying..." -ForegroundColor DarkYellow
                & taskkill.exe /PID $holder /T /F 2>&1 | Out-Null
                Start-Sleep -Seconds 3
            }
            try {
                Wait-HrmPort -Port $BridgePort -Name 'ZKTeco bridge' -Seconds 20
            } catch {
                Write-Host "  WARNING: $_" -ForegroundColor DarkYellow
                Write-Host "  The ZKTeco bridge did not start. Core services continue without it." -ForegroundColor DarkYellow
                Write-Host "  To retry later: php D:\hrm\zkteco-service\app.py" -ForegroundColor DarkGray
            }
        }
    }

    Write-Host ''
    Write-Host '[OK] HRM services are running:' -ForegroundColor Green
    Write-Host "     Laravel:  http://${ServerIp}:$LaravelPort"
    Write-Host "     Reverb:   ws://${ServerIp}:$ReverbPort (NSSM service 'HRM-Reverb')"
    Write-Host "     ADMS:     http://${ServerIp}:$AdmsPort"
    Write-Host "     Scheduler: Task Scheduler 'HRM Scheduler' (schedule:run every minute)"
    if (-not $NoBridge) { Write-Host "     Bridge:   http://${ServerIp}:$BridgePort" }
    Write-Host ''
    if ($NonInteractive) {
        Write-Host 'Running headless (server mode): supervising services until the task stops this process.' -ForegroundColor Cyan
    } else {
        Write-Host 'Close this CMD window to stop and clean up every HRM process.' -ForegroundColor Cyan
        Write-Host 'Press Q to stop the services cleanly.' -ForegroundColor Cyan
    }

    # 012/STABILITY supervision loop: per-service crash accounting with
    # backoff + circuit breaker, plus periodic port-health checks that catch
    # hung processes (pid alive, port dead) which HasExited alone can never
    # see. All restart events go to hrm-supervisor.log (console is headless).
    $iteration = 0
    while ($true) {
        $iteration++
        $now = Get-Date

        foreach ($service in $services) {
            # Cooldown: circuit is OPEN for this service, skip silently.
            if ($service.CooldownUntil -and $now -lt $service.CooldownUntil) { continue }
            if ($service.CooldownUntil -and $now -ge $service.CooldownUntil) {
                $service.CooldownUntil = $null
                Write-HrmSupervisorLog "$($service.Name) cooldown over -- circuit CLOSED, resuming supervision."
            }

            if ($service.Process.HasExited) {
                # Keep only crashes inside the window, then account this one.
                $service.CrashTimes = @($service.CrashTimes | Where-Object { $_ -gt $now.Subtract($HrmCrashWindow) })
                $service.CrashTimes += $now

                if ($service.CrashTimes.Count -ge $HrmCrashLimit) {
                    $service.CooldownUntil = $now.Add($HrmCooldown)
                    $service.CrashTimes = @()
                    Write-HrmSupervisorLog "$($service.Name) crashed $($HrmCrashLimit)x in $($HrmCrashWindow.TotalMinutes) min -- circuit OPEN, cooling down $($HrmCooldown.TotalMinutes) min. Check $($service.LogPath)."
                    continue
                }

                $backoffIdx = [Math]::Min($service.CrashTimes.Count - 1, $HrmBackoffSeconds.Count - 1)
                $waitSecs = $HrmBackoffSeconds[$backoffIdx]
                Write-HrmSupervisorLog "Restarting $($service.Name) (crash $($service.CrashTimes.Count)/$HrmCrashLimit in window, backoff ${waitSecs}s)..."
                Start-Sleep -Seconds $waitSecs
                try {
                    Restart-HrmProcess -Job $job -Service $service
                    Write-HrmSupervisorLog "$($service.Name) restarted (PID $($service.Process.Id))."
                } catch {
                    Write-HrmSupervisorLog "$($service.Name) restart FAILED: $($_.Exception.Message)"
                }
                continue
            }

            # Process alive: reset crash history once it proves stable for a
            # full window (a flap long ago must not count against it forever).
            if ($service.CrashTimes.Count -gt 0) {
                $lastCrash = ($service.CrashTimes | Sort-Object | Select-Object -Last 1)
                if ($now - $lastCrash -gt $HrmCrashWindow) { $service.CrashTimes = @() }
            }
        }

        # Periodic port-health sweep: pid alive but nothing listening.
        if (($iteration % $HrmHealthCheckEvery) -eq 0) {
            foreach ($service in $services) {
                if ($service.Port -le 0) { continue }
                if ($service.CooldownUntil -and (Get-Date) -lt $service.CooldownUntil) { continue }
                try {
                    if ($service.Process.HasExited) { continue }
                } catch { continue }
                if (-not (Get-HrmPortListener -Port $service.Port)) {
                    $service.ConsecutivePortFails++
                    if ($service.ConsecutivePortFails -ge $HrmPortGraceFails) {
                        Write-HrmSupervisorLog "$($service.Name) (PID $($service.Process.Id)) alive but port $($service.Port) is dark ${HrmPortGraceFails}x -- treating as hung, restarting..."
                        $service.CrashTimes = @($service.CrashTimes | Where-Object { $_ -gt (Get-Date).Subtract($HrmCrashWindow) })
                        $service.CrashTimes += (Get-Date)
                        try {
                            & taskkill.exe /PID $service.Process.Id /T /F 2>&1 | Out-Null
                        } catch {}
                        Start-Sleep -Seconds 3
                        try {
                            Restart-HrmProcess -Job $job -Service $service
                            Write-HrmSupervisorLog "$($service.Name) restarted after hang (PID $($service.Process.Id))."
                        } catch {
                            Write-HrmSupervisorLog "$($service.Name) restart FAILED: $($_.Exception.Message)"
                        }
                    }
                } else {
                    $service.ConsecutivePortFails = 0
                }
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
    # 012/STABILITY: release the single-instance mutex (Closing the Job
    # above already terminated every supervised child).
    try {
        if ($HrmMutexAcquired -and $HrmMutex) { $HrmMutex.ReleaseMutex() }
        if ($HrmMutex) { $HrmMutex.Dispose() }
    } catch {}

    Write-Host 'HRM services have been stopped.' -ForegroundColor Yellow
}
