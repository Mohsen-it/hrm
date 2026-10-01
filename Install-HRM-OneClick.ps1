#Requires -Version 5.1
<#
.SYNOPSIS
    HRM one-click full installer - installs everything in order on a new Windows machine.

.DESCRIPTION
    Covers the FULL stack found in this repo:
      PHP 8.3+ (Laragon) + Composer 2.x
      Laravel 13.8 + 17 modules (nwidart/laravel-modules 12.0) + 128 migrations + seeders
      Node 20+ + npm + Vite 8 + Vue 3.5 + Tailwind 4 (npm install + npm run build)
      Python 3.11+ venv + Flask 3.0 bridge (:5000) + ADMS server (:8081)
      MySQL 8.x (hrmair, auto-created) or SQLite fallback + Redis 6379 (queue/cache)
      Reverb 8080 keys + backup encryption key (auto-generated when empty)
      Scheduler + watchdog tasks + firewall rules + logon autostart + health checks
      Fresh-Windows hardening: Laragon-PHP recovery, php.ini extension
      enablement, long paths, connectivity probes, stale-IP guard

    Correct ordering guarantee: .env is created and all secrets/keys are
    finalized BEFORE `npm run build`, because Vite bakes VITE_* values into
    the frontend at build time.

    Run -CheckOnly first (changes nothing). Full run installs step by step,
    each step self-verifies before continuing. Safe to re-run (idempotent).

    ASCII-ONLY file by project rule (012/STABILITY): never add non-ASCII chars.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File Install-HRM-OneClick.ps1 -CheckOnly
.EXAMPLE
    powershell -ExecutionPolicy Bypass -File Install-HRM-OneClick.ps1
.EXAMPLE
    powershell -ExecutionPolicy Bypass -File Install-HRM-OneClick.ps1 -Production -Seed -StartAfter -AutoInstall -ServerIp 10.10.250.2
#>

[CmdletBinding()]
param(
    [string] $Root = '',
    [switch] $CheckOnly,
    [switch] $SkipBuild,
    [switch] $SkipSeed,
    [switch] $Seed,
    [switch] $Production,
    [switch] $AutoInstall,
    [switch] $StartAfter,
    [switch] $NonInteractive,
    [ValidateSet('auto', 'sqlite', 'mysql')]
    [string] $DbMode = 'auto',
    [string] $ServerIp = '',
    [switch] $Help
)

$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($Root)) {
    $Root = (Resolve-Path (Join-Path (Split-Path $MyInvocation.MyCommand.Path -Parent) '.')).Path
}

if ($Help) {
    Write-Host 'Usage: Install-HRM-OneClick.ps1 [-CheckOnly] [-SkipBuild] [-Seed] [-SkipSeed]'
    Write-Host '       [-Production] [-DbMode auto|sqlite|mysql] [-ServerIp 10.10.250.2]'
    Write-Host '       [-AutoInstall] [-StartAfter] [-NonInteractive]'
    Write-Host ''
    Write-Host '  -CheckOnly     : audit only, change nothing (run first on a new machine)'
    Write-Host '  -SkipBuild     : skip npm run build'
    Write-Host '  -Seed          : run php artisan db:seed --force after migrate'
    Write-Host '  -Production    : composer install --no-dev + production template hints'
    Write-Host '  -DbMode        : force sqlite or mysql (default auto = keep .env, fallback sqlite)'
    Write-Host '  -ServerIp      : write APP_URL + VITE_REVERB_HOST with this LAN IP'
    Write-Host '  -AutoInstall   : try winget install for missing Git/Node/Python (needs admin + internet)'
    Write-Host '  -StartAfter    : launch services after install (scripts\Start-HRM-Windows.ps1)'
    Write-Host '  -NonInteractive: never prompt, use defaults (for fresh-machine copy-paste runs)'
    exit 0
}

# ---------------- logging ----------------
$LogDir = Join-Path $Root 'storage\logs'
try { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null } catch {}
$LogFile = Join-Path $LogDir ('hrm-install-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
try { Start-Transcript -Path $LogFile -Append | Out-Null } catch {}

function Write-Info([string] $m) { Write-Host "  [..] $m" -ForegroundColor Cyan }
function Write-Ok([string] $m) { Write-Host "  [OK] $m" -ForegroundColor Green }
function Write-Warn([string] $m) { Write-Host "  [!!] $m" -ForegroundColor Yellow }
function Write-Err([string] $m) { Write-Host "  [FAIL] $m" -ForegroundColor Red }

function Test-Tcp([string] $Computer, [int] $Port, [int] $Ms = 1500) {
    $c = $null
    try {
        $c = New-Object Net.Sockets.TcpClient
        $iar = $c.BeginConnect($Computer, $Port, $null, $null)
        if ($iar.AsyncWaitHandle.WaitOne($Ms)) { $c.EndConnect($iar); return $true }
    } catch {} finally { if ($c) { try { $c.Close() } catch {} } }
    return $false
}

function Invoke-Step([string] $name, [scriptblock] $action, [scriptblock] $verify) {
    Write-Info "$name ..."
    & $action
    if (& $verify) { Write-Ok $name; return }
    throw "Verification failed after step: $name. See log: $LogFile"
}

function Install-WithWinget([string] $id, [string] $label) {
    Write-Info "Installing $label via winget ($id) ..."
    & winget install --id $id -e --silent --accept-package-agreements --accept-source-agreements
    if ($LASTEXITCODE -ne 0) { throw "winget install failed for $label ($id)" }
    Write-Ok "$label installed (restart shell may be needed for PATH)"
}

# --- .env helpers (all values we write are plain ASCII: hex, urls, ips) ---
function Get-DotEnvValue([string] $File, [string] $Key) {
    $m = Select-String -LiteralPath $File -Pattern ('^' + [regex]::Escape($Key) + '=(.*)$') | Select-Object -First 1
    if ($m) { return $m.Matches[0].Groups[1].Value.Trim() }
    return ''
}

function Set-DotEnvValue([string] $File, [string] $Key, [string] $Value) {
    $lines = @(Get-Content -LiteralPath $File)
    $done = $false
    $pat = '^' + [regex]::Escape($Key) + '=.*$'
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match $pat) { $lines[$i] = $Key + '=' + $Value; $done = $true }
    }
    if (-not $done) { $lines += ($Key + '=' + $Value) }
    # UTF-8 WITHOUT BOM: a BOM prefix would corrupt the first key for dotenv
    # parsers, and -Encoding ASCII would corrupt any non-ASCII comment.
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllLines($File, $lines, $utf8NoBom)
}

function Get-LanIp {
    try {
        $cands = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -ne '127.0.0.1' } | Select-Object -ExpandProperty IPAddress
        $lan = $cands | Where-Object { $_ -match '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)' } | Select-Object -First 1
        if ($lan) { return $lan }
    } catch {}
    return '127.0.0.1'
}

