#Requires -Version 5.1
<#
.SYNOPSIS
    HRM queue-worker watchdog (idempotent, safe to run every 5 minutes).

.DESCRIPTION
    Starts `php artisan queue:work` (queues: attendance,notifications,default)
    ONLY when no such worker is currently running. The `attendance` queue
    carries punch-ingestion chunks and must come first so a default backlog
    can never starve morning/evening bursts. Never touches devices, never
    deletes anything, never restarts running processes. Intended for Windows
    Task Scheduler:

      schtasks /Create /TN "HRM Queue Worker Watchdog" /TR "powershell -NoProfile -ExecutionPolicy Bypass -File D:\hrm\scripts\Ensure-HrmQueueWorker.ps1" /SC MINUTE /MO 5 /F

    Removal: schtasks /Delete /TN "HRM Queue Worker Watchdog" /F
#>

[CmdletBinding()]
param(
    [string]$Root = 'D:\hrm',
    [string]$Queues = 'attendance,notifications,default',
    [int]$Tries = 6,
    [int]$Timeout = 200,
    [int]$MaxJobs = 3000,
    [int]$MaxTime = 3600
)

$ErrorActionPreference = 'Stop'
$logDir = Join-Path $Root 'storage\logs'
$log = Join-Path $logDir 'hrm-queue-watchdog.log'

function Write-WatchdogLog([string]$message) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $message"
    Add-Content -LiteralPath $log -Value $line -Encoding UTF8
}

try {
    # 012/STABILITY: supervisor-aware -- when the HRM supervisor is alive it
    # owns every queue worker inside its Job Object (default, attendance x2,
    # biometrics) and heals them itself with backoff. A watchdog launch in
    # that state would create an UNMANAGED orphan: invisible to cleanup,
    # duplicating work and fighting the next restart. Only act when the
    # supervisor is genuinely gone.
    $supervisor = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '^powershell(\.exe)?$' -and $_.CommandLine -match 'Start-HRM-Windows\.ps1' } |
        Select-Object -First 1

    if ($supervisor) {
        Write-WatchdogLog "OK - supervisor alive (PID $($supervisor.ProcessId)). Workers are managed. Nothing to do."
        exit 0
    }

    # Supervisor is down: rescue only if no managed-queue worker survives.
    # (A lone `biometrics` orphan must not block the rescue of the
    # attendance/default queues.)
    $existing = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -eq 'php.exe' -and $_.CommandLine -match 'artisan\s+queue:work' -and $_.CommandLine -match 'default|attendance' } |
        Select-Object -First 1

    if ($existing) {
        Write-WatchdogLog "OK - worker already running (PID $($existing.ProcessId)). Nothing to do."
        exit 0
    }

    $args = "artisan queue:work --queue=$Queues --tries=$Tries --timeout=$Timeout --sleep=1 --memory=512 --max-jobs=$MaxJobs --max-time=$MaxTime --backoff=10"
    $proc = Start-Process -FilePath 'php' -ArgumentList $args `
        -WorkingDirectory $Root -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDir 'hrm-queue-worker.log') `
        -RedirectStandardError (Join-Path $logDir 'hrm-queue-worker.err.log') -PassThru

    Write-WatchdogLog "STARTED - queue worker launched (PID $($proc.Id), queues=$Queues)."
    exit 0
}
catch {
    try { Write-WatchdogLog "ERROR - $($_.Exception.Message)" } catch { }
    exit 1
}
