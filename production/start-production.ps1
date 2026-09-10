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