# Creates the MySQL database when missing, via PHP PDO (no mysql.exe needed in
# PATH). Credentials travel in process-only env vars so they never hit the log
# (the transcript records the php -r CODE, which contains no secrets).
function Ensure-MySqlDatabase([string] $EnvFile) {
    $h = Get-DotEnvValue $EnvFile 'DB_HOST'; if ([string]::IsNullOrWhiteSpace($h)) { $h = '127.0.0.1' }
    $port = Get-DotEnvValue $EnvFile 'DB_PORT'; if ([string]::IsNullOrWhiteSpace($port)) { $port = '3306' }
    $user = Get-DotEnvValue $EnvFile 'DB_USERNAME'; if ([string]::IsNullOrWhiteSpace($user)) { $user = 'root' }
    $pass = Get-DotEnvValue $EnvFile 'DB_PASSWORD'
    $name = Get-DotEnvValue $EnvFile 'DB_DATABASE'; if ([string]::IsNullOrWhiteSpace($name)) { $name = 'hrm' }
    $env:HRM_DB_HOST = $h
    $env:HRM_DB_PORT = $port
    $env:HRM_DB_USER = $user
    $env:HRM_DB_PASS = $pass
    $env:HRM_DB_NAME = $name
    # The PHP snippet runs from a temp FILE (not `php -r`): Windows PowerShell
    # 5.1 mangles inner double quotes when passing `-r` code as an argument,
    # which silently breaks the command. A file sidesteps quoting entirely.
    $phpCode = @'
<?php
$c = new PDO('mysql:host='.getenv('HRM_DB_HOST').';port='.getenv('HRM_DB_PORT'), getenv('HRM_DB_USER'), getenv('HRM_DB_PASS'), array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
$c->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`','``',getenv('HRM_DB_NAME')).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo 'db-ok';
'@
    $tmpPhp = Join-Path ([System.IO.Path]::GetTempPath()) ('hrm-ensuredb-' + [Guid]::NewGuid().ToString('N') + '.php')
    Set-Content -LiteralPath $tmpPhp -Value $phpCode -Encoding ASCII
    $out = ''
    try { $out = (& php $tmpPhp | Out-String) } catch { $out = '' }
    try { Remove-Item -LiteralPath $tmpPhp -Force -ErrorAction SilentlyContinue } catch {}
    Remove-Item Env:\HRM_DB_HOST -ErrorAction SilentlyContinue
    Remove-Item Env:\HRM_DB_PORT -ErrorAction SilentlyContinue
    Remove-Item Env:\HRM_DB_USER -ErrorAction SilentlyContinue
    Remove-Item Env:\HRM_DB_PASS -ErrorAction SilentlyContinue
    Remove-Item Env:\HRM_DB_NAME -ErrorAction SilentlyContinue
    return ($out -match 'db-ok')
}

Write-Host ''
Write-Host '============================================================' -ForegroundColor Cyan
Write-Host ' HRM one-click installer - full stack (Laravel + Vue + Py)' -ForegroundColor Cyan
Write-Host " Root: $Root" -ForegroundColor Cyan
Write-Host " Log : $LogFile" -ForegroundColor DarkGray
Write-Host '============================================================' -ForegroundColor Cyan

# ---------------- 0. environment ----------------
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if ($isAdmin) { Write-Ok 'Running elevated (Administrator)' } else { Write-Warn 'NOT elevated - scheduler/autostart/firewall registration will be skipped. Right-click Run as administrator for a new machine.' }

$os = Get-CimInstance Win32_OperatingSystem -ErrorAction SilentlyContinue
if ($os) { Write-Host "  OS: $($os.Caption) ($($os.OSArchitecture))" -ForegroundColor DarkGray }

try {
    $disk = Get-PSDrive -Name ($Root.Substring(0, 1)) -ErrorAction Stop
    $freeGB = [math]::Round($disk.Free / 1GB, 1)
    if ($freeGB -lt 5) { Write-Warn "Low disk space: ${freeGB}GB free on $($Root.Substring(0,1)): (need 5GB+)" } else { Write-Ok "Disk space: ${freeGB}GB free" }
} catch { Write-Warn 'Could not check disk space' }

# merge Machine+User PATH entries into this session without dropping process entries
try { $env:Path = $env:Path + ';' + [System.Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path', 'User') } catch {}

# ---------------- 1. prerequisites ----------------
Write-Host ''
Write-Host '--- Step 1/10: prerequisites ---' -ForegroundColor Cyan

$checks = @()

# NOTE on output capture below: several tools (composer.bat, python warnings,
# schtasks) print to STDERR during NORMAL operation. Merging STDERR with
# `2>&1` turns those lines into terminating errors under
# $ErrorActionPreference=Stop, so we capture STDOUT only and let STDERR
# flow to the console (harmless display, never throws).
$phpOk = $false
$pv = ''
try { $pv = (& php -v | Out-String) } catch { $pv = '' }
if ($pv -match 'PHP 8\.(3|4|5)') { $phpOk = $true; Write-Ok "PHP: $($pv.Split([Environment]::NewLine)[0].Trim())" }
elseif ($pv.Trim().Length -gt 0) { Write-Warn "PHP version not 8.3+: $($pv.Split([Environment]::NewLine)[0])" }
else { Write-Warn 'PHP missing - install Laragon Full (php-8.3+) and add php to PATH' }
$checks += @{ Name = 'PHP 8.3+'; Ok = $phpOk; Hint = 'Install Laragon Full: https://laragon.org/download/ then add C:\laragon\bin\php\php-8.3-*\ to PATH' }

$composerOk = $false
$composerCmd = $null
try {
    $w = (& where.exe composer.bat | Out-String)
    # where.exe lists BOTH `composer` (git-bash shell script) and `composer.bat`;
    # we must pick the .bat line, never the extensionless one.
    $batLine = ($w.Split([Environment]::NewLine) | Where-Object { $_ -match 'composer\.bat' } | Select-Object -First 1)
    if ($batLine) { $composerCmd = $batLine.Trim() }
} catch {}
if ([string]::IsNullOrWhiteSpace($composerCmd)) { $composerCmd = 'composer.bat' }
$cv = ''
try { $cv = (& $composerCmd --version | Out-String) } catch { $cv = '' }
if ($cv -match 'Composer') { $composerOk = $true; Write-Ok "Composer: $($cv.Trim().Split([Environment]::NewLine)[0])" }
if (-not $composerOk) { Write-Warn 'Composer missing' }
$checks += @{ Name = 'Composer 2.x'; Ok = $composerOk; Hint = 'Install Composer for Windows: https://getcomposer.org/download/' }

$nodeOk = $false
$nv = ''
try { $nv = ((& node -v | Out-String).Trim()) } catch { $nv = '' }
if ($nv -match 'v(2[0-9]|[3-9][0-9])') { $nodeOk = $true; Write-Ok "Node: $nv" }
elseif ($nv.Length -gt 0) { Write-Warn "Node too old ($nv) - need v20+" }
else { Write-Warn 'Node.js missing - need v20 LTS+' }
$checks += @{ Name = 'Node 20+'; Ok = $nodeOk; Hint = 'Install Node.js 20 LTS: https://nodejs.org/ (winget: OpenJS.NodeJS.LTS)' }

$npmOk = $false
$nmv = ''
try { $nmv = ((& npm -v | Out-String).Trim()) } catch { $nmv = '' }
if ($nmv -match '^\d+') { $npmOk = $true; Write-Ok "npm: $nmv" }
else { Write-Warn 'npm missing (comes with Node)' }
$checks += @{ Name = 'npm'; Ok = $npmOk; Hint = 'Reinstall Node.js LTS (npm is bundled)' }

$pyOk = $false
$pyv = ''
try { $pyv = ((& py -3 --version | Out-String).Trim()) } catch { $pyv = '' }
if ($pyv -match 'Python 3\.(1[1-9]|[2-9][0-9])') { $pyOk = $true; Write-Ok "Python: $pyv" }
elseif ($pyv.Length -gt 0) { Write-Warn "Python too old ($pyv) - need 3.11+" }
else { Write-Warn 'Python missing - need 3.11+ with py launcher' }
$checks += @{ Name = 'Python 3.11+'; Ok = $pyOk; Hint = 'Install Python 3.12 from python.org (tick Add to PATH + py launcher) (winget: Python.Python.3.12)' }

$gitOk = $false
$gv = ''
try { $gv = ((& git --version | Out-String).Trim()) } catch { $gv = '' }
if ($gv -match 'git version') { $gitOk = $true; Write-Ok "Git: $gv" }
else { Write-Warn 'Git missing (optional but recommended)' }

$mysqlUp = Test-Tcp '127.0.0.1' 3306
$mysqlSvc = Get-Service 'HRM-MySQL' -ErrorAction SilentlyContinue
$laragonMysql = Get-ChildItem 'C:\laragon\bin\mysql\*\bin\mysqld.exe' -ErrorAction SilentlyContinue | Select-Object -First 1
if ($mysqlUp) { Write-Ok 'MySQL: port 3306 reachable' }
elseif ($mysqlSvc) { Write-Warn "MySQL: service HRM-MySQL exists but not listening (status: $($mysqlSvc.Status)) - will try to start it" }
elseif ($laragonMysql) { Write-Warn "MySQL: Laragon mysqld found at $($laragonMysql.FullName) but not running - start Laragon or the HRM-MySQL service" }
else { Write-Warn 'MySQL: not detected (SQLite fallback will be used unless you install MySQL 8.x / Laragon)' }

$redisUp = Test-Tcp '127.0.0.1' 6379
$redisSvc = Get-Service 'hrm-redis' -ErrorAction SilentlyContinue
$laragonRedisExe = 'C:\laragon\bin\redis\redis-x64-5.0.14.1\redis-server.exe'
$laragonRedisConf = 'C:\laragon\bin\redis\redis-x64-5.0.14.1\redis.windows.conf'
$laragonRedis = Test-Path -LiteralPath $laragonRedisExe
if ($redisUp) { Write-Ok 'Redis: port 6379 reachable' }
elseif ($redisSvc) { Write-Warn "Redis: service hrm-redis exists (status: $($redisSvc.Status)) - will try to start it" }
elseif ($laragonRedis) { Write-Warn 'Redis: Laragon redis-server.exe found but not running - will try to start it' }
else { Write-Warn 'Redis: not detected - queue/cache/session fall back to database/file automatically' }

if ($AutoInstall) {
    Write-Host ''
    Write-Host '--- AutoInstall via winget ---' -ForegroundColor Cyan
    try { & winget --version | Out-Null } catch { throw 'winget not available - install App Installer from Microsoft Store first.' }
    if (-not $gitOk) { try { Install-WithWinget 'Git.Git' 'Git' } catch { Write-Warn $_.Exception.Message } }
    if (-not $nodeOk) { try { Install-WithWinget 'OpenJS.NodeJS.LTS' 'Node.js LTS' } catch { Write-Warn $_.Exception.Message } }
    if (-not $pyOk) { try { Install-WithWinget 'Python.Python.3.12' 'Python 3.12' } catch { Write-Warn $_.Exception.Message } }
    if (-not $composerOk) { try { Install-WithWinget 'Composer.Composer' 'Composer' } catch { Write-Warn $_.Exception.Message } }
    $env:Path = $env:Path + ';' + [System.Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path', 'User')
    # Winget installers only take effect for NEW processes; re-probe inside
    # this session so freshly installed tools are picked up without re-run.
    Write-Info 're-probing tools after AutoInstall ...'
    try { $gv2 = ((& git --version | Out-String).Trim()) } catch { $gv2 = '' }
    if ($gv2 -match 'git version') { $gitOk = $true; Write-Ok "Git: $gv2" }
    try { $nv2 = ((& node -v | Out-String).Trim()) } catch { $nv2 = '' }
    if ($nv2 -match 'v(2[0-9]|[3-9][0-9])') { $nodeOk = $true; $npmOk = $true; ($checks | Where-Object { $_.Name -eq 'Node 20+' }).Ok = $true; ($checks | Where-Object { $_.Name -eq 'npm' }).Ok = $true; Write-Ok "Node: $nv2" }
    try { $pyv2 = ((& py -3 --version | Out-String).Trim()) } catch { $pyv2 = '' }
    if ($pyv2 -match 'Python 3\.(1[1-9]|[2-9][0-9])') { $pyOk = $true; ($checks | Where-Object { $_.Name -eq 'Python 3.11+' }).Ok = $true; Write-Ok "Python: $pyv2" }
    try {
        $w2 = (& where.exe composer.bat | Out-String)
        $bat2 = ($w2.Split([Environment]::NewLine) | Where-Object { $_ -match 'composer\.bat' } | Select-Object -First 1)
        if ($bat2) { $composerCmd = $bat2.Trim() }
    } catch {}
    $cv2 = ''
    try { $cv2 = (& $composerCmd --version | Out-String) } catch { $cv2 = '' }
    if ($cv2 -match 'Composer') { $composerOk = $true; ($checks | Where-Object { $_.Name -eq 'Composer 2.x' }).Ok = $true; Write-Ok "Composer: $($cv2.Trim().Split([Environment]::NewLine)[0])" }
    Write-Warn 'If PATH still misses new tools, CLOSE this window, open a new one, and re-run the installer.'
}

$missing = @($checks | Where-Object { -not $_.Ok })
if ($CheckOnly) {
    Write-Host ''
    Write-Host '--- integration status (informational, fixed by a full run as admin) ---' -ForegroundColor Cyan
    foreach ($p in @(8000, 8080, 8081, 5000)) {
        try {
            if (Get-NetFirewallRule -DisplayName "HRM-Allow-$p" -ErrorAction Stop) { Write-Ok "firewall rule HRM-Allow-$p present" }
        } catch { Write-Warn "firewall rule HRM-Allow-$p missing (TCP $p inbound for LAN/devices)" }
    }
    foreach ($t in @('HRM Scheduler', 'HRM Queue Worker Watchdog')) {
        $tp = ''
        try { $tp = (& schtasks /query /tn $t | Out-String) } catch { $tp = '' }
        if ($tp -match $t) { Write-Ok "task present: $t" } else { Write-Warn "task missing: $t" }
    }
    Write-Host ''
    if ($missing.Count -eq 0) { Write-Ok 'All hard prerequisites present. Re-run without -CheckOnly to install.' }
    else {
        Write-Warn 'Missing prerequisites:'
        foreach ($m in $missing) { Write-Host "    - $($m.Name): $($m.Hint)" -ForegroundColor Yellow }
        Write-Host '  Re-run with -AutoInstall to try winget for Git/Node/Python.' -ForegroundColor DarkGray
    }
    try { Stop-Transcript | Out-Null } catch {}
    if ($missing.Count -eq 0) { exit 0 } else { exit 1 }
}

# Laragon ships PHP but often leaves it out of PATH. Recover it automatically:
# use the newest Laragon PHP for this session and persist it to User PATH.
if (-not $phpOk) {
    $laraPhp = Get-ChildItem 'C:\laragon\bin\php\*\php.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($laraPhp) {
        $env:Path = $laraPhp.Directory.FullName + ';' + $env:Path
        Write-Ok "Laragon PHP found: $($laraPhp.FullName) (session PATH updated)"
        try {
            $userPath = [System.Environment]::GetEnvironmentVariable('Path', 'User')
            if ($userPath -notmatch [regex]::Escape($laraPhp.Directory.FullName)) {
                $newUserPath = $laraPhp.Directory.FullName + ';' + $userPath
                if ($newUserPath.Length -le 1000) {
                    [System.Environment]::SetEnvironmentVariable('Path', $newUserPath, 'User')
                    Write-Ok 'Laragon PHP persisted to User PATH (new windows will see it)'
                } else { Write-Warn 'User PATH too long for setx-style persist - add Laragon php dir manually (session still works)' }
            }
        } catch { Write-Warn "could not persist User PATH: $($_.Exception.Message) (session still works)" }
        $pv = ''
        try { $pv = (& php -v | Out-String) } catch { $pv = '' }
        if ($pv -match 'PHP 8\.(3|4|5)') { $phpOk = $true; ($checks | Where-Object { $_.Name -eq 'PHP 8.3+' }).Ok = $true; Write-Ok "PHP: $($pv.Split([Environment]::NewLine)[0].Trim())" }
        else { Write-Warn 'Laragon PHP found but version is not 8.3+ - upgrade Laragon' }
    }
}

if (-not ($phpOk -and $composerOk -and $nodeOk -and $pyOk)) {
    Write-Err 'Hard prerequisites missing (PHP/Composer/Node/Python). Run with -CheckOnly for details, or -AutoInstall to try winget.'
    throw 'Prerequisites missing - aborting.'
}

# Fresh-Windows hardening: long paths (git/npm/composer break on deep
# node_modules without it) + install-path sanity + download connectivity.
if ($Root -match '\s') { Write-Warn "install path contains SPACES ($Root) - composer/npm/python venv are safest under a short ASCII path like D:\hrm" }
if ($Root -match '[^\x00-\x7F]') { Write-Warn 'install path contains non-ASCII characters - move the project to a plain ASCII path like D:\hrm to avoid tool breakage' }
if ($isAdmin) {
    try {
        $lp = Get-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem' -Name 'LongPathsEnabled' -ErrorAction Stop
        if ($lp.LongPathsEnabled -ne 1) {
            Set-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem' -Name 'LongPathsEnabled' -Value 1 -ErrorAction Stop
            Write-Ok 'Windows long paths enabled (deep node_modules/vendor safe)'
        } else { Write-Ok 'Windows long paths already enabled' }
    } catch { Write-Warn "could not enable long paths: $($_.Exception.Message) (non-fatal)" }
} else { Write-Warn 'LongPathsEnabled not verified (need admin) - deep paths may fail on fresh Windows' }
foreach ($h in @('repo.packagist.org', 'registry.npmjs.org', 'pypi.org')) {
    if (Test-Tcp $h 443 3000) { Write-Ok "internet: ${h}:443 reachable" }
    else { Write-Warn "internet: ${h}:443 UNREACHABLE - composer/npm/pip downloads need it (proxy/firewall?)" }
}

# PHP extensions audit (warn only, never fatal except pdo)
Write-Host ''
Write-Host '--- PHP extensions ---' -ForegroundColor Cyan
$needExt = @('pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'curl', 'fileinfo', 'zip', 'gd', 'intl')
$loadedMods = ''
try { $loadedMods = ((& php -m | Out-String).ToLower()) } catch { $loadedMods = '' }
$missingExt = @()
foreach ($e in $needExt) {
    if ($loadedMods -match "(?m)^\s*$e\s*$") { Write-Host "  [OK] php-$e" -ForegroundColor Green }
    else {
        $missingExt += $e
        if ($e -in @('pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl')) { Write-Err "php-$e MISSING (required)" }
        else {
            $iniPath = ''
            try { $iniPath = ((& php --ini | Out-String).Split([Environment]::NewLine) | Where-Object { $_ -match 'Loaded Configuration File' } | Select-Object -First 1).Trim() } catch {}
            Write-Warn "php-$e missing (recommended - enable extension=$e in $iniPath)"
        }
    }
}

# Try to enable missing extensions in the LOADED php.ini (CLI picks the change
# up immediately, no restart). Backup first, only uncomment when the DLL
# exists, then re-probe. Never fatal: worst case the warnings above stand.
if ($missingExt.Count -gt 0) {
    try {
        $iniLine = ((& php --ini | Out-String).Split([Environment]::NewLine) | Where-Object { $_ -match 'Loaded Configuration File' } | Select-Object -First 1)
        $iniFile = ''
        if ($iniLine -match ':\s*(.+)$') { $iniFile = $Matches[1].Trim() }
        $phpBin = ''
        try { $phpBin = (Get-Command php -ErrorAction Stop).Source } catch {}
        $extDir = ''
        if ($phpBin) { $extDir = Join-Path (Split-Path $phpBin -Parent) 'ext' }
        if (($iniFile -ne '') -and (Test-Path -LiteralPath $iniFile) -and ($extDir -ne '') -and (Test-Path -LiteralPath $extDir)) {
            $dllMap = @{ 'pdo_mysql' = 'php_pdo_mysql.dll'; 'pdo_sqlite' = 'php_pdo_sqlite.dll' }
            $iniBackedUp = $false
            foreach ($e in $missingExt) {
                $dll = "php_${e}.dll"
                if ($dllMap.ContainsKey($e)) { $dll = $dllMap[$e] }
                if (-not (Test-Path -LiteralPath (Join-Path $extDir $dll))) { continue }
                $iniText = Get-Content -LiteralPath $iniFile -Raw
                $pat = '(?m)^\s*;\s*extension\s*=\s*' + [regex]::Escape($dll.Replace('.dll', '')) + '\s*$'
                if ($iniText -match $pat) {
                    if (-not $iniBackedUp) {
                        try { New-Item -ItemType Directory -Path (Join-Path $Root 'backups') -Force | Out-Null } catch {}
                        Copy-Item -LiteralPath $iniFile -Destination (Join-Path $Root ('backups\php-ini-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.bak')) -Force
                        $iniBackedUp = $true
                    }
                    $iniText = [regex]::Replace($iniText, $pat, ('extension=' + $dll.Replace('.dll', '')))
                    $utf8Ini = New-Object System.Text.UTF8Encoding $false
                    [System.IO.File]::WriteAllText($iniFile, $iniText, $utf8Ini)
                    Write-Ok "php.ini: enabled extension=$($dll.Replace('.dll','')) (backup in backups\)"
                }
            }
            $reMods = ''
            try { $reMods = ((& php -m | Out-String).ToLower()) } catch { $reMods = '' }
            foreach ($e in $missingExt) {
                if ($reMods -match "(?m)^\s*$e\s*$") { Write-Ok "php-$e now loaded" }
            }
        }
    } catch { Write-Warn "php.ini auto-enable skipped: $($_.Exception.Message) (non-fatal)" }
}

# Try to start stopped-but-present infrastructure services (full run only).
if ($mysqlSvc -and ($mysqlSvc.Status -ne 'Running')) {
    try {
        Write-Info 'starting service HRM-MySQL ...'
        Start-Service -Name 'HRM-MySQL' -ErrorAction Stop
        Start-Sleep -Seconds 5
        if (Test-Tcp '127.0.0.1' 3306) { Write-Ok 'HRM-MySQL started (3306 listening)'; $mysqlUp = $true }
        else { Write-Warn 'HRM-MySQL start requested but 3306 still dark - MySQL may still be booting' }
    } catch { Write-Warn "could not start HRM-MySQL: $($_.Exception.Message)" }
}
if (-not (Test-Tcp '127.0.0.1' 6379)) {
    if ($redisSvc -and ($redisSvc.Status -ne 'Running')) {
        try {
            Write-Info 'starting service hrm-redis ...'
            Start-Service -Name 'hrm-redis' -ErrorAction Stop
            Start-Sleep -Seconds 3
            if (Test-Tcp '127.0.0.1' 6379) { Write-Ok 'hrm-redis started (6379 listening)'; $redisUp = $true }
        } catch { Write-Warn "could not start hrm-redis: $($_.Exception.Message)" }
    }
    if ((-not $redisUp) -and $laragonRedis -and (Test-Path -LiteralPath $laragonRedisConf)) {
        try {
            Write-Info 'starting Laragon redis-server detached ...'
            Start-Process -FilePath $laragonRedisExe -ArgumentList $laragonRedisConf -WindowStyle Hidden
            Start-Sleep -Seconds 3
            if (Test-Tcp '127.0.0.1' 6379) { Write-Ok 'Laragon redis-server started (6379 listening)'; $redisUp = $true }
        } catch { Write-Warn "could not start Laragon redis: $($_.Exception.Message)" }
    }
}

# ---------------- 2. .env (BEFORE build: Vite bakes VITE_* at build time) ----------------
Write-Host ''
Write-Host '--- Step 2/10: .env + database selection ---' -ForegroundColor Cyan
$envFile = Join-Path $Root '.env'
$envExample = Join-Path $Root '.env.example'
$envProdTpl = Join-Path $Root 'production\.env.production.example'
$envJustCreated = $false
if (-not (Test-Path -LiteralPath $envFile)) {
    if ($Production -and (Test-Path -LiteralPath $envProdTpl)) { Copy-Item -LiteralPath $envProdTpl -Destination $envFile; Write-Warn '.env created from production template.' }
    elseif (Test-Path -LiteralPath $envExample) { Copy-Item -LiteralPath $envExample -Destination $envFile; Write-Ok '.env created from .env.example' }
    else { throw '.env missing and no template found.' }
    $envJustCreated = $true
    if ($Production) {
        Write-Host ''
        Write-Err 'FIRST RUN: .env was just created from the production template.'
        Write-Host '  Fill these values in .env, then RE-RUN the installer:' -ForegroundColor Yellow
        Write-Host '    DB_DATABASE / DB_USERNAME / DB_PASSWORD, APP_URL (server LAN IP),' -ForegroundColor Yellow
        Write-Host '    VITE_REVERB_HOST (server LAN IP), MAIL_*, BACKUP_MYSQLDUMP_PATH / BACKUP_MYSQL_PATH' -ForegroundColor Yellow
        try { Stop-Transcript | Out-Null } catch {}
        exit 2
    }
} else { Write-Ok '.env already exists (never overwritten)' }

# Resolve YOUR-SERVER-IP placeholders (fresh production template).
$lanIpEarly = Get-LanIp
$wantedIp = $lanIpEarly
if (-not [string]::IsNullOrWhiteSpace($ServerIp)) { $wantedIp = $ServerIp.Trim() }
$envRaw = Get-Content -LiteralPath $envFile -Raw
if ($envRaw -match 'YOUR-SERVER-IP') {
    $envRaw = $envRaw -replace 'YOUR-SERVER-IP', $wantedIp
    $utf8NoBomEarly = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($envFile, $envRaw, $utf8NoBomEarly)
    Write-Ok "YOUR-SERVER-IP placeholders resolved to $wantedIp"
}
if (-not [string]::IsNullOrWhiteSpace($ServerIp)) {
    Set-DotEnvValue $envFile 'APP_URL' ("http://" + $wantedIp)
    Set-DotEnvValue $envFile 'VITE_REVERB_HOST' $wantedIp
    Write-Ok "APP_URL + VITE_REVERB_HOST set to http://$wantedIp (-ServerIp)"
}
# Stale-IP guard: a copied .env points at the OLD machine's LAN IP, which
# silently breaks generated links/password resets on the new network.
# NOTE: $Matches is clobbered by EVERY -match, so capture into variables first.
$auHost = ''
$appUrlNow = Get-DotEnvValue $envFile 'APP_URL'
if ($appUrlNow -match '^https?://([^/:]+)') { $auHost = $Matches[1] }
if (($auHost -match '^\d+\.\d+\.\d+\.\d+$') -and ($auHost -ne $lanIpEarly) -and ($auHost -ne '127.0.0.1')) {
    Write-Warn "APP_URL host ($auHost) differs from this machine LAN ($lanIpEarly) - re-run with -ServerIp $lanIpEarly to rewrite, or keep if intentional (DNS/reverse proxy)"
}
$viteHostNow = Get-DotEnvValue $envFile 'VITE_REVERB_HOST'
if (($viteHostNow -match '^\d+\.\d+\.\d+\.\d+$') -and ($viteHostNow -ne $lanIpEarly)) {
    Write-Warn "VITE_REVERB_HOST ($viteHostNow) differs from this machine LAN ($lanIpEarly) - browsers on LAN will fail realtime; re-run with -ServerIp $lanIpEarly to rewrite"
}

# DB mode decision
$conn = 'sqlite'
$envText = Get-Content -LiteralPath $envFile -Raw
if ($envText -match '(?m)^DB_CONNECTION=(\w+)') { $conn = $Matches[1].Trim().ToLower() }
if ($DbMode -ne 'auto') { $conn = $DbMode }
if (($conn -eq 'mysql') -and (-not (Test-Tcp '127.0.0.1' 3306)) -and (-not $laragonMysql) -and (-not $mysqlSvc)) {
    Write-Warn 'DB_CONNECTION=mysql but no MySQL detected on this machine - falling back to sqlite.'
    Write-Warn 'To use MySQL later: install Laragon/MySQL, set DB_CONNECTION=mysql, re-run installer.'
    Set-DotEnvValue $envFile 'DB_CONNECTION' 'sqlite'
    $conn = 'sqlite'
}
Write-Host "  DB mode: $conn" -ForegroundColor DarkGray

$sqliteFile = Join-Path $Root 'database\database.sqlite'
if ($conn -eq 'sqlite') {
    if (-not (Test-Path -LiteralPath $sqliteFile)) {
        $dbName = Get-DotEnvValue $envFile 'DB_DATABASE'
        if ([string]::IsNullOrWhiteSpace($dbName) -or $dbName -eq ':memory:') {
            New-Item -ItemType File -Path $sqliteFile -Force | Out-Null
            Write-Ok "SQLite file created: $sqliteFile"
        } else {
            $custom = Join-Path $Root $dbName
            try { New-Item -ItemType File -Path $custom -Force | Out-Null; Write-Ok "SQLite file created: $custom" } catch { New-Item -ItemType File -Path $sqliteFile -Force | Out-Null; Write-Ok "SQLite file created: $sqliteFile" }
        }
    } else { Write-Ok "SQLite file present: $sqliteFile" }
}

# Redis fallback: .env templates default to redis-backed queue/cache/session.
# If Redis is (still) down, point Laravel at zero-dependency drivers instead of
# crashing at runtime. LOUD on purpose: this rewrites three .env lines.
$redisNow = Test-Tcp '127.0.0.1' 6379
if (-not $redisNow) {
    $qb = Get-DotEnvValue $envFile 'QUEUE_CONNECTION'
    $cb = Get-DotEnvValue $envFile 'CACHE_STORE'
    $sb = Get-DotEnvValue $envFile 'SESSION_DRIVER'
    if ($qb -eq 'redis') { Set-DotEnvValue $envFile 'QUEUE_CONNECTION' 'database'; Write-Warn 'Redis down: QUEUE_CONNECTION redis -> database (jobs table exists in migrations)' }
    if ($cb -eq 'redis') { Set-DotEnvValue $envFile 'CACHE_STORE' 'file'; Write-Warn 'Redis down: CACHE_STORE redis -> file' }
    if ($sb -eq 'redis') { Set-DotEnvValue $envFile 'SESSION_DRIVER' 'file'; Write-Warn 'Redis down: SESSION_DRIVER redis -> file (no sessions table exists, file needs none)' }
    if (($qb -eq 'redis') -or ($cb -eq 'redis') -or ($sb -eq 'redis')) {
        Write-Warn 'To use Redis later: start redis, restore the three values, run: php artisan optimize:clear'
    }
} else { Write-Ok 'Redis 6379 listening - keeping redis-backed queue/cache/session' }

# zkteco-service/.env (gitignored: missing on git-clone machines, no example ships).
$zEnv = Join-Path $Root 'zkteco-service\.env'
if (-not (Test-Path -LiteralPath $zEnv)) {
    $zDefaults = @(
        'ZKTECO_PYTHON_SERVICE_HOST=0.0.0.0',
        'ZKTECO_PYTHON_SERVICE_PORT=5000',
        'ADMS_PORT=8081',
        'ADMS_HOST=0.0.0.0',
        'LARAVEL_URL=http://127.0.0.1',
        'ADMS_LARAVEL_TIMEOUT=10',
        'ADMS_SAVE_RAW=0',
        'ADMS_VERBOSE_LOG=0',
        'ADMS_COMMAND_REFRESH_SECONDS=1',
        'ADMS_FACE_SERIAL_COOLDOWN_SECONDS=2',
        'ADMS_USER_COMMAND_HOLD_SECONDS=60'
    )
    Set-Content -LiteralPath $zEnv -Value $zDefaults -Encoding ASCII
    Write-Ok 'zkteco-service/.env created with defaults (bridge :5000, ADMS :8081)'
} else { Write-Ok 'zkteco-service/.env present (never overwritten)' }

# required writable dirs
foreach ($d in @('storage\logs', 'storage\framework\cache', 'storage\framework\sessions', 'storage\framework\views', 'storage\app\backups', 'bootstrap\cache', 'zkteco-service\logs', 'backups')) {
    $p = Join-Path $Root $d
    try { New-Item -ItemType Directory -Path $p -Force | Out-Null } catch {}
}
Write-Ok 'writable directories ensured'
try {
    & icacls (Join-Path $Root 'storage') /grant 'USERS:(OI)(CI)F' /T /Q | Out-Null
    & icacls (Join-Path $Root 'bootstrap\cache') /grant 'USERS:(OI)(CI)F' /T /Q | Out-Null
    if ($LASTEXITCODE -eq 0) { Write-Ok 'directory permissions relaxed (icacls USERS:F on storage + bootstrap\cache)' }
    else { Write-Warn 'icacls relaxation skipped - run elevated to apply (non-fatal)' }
} catch { Write-Warn 'icacls relaxation skipped (non-fatal)' }

# ---------------- 3. composer ----------------
Write-Host ''
Write-Host '--- Step 3/10: composer install (Laravel 13 + 17 modules) ---' -ForegroundColor Cyan
Push-Location $Root
try {
    if ([string]::IsNullOrWhiteSpace($composerCmd)) { $composerCmd = 'composer' }
    if ($Production) { Invoke-Step 'composer install --no-dev' { & $composerCmd install --no-dev --no-interaction --prefer-dist --optimize-autoloader } { Test-Path -LiteralPath (Join-Path $Root 'vendor\autoload.php') } }
    else { Invoke-Step 'composer install' { & $composerCmd install --no-interaction --prefer-dist --optimize-autoloader } { Test-Path -LiteralPath (Join-Path $Root 'vendor\autoload.php') } }
}
finally { Pop-Location }

# ---------------- 4. keys + database creation (ALL before npm build) ----------------
Write-Host ''
Write-Host '--- Step 4/10: app keys + secrets + MySQL database ---' -ForegroundColor Cyan
Push-Location $Root
try {
    $envText = Get-Content -LiteralPath $envFile -Raw
    if ($envText -match '(?m)^APP_KEY=\s*$') {
        Invoke-Step 'artisan key:generate' { php artisan key:generate --force } { ((Select-String -LiteralPath $envFile -Pattern '^APP_KEY=' | Select-Object -First 1).Line.Length -gt 12) }
    } else { Write-Ok 'APP_KEY present' }

    # Reverb keys (required only when broadcasting via reverb; hex, .env-safe).
    if ((Get-DotEnvValue $envFile 'BROADCAST_CONNECTION') -eq 'reverb') {
        if ([string]::IsNullOrWhiteSpace((Get-DotEnvValue $envFile 'REVERB_APP_ID'))) {
            Set-DotEnvValue $envFile 'REVERB_APP_ID' 'hrm-local'
            Write-Ok 'REVERB_APP_ID defaulted to hrm-local'
        }
        if ([string]::IsNullOrWhiteSpace((Get-DotEnvValue $envFile 'REVERB_APP_KEY'))) {
            $nk = ((& php -r "echo bin2hex(random_bytes(16));" | Out-String).Trim())
            if ($nk.Length -lt 16) { throw 'could not generate REVERB_APP_KEY (php random_bytes failed)' }
            Set-DotEnvValue $envFile 'REVERB_APP_KEY' $nk
            # The production template references ${REVERB_APP_KEY} in the VITE
            # line; Vite must see the literal value, so mirror it explicitly.
            Set-DotEnvValue $envFile 'VITE_REVERB_APP_KEY' $nk
            Write-Ok 'REVERB_APP_KEY generated (+ mirrored to VITE_REVERB_APP_KEY)'
        } else { Write-Ok 'REVERB_APP_KEY present' }
        if ([string]::IsNullOrWhiteSpace((Get-DotEnvValue $envFile 'REVERB_APP_SECRET'))) {
            $ns = ((& php -r "echo bin2hex(random_bytes(32));" | Out-String).Trim())
            if ($ns.Length -lt 32) { throw 'could not generate REVERB_APP_SECRET (php random_bytes failed)' }
            Set-DotEnvValue $envFile 'REVERB_APP_SECRET' $ns
            Write-Ok 'REVERB_APP_SECRET generated'
        } else { Write-Ok 'REVERB_APP_SECRET present' }
    } else { Write-Ok 'broadcast=log/file - no Reverb keys needed' }

    # Backup encryption key (Backups module throws when enabled-but-empty).
    $ben = Get-DotEnvValue $envFile 'BACKUP_ENCRYPTION_ENABLED'
    $bek = Get-DotEnvValue $envFile 'BACKUP_ENCRYPTION_KEY'
    if ((($ben -eq '') -or ($ben -eq 'true')) -and [string]::IsNullOrWhiteSpace($bek)) {
        $nb = ((& php -r "echo base64_encode(random_bytes(32));" | Out-String).Trim())
        if ($nb.Length -lt 16) { throw 'could not generate BACKUP_ENCRYPTION_KEY (php random_bytes failed)' }
        Set-DotEnvValue $envFile 'BACKUP_ENCRYPTION_KEY' $nb
        Write-Ok 'BACKUP_ENCRYPTION_KEY generated (base64 of 32 random bytes)'
    } else { Write-Ok 'backup encryption key OK (disabled or present)' }

    # MySQL database (missing DB is the #1 fresh-machine migrate killer).
    if ($conn -eq 'mysql') {
        if (Ensure-MySqlDatabase $envFile) { Write-Ok 'MySQL database ensured (CREATE DATABASE IF NOT EXISTS)' }
        else { Write-Warn 'MySQL database could not be ensured yet - will retry during migrate (server may still boot, or credentials lack CREATE privilege)' }
    }
}
finally { Pop-Location }

# ---------------- 5. npm (build AFTER .env+keys so VITE_* bake correctly) ----------------
Write-Host ''
Write-Host '--- Step 5/10: npm install + build (Vite 8 + Vue 3.5 + Tailwind 4) ---' -ForegroundColor Cyan
Push-Location $Root
try {
    # NOTE: `npm install` (not `npm ci`) on purpose: on a fresh machine both do
    # a clean install, but on re-runs `npm ci` would wipe a healthy
    # node_modules first and a network hiccup would leave the machine broken.
    Invoke-Step 'npm install' { npm install --no-audit --no-fund } { Test-Path -LiteralPath (Join-Path $Root 'node_modules') }
    if (-not $SkipBuild) {
        Invoke-Step 'npm run build' { npm run build } { Test-Path -LiteralPath (Join-Path $Root 'public\build\manifest.json') }
        try {
            Write-Info 'design-token lint ...'
            & npm run lint:tokens
            if ($LASTEXITCODE -eq 0) { Write-Ok 'design tokens clean' } else { Write-Warn 'lint:tokens reported issues (non-fatal) - check mistral.ai/DESIGN.md' }
        } catch { Write-Warn 'lint:tokens skipped (non-fatal)' }
    } else { Write-Warn 'Skipped frontend build (-SkipBuild)' }
}
finally { Pop-Location }

# ---------------- 6. python venv ----------------
Write-Host ''
Write-Host '--- Step 6/10: python venv + requirements (Flask bridge :5000 + ADMS :8081) ---' -ForegroundColor Cyan
$venvPy = Join-Path $Root 'zkteco-service\venv\Scripts\python.exe'
$reqBridge = Join-Path $Root 'zkteco-service\requirements.txt'
$reqRoot = Join-Path $Root 'requirements.txt'
$reqFile = $reqBridge
if (-not (Test-Path -LiteralPath $reqFile)) { $reqFile = $reqRoot }
Push-Location $Root
try {
    Invoke-Step 'python venv + pip requirements' {
        if (-not (Test-Path -LiteralPath $venvPy)) {
            Write-Info 'creating venv (py -3 -m venv zkteco-service\venv) ...'
            & py -3 -m venv (Join-Path $Root 'zkteco-service\venv')
        }
        Write-Info 'upgrading pip ...'
        & $venvPy -m pip install --upgrade pip
        Write-Info "installing $reqFile ..."
        & $venvPy -m pip install -r $reqFile
    } { Test-Path -LiteralPath $venvPy }
    try {
        & $venvPy -c "import flask; print('flask ' + flask.__version__)"
        Write-Ok 'Flask import check passed'
    } catch { Write-Warn 'Flask import check failed - bridge may not start (re-run pip install)' }
}
finally { Pop-Location }

# ---------------- 7. laravel ----------------
Write-Host ''
Write-Host '--- Step 7/10: migrate + seed + caches (128 migrations, 17 modules) ---' -ForegroundColor Cyan
Push-Location $Root
try {
    Write-Info 'optimize:clear ...'
    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) { Write-Warn 'optimize:clear exited non-zero (continuing - DB may be empty, migrate comes next)' }

    if ($conn -eq 'mysql') {
        $dbReady = $false
        for ($i = 1; $i -le 6; $i++) {
            # Re-attempt CREATE DATABASE each round: covers both "server still
            # booting" and "database did not exist yet".
            if (Ensure-MySqlDatabase $envFile) { Write-Info 'database ensured' }
            & php artisan migrate:status *> (Join-Path $Root 'storage\logs\hrm-migration-status.log')
            if ($LASTEXITCODE -eq 0) { $dbReady = $true; break }
            Write-Warn "MySQL not ready (attempt $i/6) - open Laragon and click Start All (or start service HRM-MySQL); waiting 10s ..."
            Start-Sleep -Seconds 10
        }
        if (-not $dbReady) {
            Write-Err 'MySQL unreachable. Either start MySQL and re-run, or switch to SQLite: set DB_CONNECTION=sqlite in .env.'
            throw 'Database preflight failed. See storage\logs\hrm-migration-status.log.'
        }
        Write-Ok 'MySQL reachable'
    }

    Invoke-Step 'artisan migrate --force' { php artisan migrate --force } { $true }

    $doSeed = $false
    if ($Seed) { $doSeed = $true }
    elseif (-not $SkipSeed) {
        if ($NonInteractive) { $doSeed = $false }
        else {
            $ans = Read-Host '  Run database seeders now? (admin user + permissions + Ramadan dates) [y/N]'
            if ($ans -match '^[Yy]') { $doSeed = $true }
        }
    }
    if ($doSeed) { Invoke-Step 'artisan db:seed --force' { php artisan db:seed --force } { $true } }
    else { Write-Warn 'Seeders skipped (run later: php artisan db:seed --force)' }

    # Keep the committed ziggy route map in sync with current routes.
    $routeList = ''
    try { $routeList = (& php artisan list --raw | Out-String) } catch { $routeList = '' }
    if ($routeList -match 'ziggy:generate') {
        & php artisan ziggy:generate
        if (($LASTEXITCODE -eq 0) -and (Test-Path -LiteralPath (Join-Path $Root 'resources\js\ziggy.js'))) { Write-Ok 'ziggy route map regenerated' }
        else { Write-Warn 'ziggy:generate had warnings (non-fatal)' }
    }

    Write-Info 'storage:link ...'
    try { & php artisan storage:link } catch { Write-Warn 'storage:link skipped (link may already exist)' }

    if ($Production) {
        Write-Info 'caching config/routes/views for production ...'
        & php artisan optimize
        if ($LASTEXITCODE -ne 0) { Write-Warn 'artisan optimize had warnings (non-fatal)' } else { Write-Ok 'production optimize done' }
    } else { Write-Ok 'dev mode: caches left clear (no optimize)' }
}
finally { Pop-Location }

# ---------------- 8. windows integration ----------------
Write-Host ''
Write-Host '--- Step 8/10: scheduler + watchdog + firewall + autostart ---' -ForegroundColor Cyan
$phpExe = 'php'
try { $f = Get-Command php -ErrorAction Stop; if ($f.Source) { $phpExe = $f.Source } } catch { $phpExe = 'php' }

if ($isAdmin) {
    # 1) HRM Scheduler. Task Scheduler does NOT interpret `>>` redirection
    # (it passes it literally to php.exe -> exit 1), so the action MUST go
    # through cmd.exe - proven pattern from production/Setup-Scheduler-Unification.ps1.
    try {
        $schedProbe = ''
        try { $schedProbe = (& schtasks /query /tn 'HRM Scheduler' | Out-String) } catch { $schedProbe = '' }
        if ($schedProbe -match 'HRM Scheduler') {
            Write-Ok 'Task HRM Scheduler already exists (left untouched)'
        } else {
            $schedLog = Join-Path $Root 'storage\logs\hrm-schedule-run.log'
            $registered = $false
            try {
                # Preferred: ScheduledTasks cmdlets (inbox on Win10/11 Pro).
                $action = New-ScheduledTaskAction -Execute 'cmd.exe' `
                    -Argument "/d /c `"`"$phpExe`" artisan schedule:run >> `"$schedLog`" 2>&1`"" `
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
                    -Description 'HRM Laravel scheduler (schedule:run every minute). All jobs use withoutOverlapping.' `
                    -Force | Out-Null
                $registered = $true
            } catch {
                Write-Warn "ScheduledTasks cmdlets unavailable: $($_.Exception.Message) - falling back to schtasks"
            }
            if (-not $registered) {
                $trArg = 'cmd.exe /d /c `"' + $phpExe + '`" artisan schedule:run >> `"' + $schedLog + '`" 2>&1'
                & schtasks /create /tn 'HRM Scheduler' /tr $trArg /sc minute /mo 1 /ru SYSTEM /rl HIGHEST /f
                if ($LASTEXITCODE -eq 0) { $registered = $true } else { Write-Warn 'Could not register HRM Scheduler task (non-fatal)' }
            }
            if ($registered) {
                Write-Ok 'Task HRM Scheduler registered (schedule:run every minute, SYSTEM, via cmd.exe)'
                # Prove it executes: run once now and watch the log move (60s cap).
                try { Start-ScheduledTask -TaskName 'HRM Scheduler' } catch {}
                $proved = $false
                for ($i = 1; $i -le 12; $i++) {
                    Start-Sleep -Seconds 5
                    if ((Test-Path -LiteralPath $schedLog) -and ((Get-Item -LiteralPath $schedLog).LastWriteTime -gt (Get-Date).AddMinutes(-3))) { $proved = $true; break }
                }
                if ($proved) { Write-Ok 'HRM Scheduler PROVED: first run wrote to hrm-schedule-run.log' }
                else { Write-Warn 'HRM Scheduler registered but first run not observed in 60s (check Task Scheduler history)' }
            }
        }
    } catch { Write-Warn "Scheduler registration skipped: $($_.Exception.Message)" }

    # 2) Queue watchdog safety net (every 5 min, supervisor-aware).
    try {
        $wdProbe = ''
        try { $wdProbe = (& schtasks /query /tn 'HRM Queue Worker Watchdog' | Out-String) } catch { $wdProbe = '' }
        if ($wdProbe -match 'HRM Queue Worker Watchdog') {
            Write-Ok 'Task HRM Queue Worker Watchdog already exists'
        } else {
            $wdScript = Join-Path $Root 'scripts\Ensure-HrmQueueWorker.ps1'
            if (Test-Path -LiteralPath $wdScript) {
                # Pass -Root explicitly: the script defaults to D:\hrm, which
                # breaks portable installs elsewhere.
                & schtasks /create /tn 'HRM Queue Worker Watchdog' /tr "powershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$wdScript`" -Root `"$Root`"" /sc minute /mo 5 /ru SYSTEM /f
                if ($LASTEXITCODE -eq 0) { Write-Ok 'Task HRM Queue Worker Watchdog registered (every 5 min, SYSTEM)' }
                else { Write-Warn 'Could not register watchdog task (non-fatal)' }
            } else { Write-Warn "watchdog script missing: $wdScript (non-fatal)" }
        }
    } catch { Write-Warn "Watchdog registration skipped: $($_.Exception.Message)" }

    # 3) Retire the legacy orphan biometrics task (worker is supervised now).
    try {
        $legProbe = ''
        try { $legProbe = (& schtasks /query /tn 'HRM-temp-biometrics-worker' | Out-String) } catch { $legProbe = '' }
        if ($legProbe -match 'HRM-temp-biometrics-worker') {
            $bakFile = Join-Path $Root ('backups\hrm-task-HRM-temp-biometrics-worker-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.xml')
            try { & schtasks /query /tn 'HRM-temp-biometrics-worker' /xml | Set-Content -LiteralPath $bakFile -Encoding ASCII } catch {}
            try { Disable-ScheduledTask -TaskName 'HRM-temp-biometrics-worker' | Out-Null; Write-Ok 'Legacy HRM-temp-biometrics-worker DISABLED (backup kept in backups\)' }
            catch {
                & schtasks /change /tn 'HRM-temp-biometrics-worker' /disable
                if ($LASTEXITCODE -eq 0) { Write-Ok 'Legacy HRM-temp-biometrics-worker DISABLED (backup kept in backups\)' }
                else { Write-Warn 'Could not disable legacy biometrics task (non-fatal)' }
            }
        }
    } catch { Write-Warn "Legacy task cleanup skipped: $($_.Exception.Message)" }

    # 4) Firewall: LAN browsers + fingerprint devices must reach these ports.
    foreach ($p in @(8000, 8080, 8081, 5000)) {
        $ruleName = "HRM-Allow-$p"
        try {
            Get-NetFirewallRule -DisplayName $ruleName -ErrorAction Stop | Out-Null
            Write-Ok "firewall rule present: $ruleName"
        } catch {
            try {
                New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort $p -Action Allow -Profile Private,Domain | Out-Null
                Write-Ok "firewall rule created: $ruleName (TCP $p inbound, Private+Domain)"
            } catch { Write-Warn "could not create firewall rule for port $p (non-fatal - add manually for LAN/device access)" }
        }
    }
    Write-Warn 'Firewall covers Private+Domain profiles; on Public networks add the rules manually.'

    # 5) Logon autostart (visible-free VBS, same file this server uses).
    try {
        $startupDir = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\Startup'
        $vbs = Join-Path $startupDir 'HRM-AutoStart.vbs'
        if (-not (Test-Path -LiteralPath $vbs)) {
            $ps1 = Join-Path $Root 'scripts\Start-HRM-Windows.ps1'
            $cmd = 'CreateObject("Wscript.Shell").Run "powershell.exe -NoLogo -WindowStyle Hidden -ExecutionPolicy Bypass -File """' + $ps1 + '""" -SkipBuild -NonInteractive", 0, False'
            Set-Content -LiteralPath $vbs -Value "' HRM AutoStart (installed $(Get-Date -Format 'yyyy-MM-dd'))", $cmd -Encoding ASCII
            Write-Ok "Logon autostart installed: $vbs"
        } else { Write-Ok "Logon autostart already present: $vbs" }
    } catch { Write-Warn "Autostart VBS skipped: $($_.Exception.Message)" }
} else { Write-Warn 'Skipped scheduler/watchdog/firewall/autostart (need Administrator) - re-run elevated to register them.' }

