#Requires -Version 5.1
<#
.SYNOPSIS
    HRM headless production launcher (011/SERVER).

.DESCRIPTION
    Runs the full HRM stack with no console and no interaction, for Task
    Scheduler ("At startup", SYSTEM, hidden) or any standalone server.

    The supervisor is invoked IN THIS PROCESS (call operator, not
    Start-Process) so stopping the task kills the supervisor, which closes
    its Job Object and cleanly terminates every child service. A detached
    child would orphan the whole stack on task stop -- hence no Start-Process.

    Logs start/failure to storage\logs\hrm-startup.log. Interactive
    developers keep using scripts\Start-HRM-Windows.bat.
#>

[CmdletBinding()]
param(
    [switch] $SkipBuild,
    [switch] $NoBridge
)

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$LogFile = Join-Path $Root 'storage\logs\hrm-startup.log'

# PYTHON* hygiene (mirrors the supervisor): runs headless under SYSTEM where
# third-party PYTHONHOME pollution would otherwise break the venv children.
Remove-Item Env:\PYTHONHOME -ErrorAction SilentlyContinue
Remove-Item Env:\PYTHONPATH -ErrorAction SilentlyContinue
Remove-Item Env:\PYTHONIOENCODING -ErrorAction SilentlyContinue

function Write-StartupLog([string] $message) {
    $line = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $message"
    Add-Content -LiteralPath $LogFile -Value $line -Encoding UTF8
}

$supervisor = Join-Path $Root 'scripts\Start-HRM-Windows.ps1'
$flags = @()
if ($SkipBuild) { $flags += '-SkipBuild' }
if ($NoBridge) { $flags += '-NoBridge' }

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
Write-StartupLog "Starting HRM stack headless in-process (SkipBuild=$($SkipBuild.IsPresent), Elevated=$isAdmin)."

# Redis is queue infrastructure (QUEUE_CONNECTION=redis) — like MySQL it
# lives OUTSIDE the supervisor Job Object so a task/supervisor restart never
# drops the queue. Idempotent: if the hrm-redis service (or anything else)
# already listens on 6379, this is a no-op.
try {
    $redisUp = Test-NetConnection -ComputerName '127.0.0.1' -Port 6379 -WarningAction SilentlyContinue | Select-Object -ExpandProperty TcpTestSucceeded
}
catch {
    $redisUp = $false
}
if (-not $redisUp) {
    # Discover the bundled redis dynamically: Laragon upgrades change the
    # versioned folder name, so a hardcoded path rots on new machines.
    $redisExe = $null
    $redisConf = $null
    $rFound = Get-ChildItem 'C:\laragon\bin\redis\*\redis-server.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($rFound) {
        $redisExe = $rFound.FullName
        foreach ($cf in @('redis.windows.conf', 'redis.conf')) {
            $cp = Join-Path $rFound.Directory.FullName $cf
            if (Test-Path -LiteralPath $cp) { $redisConf = $cp; break }
        }
    }
    if (($redisExe) -and ($redisConf)) {
        Write-StartupLog 'Redis 6379 not listening — starting redis-server detached.'
        Start-Process -FilePath $redisExe -ArgumentList $redisConf -WindowStyle Hidden
        Start-Sleep -Seconds 3
    }
    else {
        Write-StartupLog 'FATAL: no redis-server.exe under C:\laragon\bin\redis\. Queue (redis) cannot start.'
        exit 1
    }
}
else {
    Write-StartupLog 'Redis 6379 already listening — nothing to do.'
}

try {
    & $supervisor -NonInteractive @flags
    $code = $LASTEXITCODE
    Write-StartupLog "Supervisor exited with code $code."
    exit $code
}
catch {
    Write-StartupLog "FATAL: $($_.Exception.Message)"
    exit 1
}
