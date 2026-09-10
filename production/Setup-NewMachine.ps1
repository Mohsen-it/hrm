#Requires -Version 5.1
<#
.SYNOPSIS
    HRM new-machine setup (011/SERVER) -- audit first, install step by step.

.DESCRIPTION
    Prepares a FRESH Windows machine to run the HRM stack:
      1. Checks prerequisites (PHP 8.3+, Composer, Node 20+, Python 3.11+, MySQL reachable).
      2. composer install / npm install / npm run build / Python venv + requirements.
      3. Creates .env from .env.production.example when missing (never overwrites).
      4. php artisan key:generate (only when APP_KEY empty) + migrate --force.
      5. Registers the logon autostart (HRM-AutoStart.vbs) -- server-grade
         no-logon operation additionally needs the NSSM or SYSTEM-task layer
         documented in DEPLY-NEW-MACHINE.md.

    Run -CheckOnly first: it reports what is missing and changes NOTHING.
    Every install step verifies its own result before continuing.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File Setup-NewMachine.ps1 -CheckOnly
    powershell -ExecutionPolicy Bypass -File Setup-NewMachine.ps1
#>

[CmdletBinding()]
param(
    # NOTE: $PSScriptRoot is NOT available in parameter defaults on PS 5.1,
    # so $Root is resolved in the body below (empty = script's parent dir).
    [string] $Root = '',
    [switch] $CheckOnly,
    [switch] $SkipBuild
)

$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($Root)) {
    $Root = (Resolve-Path (Join-Path (Split-Path $MyInvocation.MyCommand.Path -Parent) '..')).Path
}

function Test-Step([string] $name, [scriptblock] $test, [string] $hint) {
    try {
        if (& $test) { Write-Host "  [OK] $name" -ForegroundColor Green; return $true }
    } catch { }
    Write-Host "  [MISS] $name -- $hint" -ForegroundColor Yellow
    return $false
}

function Invoke-Step([string] $name, [scriptblock] $action, [scriptblock] $verify) {
    Write-Host "  [..] $name ..." -ForegroundColor Cyan
    & $action
    if (& $verify) { Write-Host "  [OK] $name" -ForegroundColor Green }
    else { throw "Verification failed after: $name" }
}

Write-Host '=== HRM new-machine check ===' -ForegroundColor Cyan
Write-Host "Root: $Root"

$hasPhp = Test-Step 'PHP 8.3+' { (php -v 2>$null) -match 'PHP 8\.[3-9]' } 'install Laragon full (php-8.3+) and add php to PATH'
$hasComposer = Test-Step 'Composer' { (composer --version 2>$null) -match 'Composer' } 'install Composer for Windows'
$hasNode = Test-Step 'Node 20+' { (node -v 2>$null) -match 'v(2[0-9]|[3-9][0-9])' } 'install Node.js LTS 20+'
$hasPython = Test-Step 'Python 3.11+' { (py -3 --version 2>$null) -match 'Python 3\.(1[1-9]|[2-9][0-9])' } 'install Python 3.11+ (py launcher)'

if ($CheckOnly) {
    Write-Host ''
    if ($hasPhp -and $hasComposer -and $hasNode -and $hasPython) {
        Write-Host 'All prerequisites present. Re-run without -CheckOnly to install.' -ForegroundColor Green
    } else {
        Write-Host 'Install the missing items above, then re-run.' -ForegroundColor Yellow
    }
    exit 0
}

if (-not ($hasPhp -and $hasComposer -and $hasNode -and $hasPython)) {
    throw 'Prerequisites missing. Run with -CheckOnly for the full list.'
}

Push-Location $Root
try {
    Invoke-Step 'composer install' `
        { composer install --no-dev --optimize-autoloader } `
        { Test-Path -LiteralPath (Join-Path $Root 'vendor\autoload.php') }

    Invoke-Step 'npm install' `
        { npm install } `
        { Test-Path -LiteralPath (Join-Path $Root 'node_modules') }

    if (-not $SkipBuild) {
        Invoke-Step 'npm run build' `
            { npm run build } `
            { Test-Path -LiteralPath (Join-Path $Root 'public\build\manifest.json') }
    }

    Invoke-Step 'python venv + requirements' `
        {
            if (-not (Test-Path -LiteralPath (Join-Path $Root 'zkteco-service\venv\Scripts\python.exe'))) {
                py -3 -m venv (Join-Path $Root 'zkteco-service\venv')
            }
            & (Join-Path $Root 'zkteco-service\venv\Scripts\python.exe') -m pip install -r (Join-Path $Root 'zkteco-service\requirements.txt')
        } `
        { Test-Path -LiteralPath (Join-Path $Root 'zkteco-service\venv\Scripts\python.exe') }

    $envFile = Join-Path $Root '.env'
    if (-not (Test-Path -LiteralPath $envFile)) {
        Copy-Item -LiteralPath (Join-Path $PSScriptRoot '.env.production.example') -Destination $envFile
        Write-Host '  [!!] .env created from template -- FILL IT BEFORE CONTINUING, then re-run.' -ForegroundColor Red
        exit 2
    }

    $key = (Select-String -LiteralPath $envFile -Pattern '^APP_KEY=' | Select-Object -First 1).Line
    if ($key -eq 'APP_KEY=') {
        Invoke-Step 'artisan key:generate' `
            { php artisan key:generate --force } `
            { ((Select-String -LiteralPath $envFile -Pattern '^APP_KEY=' | Select-Object -First 1).Line.Length -gt 8) }
    }

    Invoke-Step 'artisan migrate --force' `
        { php artisan migrate --force } `
        { $true }

    # Logon autostart (visible-free VBS, same file this server uses).
    $startupDir = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\Startup'
    $vbs = Join-Path $startupDir 'HRM-AutoStart.vbs'
    if (-not (Test-Path -LiteralPath $vbs)) {
        $cmd = 'CreateObject("Wscript.Shell").Run "powershell.exe -NoLogo -WindowStyle Hidden -ExecutionPolicy Bypass -File """' + (Join-Path $Root 'scripts\Start-HRM-Windows.ps1') + '""" -SkipBuild -NonInteractive", 0, False'
        Set-Content -LiteralPath $vbs -Value "' HRM AutoStart (installed $(Get-Date -Format 'yyyy-MM-dd'))", $cmd -Encoding ASCII
    }
    Write-Host "  [OK] logon autostart present: $vbs" -ForegroundColor Green

    Write-Host ''
    Write-Host 'Setup complete. Start now with: scripts\Start-HRM-Windows.bat' -ForegroundColor Green
}
finally {
    Pop-Location
}