Write-Host '  NOTE: true no-logon server operation needs the NSSM layer (HRM-MySQL/HRM-Reverb/HRM-Queue/hrm-redis)' -ForegroundColor DarkGray
Write-Host '  plus the SYSTEM task HRM-Startup - see production/DEPLOY-NEW-MACHINE.md' -ForegroundColor DarkGray

# ---------------- 9. verify ----------------
Write-Host ''
Write-Host '--- Step 9/10: verification ---' -ForegroundColor Cyan
$fail = 0
function Assert-Path([string] $p, [string] $label) {
    if (Test-Path -LiteralPath $p) { Write-Ok $label } else { Write-Err "MISSING: $label ($p)"; $script:fail++ }
}
Assert-Path (Join-Path $Root 'vendor\autoload.php') 'composer vendor/autoload.php (Laravel 13)'
Assert-Path (Join-Path $Root 'node_modules') 'node_modules (Vue/Vite deps)'
Assert-Path (Join-Path $Root 'public\build\manifest.json') 'public/build/manifest.json (frontend build)'
Assert-Path (Join-Path $Root 'resources\js\ziggy.js') 'ziggy route map (resources\js\ziggy.js)'
Assert-Path $venvPy 'python venv (Flask bridge + ADMS)'
Assert-Path (Join-Path $Root 'zkteco-service\.env') 'zkteco-service/.env (bridge+ADMS config)'
Assert-Path (Join-Path $Root 'zkteco-service\adms_server.py') 'adms_server.py (:8081)'
Assert-Path (Join-Path $Root 'zkteco-service\app.py') 'bridge app.py (:5000)'
Assert-Path (Join-Path $Root 'scripts\Start-HRM-Windows.ps1') 'service supervisor script'

