#Requires -Version 5.1
<#
.SYNOPSIS
    HRM one-time admin unification (012/STABILITY). Run AS ADMINISTRATOR once.

.DESCRIPTION
    Performs every change that needs elevation, idempotently (safe to re-run):
      1. Registers Task Scheduler "HRM Scheduler"  ->  php artisan schedule:run
         every minute as SYSTEM (replaces the supervised `schedule:work`
         forever-loop; every scheduled command already uses
         withoutOverlapping(), so double-runs are impossible).
      2. Registers "HRM-Startup" from production\HRM-Startup.xml (At Startup,
         SYSTEM, hidden, restart-on-failure) and DELETES the legacy
         interactive logon task "HRM AutoStart" (it dies on logoff/lock and
         spawns duplicate supervisors).
      3. DISABLES "HRM-temp-biometrics-worker" (the biometrics queue worker is
         now a supervised service inside Start-HRM-Windows.ps1 with identical
         arguments; the orphan task would fight the supervisor).

    Order is fail-safe: nothing legacy is removed until its replacement is
    registered AND verified. All output is printed and appended to
    storage\logs\hrm-admin-setup.log.

    Rollback: backups are written to backups\admin-unification-<stamp>\.
#>

[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# --- Self-elevation: re-launch as admin when needed (no manual steps) ---
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host 'Not elevated -- relaunching as Administrator...' -ForegroundColor Yellow
    $psi = Start-Process -FilePath 'powershell.exe' -ArgumentList @(
        '-NoLogo', '-NoProfile', '-ExecutionPolicy', 'Bypass',
        '-File', "`"$PSCommandPath`""
    ) -Verb RunAs -PassThru
    $psi.WaitForExit()
    exit $psi.ExitCode
}

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$LogFile = Join-Path $Root 'storage\logs\hrm-admin-setup.log'
$BackupDir = Join-Path $Root ("backups\admin-unification-" + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null

function Write-SetupLog([string] $Message, [string] $Color = 'White') {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Write-Host $line -ForegroundColor $Color
    Add-Content -LiteralPath $LogFile -Value $line -Encoding UTF8
}

$failed = 0
Write-SetupLog '=== HRM admin unification started (elevated) ===' 'Cyan'

try {
    # --- Preflight: required files exist ---
    $PhpPath = 'C:\laragon\bin\php\php-8.3.28-nts-Win32-vs16-x64\php.exe'
    foreach ($p in @($PhpPath, (Join-Path $Root 'artisan'), (Join-Path $Root 'production\HRM-Startup.xml'))) {
        if (-not (Test-Path -LiteralPath $p)) { throw "Required path missing: $p" }
    }
    Write-SetupLog 'Preflight OK (php, artisan, HRM-Startup.xml present).' 'Green'

    # --- Step 1: HRM Scheduler (schedule:run every minute, SYSTEM) ---
    # 012/STABILITY fix: Task Scheduler does NOT interpret `>>` redirection --
    # it passes it literally to php.exe, which rejects it (exit 1, no log).
    # The action must go through cmd.exe, exactly like the supervisor does.
    $action = New-ScheduledTaskAction -Execute 'cmd.exe' `
        -Argument "/d /c `"`"$PhpPath`" artisan schedule:run >> `"D:\hrm\storage\logs\hrm-schedule-run.log`" 2>&1`"" `
        -WorkingDirectory $Root
    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
        -RepetitionInterval (New-TimeSpan -Minutes 1) `
        -RepetitionDuration (New-TimeSpan -Days 3650)
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
        -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -MultipleInstances IgnoreNew
    Register-ScheduledTask -TaskName 'HRM Scheduler' -Action $action -Trigger $trigger `
        -Principal $principal -Settings $settings `
        -Description 'HRM Laravel scheduler (schedule:run every minute). Replaces the supervised schedule:work loop. All jobs use withoutOverlapping.' `
        -Force | Out-Null
    $t = Get-ScheduledTask -TaskName 'HRM Scheduler' -ErrorAction Stop
    Write-SetupLog "HRM Scheduler registered (State=$($t.State))." 'Green'

    # Prove it executes: run once now and watch the log move.
    $runLog = Join-Path $Root 'storage\logs\hrm-schedule-run.log'
    Start-ScheduledTask -TaskName 'HRM Scheduler'
    $proved = $false
    for ($i = 1; $i -le 24; $i++) {
        Start-Sleep -Seconds 5
        if ((Test-Path -LiteralPath $runLog) -and ((Get-Item -LiteralPath $runLog).LastWriteTime -gt (Get-Date).AddMinutes(-3))) {
            $proved = $true; break
        }
    }
    if ($proved) { Write-SetupLog 'HRM Scheduler PROVED: first run wrote to hrm-schedule-run.log.' 'Green' }
    else { throw 'HRM Scheduler did not write to hrm-schedule-run.log within 120s.' }

    # --- Step 2: unify autostart (register new BEFORE removing legacy) ---
    schtasks /query /tn 'HRM AutoStart' /xml > (Join-Path $BackupDir 'HRM-AutoStart-backup.xml') 2>$null
    schtasks /Create /TN 'HRM-Startup' /XML (Join-Path $Root 'production\HRM-Startup.xml') /F | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'HRM-Startup registration failed.' }
    $s = Get-ScheduledTask -TaskName 'HRM-Startup' -ErrorAction Stop
    if ($s.State -eq 'Disabled') { Enable-ScheduledTask -TaskName 'HRM-Startup' | Out-Null }
    Write-SetupLog "HRM-Startup registered and enabled (State=$((Get-ScheduledTask -TaskName 'HRM-Startup').State))." 'Green'

    # Legacy removal is now safe (replacement verified above).
    schtasks /Delete /TN 'HRM AutoStart' /F | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not delete legacy HRM AutoStart task.' }
    Write-SetupLog 'Legacy HRM AutoStart (logon/interactive) DELETED. Backup kept.' 'Green'

    # --- Step 3: retire the orphan biometrics task (now supervised) ---
    schtasks /query /tn 'HRM-temp-biometrics-worker' /xml > (Join-Path $BackupDir 'HRM-temp-biometrics-worker-backup.xml') 2>$null
    Disable-ScheduledTask -TaskName 'HRM-temp-biometrics-worker' | Out-Null
    Write-SetupLog 'HRM-temp-biometrics-worker DISABLED (worker is now supervised; delete task after 1 stable week).' 'Green'
}
catch {
    $failed = 1
    Write-SetupLog "FATAL: $($_.Exception.Message)" 'Red'
}

if ($failed -eq 0) {
    Write-SetupLog '=== HRM admin unification COMPLETE. Backups + full log kept. ===' 'Cyan'
    Write-SetupLog "Backup dir: $BackupDir" 'Gray'
    Write-SetupLog 'Next: launch the new supervisor once (Start-HRM-Windows.bat -NonInteractive -SkipBuild).' 'Gray'
}
exit $failed