Push-Location $Root
try {
    & php artisan migrate:status *> (Join-Path $Root 'storage\logs\hrm-migration-status.log')
    if ($LASTEXITCODE -eq 0) { Write-Ok 'database migrate:status OK (all 128 migrations reachable)' } else { Write-Err 'migrate:status FAILED - check storage\logs\hrm-migration-status.log'; $fail++ }

    $r = ''
    try { $r = (& $venvPy -c "import flask, flask_cors; print('py-ok')" | Out-String) } catch { $r = '' }
    if ($r -match 'py-ok') { Write-Ok 'python flask+flask-cors import OK' } else { Write-Err 'python flask import FAILED'; $fail++ }
    $zk = ''
    try { $zk = (& $venvPy -c "import zk; print('pyzk-ok')" | Out-String) } catch { $zk = '' }
    if ($zk -match 'pyzk-ok') { Write-Ok 'python pyzk import OK (direct device protocol available)' }
    else { Write-Warn 'pyzk import FAILED - bridge serves HTTP but direct device protocol may be unavailable (check Python version vs pyzk 0.9)' }

    # Secrets audit: every key the app needs at runtime must be non-empty now.
    $needKeys = @('APP_KEY')
    if ((Get-DotEnvValue $envFile 'BROADCAST_CONNECTION') -eq 'reverb') { $needKeys += @('REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET') }
    $ben2 = Get-DotEnvValue $envFile 'BACKUP_ENCRYPTION_ENABLED'
    if (($ben2 -eq '') -or ($ben2 -eq 'true')) { $needKeys += @('BACKUP_ENCRYPTION_KEY') }
    foreach ($k in $needKeys) {
        if ([string]::IsNullOrWhiteSpace((Get-DotEnvValue $envFile $k))) { Write-Err "secret $k is EMPTY in .env"; $fail++ }
        else { Write-Ok "secret present: $k" }
    }

    $qNow = Get-DotEnvValue $envFile 'QUEUE_CONNECTION'
    if (($qNow -eq 'redis') -and (-not (Test-Tcp '127.0.0.1' 6379))) { Write-Err 'QUEUE_CONNECTION=redis but Redis 6379 is dark - workers will fail'; $fail++ }
    elseif (Test-Tcp '127.0.0.1' 6379) { Write-Ok 'Redis 6379 listening' }
    else { Write-Ok "queue on $qNow (Redis not required)" }
    # Reverb owns port 8080 ONLY via the NSSM service (the supervisor never
    # binds it). Without that service there is no realtime on a new machine.
    if ((Get-DotEnvValue $envFile 'BROADCAST_CONNECTION') -eq 'reverb') {
        if (Test-Tcp '127.0.0.1' 8080) { Write-Ok 'Reverb 8080 listening' }
        else {
            $revSvc = Get-Service 'HRM-Reverb' -ErrorAction SilentlyContinue
            if ($revSvc) { Write-Warn "HRM-Reverb service exists (status $($revSvc.Status)) but 8080 is dark - start the service" }
            else { Write-Err 'Reverb 8080 dark and no HRM-Reverb service - realtime WILL NOT work. Register the NSSM layer per production/DEPLOY-NEW-MACHINE.md, or switch BROADCAST_CONNECTION=log.'; $fail++ }
        }
    }
    foreach ($pt in @(8000, 8080, 8081, 5000)) {
        if (Test-Tcp '127.0.0.1' $pt) { Write-Warn "port $pt already in use (a service may already run - installer leaves it alone)" }
    }
}
finally { Pop-Location }

# ---------------- 10. finish ----------------
Write-Host ''
Write-Host '--- Step 10/10: done ---' -ForegroundColor Cyan
$lanIp = Get-LanIp

if ($fail -eq 0) {
    Write-Host ''
    Write-Host '============================================================' -ForegroundColor Green
    Write-Host ' INSTALL COMPLETE - everything verified.' -ForegroundColor Green
    Write-Host '============================================================' -ForegroundColor Green
} else {
    Write-Host ''
    Write-Host '============================================================' -ForegroundColor Yellow
    Write-Host " INSTALL FINISHED WITH $fail ERROR(S) - see messages above." -ForegroundColor Yellow
    Write-Host '============================================================' -ForegroundColor Yellow
}

Write-Host ''
Write-Host '  Start now (interactive console):  scripts\Start-HRM-Windows.bat' -ForegroundColor Cyan
Write-Host "  Then open:  http://${lanIp}:8000/login" -ForegroundColor Cyan
Write-Host '  Default login (created by seeders, change it immediately):' -ForegroundColor Cyan
Write-Host '    email:    admin@hrm.local' -ForegroundColor Cyan
Write-Host '    password: password' -ForegroundColor Cyan
Write-Host '  (If you skipped seeding: re-run with -Seed to create the admin user.)' -ForegroundColor DarkGray
Write-Host '  Ports: Laravel 8000 | Reverb 8080 | ADMS 8081 | Bridge 5000 | Redis 6379 | MySQL 3306' -ForegroundColor DarkGray
Write-Host "  Full log: $LogFile" -ForegroundColor DarkGray
Write-Host '  New-machine server guide: production\DEPLOY-NEW-MACHINE.md' -ForegroundColor DarkGray

if ($StartAfter) {
    Write-Host ''
    Write-Host 'Starting HRM services now (-StartAfter) ...' -ForegroundColor Cyan
    $sup = Join-Path $Root 'scripts\Start-HRM-Windows.ps1'
    if ($NonInteractive) { & $sup -SkipBuild -NonInteractive }
    else { & $sup -SkipBuild }
}

try { Stop-Transcript | Out-Null } catch {}
if ($fail -gt 0) { exit 1 }
exit 0
