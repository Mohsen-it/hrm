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
      -AutoInstall tiers (verified IDs): Full = Laragon bundle
      (LeNgocKhoa.Laragon: PHP + MySQL + Redis + Node + git + Composer);
      Minimal (-Minimal) = PHP-NTS.8.3 + Node + Python + Composer-Setup.exe
      with SQLite (no MySQL/Redis). Laragon mysqld/redis are started
      detached when present but dark; the DB itself is auto-created.
      Native (-Native) = cloud-server parity without Laragon: official MySQL
      8.4 LTS ZIP + Redis 5 ZIP registered as real Windows services
      (HRM-MySQL, hrm-redis) under C:\hrm-services. Same names/ports as the
      production NSSM layer, so DEPLOY-NEW-MACHINE.md keeps applying.

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
    [switch] $Minimal,
    [switch] $Native,
    [switch] $Help
)

$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($Root)) {
    $Root = (Resolve-Path (Join-Path (Split-Path $MyInvocation.MyCommand.Path -Parent) '.')).Path
}

# ZKBioTime-style pollution: machine/user PYTHONHOME/PYTHONPATH redirect EVERY
# python interpreter (venv included) at a FOREIGN stdlib and break it with
# "SRE module mismatch" (proven on 2026-10-01: ZKBioTime Python311 leaked into
# our 3.15 venv). Project convention (install-deps.bat) is to clear them, so
# the venv always uses its own stdlib. Must precede ANY python invocation.
Remove-Item Env:\PYTHONHOME -ErrorAction SilentlyContinue
Remove-Item Env:\PYTHONPATH -ErrorAction SilentlyContinue
Remove-Item Env:\PYTHONIOENCODING -ErrorAction SilentlyContinue

if ($Help) {
    Write-Host 'Usage: Install-HRM-OneClick.ps1 [-CheckOnly] [-SkipBuild] [-Seed] [-SkipSeed]'
    Write-Host '       [-Production] [-DbMode auto|sqlite|mysql] [-ServerIp 10.10.250.2]'
    Write-Host '       [-AutoInstall] [-Minimal] [-Native] [-StartAfter] [-NonInteractive]'
    Write-Host ''
    Write-Host '  -CheckOnly     : audit only, change nothing (run first on a new machine)'
    Write-Host '  -SkipBuild     : skip npm run build'
    Write-Host '  -Seed          : run php artisan db:seed --force after migrate'
    Write-Host '  -Production    : composer install --no-dev + production template hints'
    Write-Host '  -DbMode        : force sqlite or mysql (default auto = keep .env, fallback sqlite)'
    Write-Host '  -ServerIp      : write APP_URL + VITE_REVERB_HOST with this LAN IP'
    Write-Host '  -AutoInstall   : try winget install for missing Git/Node/Python (needs admin + internet)'
    Write-Host '  -Minimal       : with -AutoInstall, skip Laragon; PHP-NTS stack + SQLite (no MySQL/Redis)'
    Write-Host '  -Native        : server-grade, no Laragon: native Windows services for MySQL 8.4'
    Write-Host '                   (HRM-MySQL) + Redis 5 (hrm-redis) under C:\hrm-services (implies tool setup)'
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

# Verified 2026-10: LeNgocKhoa.Laragon (by Laragon author, Inno, full WAMP:
# PHP + MySQL + Redis + Node + git + Composer). PHP.PHP.NTS.8.3 is the
# official PHP Group build (CLI-optimal). There is NO winget package for PHP
# Composer itself, so the Minimal tier uses the official Composer-Setup.exe.
$script:LaragonPython = $null
$script:PyBin = 'py'
$script:PyArgs = @('-3')
$script:SessionAddedPaths = @()

function Find-LaragonRoot {
    if (Test-Path -LiteralPath 'C:\laragon\laragon.exe') { return 'C:\laragon' }
    try {
        $l = (Get-Command laragon.exe -ErrorAction Stop).Source
        if ($l) { return (Split-Path $l -Parent) }
    } catch {}
    return $null
}

function Add-SessionPathOnce([string] $Dir) {
    if ([string]::IsNullOrWhiteSpace($Dir)) { return $false }
    if (-not (Test-Path -LiteralPath $Dir -PathType Container)) { return $false }
    $norm = $Dir.TrimEnd('\')
    foreach ($p in ($env:Path -split ';')) {
        if ($p.Trim().TrimEnd('\') -ieq $norm) { return $true }
    }
    # APPEND (not prepend): properly installed system tools keep priority and
    # Laragon dirs only fill gaps. Prepending once caused Laragon npm to shadow
    # system npm and churn package-lock.json.
    $env:Path = $env:Path + ';' + $Dir
    $script:SessionAddedPaths += $Dir
    return $true
}

function Persist-UserPathOnce {
    if ($script:SessionAddedPaths.Count -eq 0) { return }
    try {
        $userPath = [System.Environment]::GetEnvironmentVariable('Path', 'User')
        if ([string]::IsNullOrEmpty($userPath)) { $userPath = '' }
        $toAdd = @()
        foreach ($d in $script:SessionAddedPaths) {
            $hit = $false
            foreach ($p in ($userPath -split ';')) {
                if ($p.Trim().TrimEnd('\') -ieq $d.TrimEnd('\')) { $hit = $true; break }
            }
            if (-not $hit) { $toAdd += $d }
        }
        if ($toAdd.Count -eq 0) { return }
        $newPath = ($toAdd -join ';') + ';' + $userPath
        if ($newPath.Length -le 1000) {
            [System.Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
            Write-Ok 'Tool dirs persisted to User PATH (new windows will see them)'
        } else { Write-Warn 'User PATH too long to persist automatically - session PATH works for this run; add the tool dirs manually for new windows' }
    } catch { Write-Warn "could not persist User PATH: $($_.Exception.Message) (session still works)" }
}

# Laragon leaves its tools out of PATH by default (its GUI has
# Tools > PATH > Add Laragon to PATH). Import them for this session.
function Import-LaragonTools([string] $LRoot) {
    if ([string]::IsNullOrWhiteSpace($LRoot)) { return }
    $picked = @()
    $php = Get-ChildItem (Join-Path $LRoot 'bin\php\*\php.exe') -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($php -and (Add-SessionPathOnce $php.Directory.FullName)) { $picked += 'php' }
    $node = Get-ChildItem (Join-Path $LRoot 'bin\nodejs\*\node.exe') -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($node -and (Add-SessionPathOnce $node.Directory.FullName)) { $picked += 'nodejs' }
    $pyth = Get-ChildItem (Join-Path $LRoot 'bin\python\*\python.exe') -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($pyth) { $script:LaragonPython = $pyth.FullName; $picked += 'python' }
    $g1 = Join-Path $LRoot 'bin\git\bin\git.exe'
    $g2 = Join-Path $LRoot 'bin\git\cmd\git.exe'
    if ((Test-Path -LiteralPath $g1) -and (Add-SessionPathOnce (Split-Path $g1 -Parent))) { $picked += 'git' }
    elseif ((Test-Path -LiteralPath $g2) -and (Add-SessionPathOnce (Split-Path $g2 -Parent))) { $picked += 'git' }
    if (Test-Path -LiteralPath (Join-Path $LRoot 'bin\composer\composer.bat')) { $picked += 'composer' }
    if ($picked.Count -gt 0) {
        Write-Ok ("Laragon tools in session PATH: " + ($picked -join ', '))
        Persist-UserPathOnce
    } else { Write-Warn 'Laragon found but no usable tool dirs matched the known layout' }
}

# Re-probe every installable tool and refresh the audit table + the python
# interpreter selected for the venv step. Safe to call repeatedly.
function Invoke-ToolReprobe {
    Write-Info 're-probing tools ...'
    $p = ''
    try { $p = ((& php -v | Out-String).Split([Environment]::NewLine)[0].Trim()) } catch { $p = '' }
    if ($p -match 'PHP 8\.(3|4|5)') { $script:phpOk = $true; ($script:checks | Where-Object { $_.Name -eq 'PHP 8.3+' }).Ok = $true; Write-Ok "PHP: $p" }
    $w = ''
    try { $w = (& where.exe composer.bat | Out-String) } catch { $w = '' }
    $bl = ($w.Split([Environment]::NewLine) | Where-Object { $_ -match 'composer\.bat' } | Select-Object -First 1)
    if ($bl) { $script:composerCmd = $bl.Trim() }
    if ([string]::IsNullOrWhiteSpace($script:composerCmd)) { $script:composerCmd = 'composer.bat' }
    $c = ''
    try { $c = (& $script:composerCmd --version | Out-String) } catch { $c = '' }
    if ($c -match 'Composer') { $script:composerOk = $true; ($script:checks | Where-Object { $_.Name -eq 'Composer 2.x' }).Ok = $true; Write-Ok "Composer: $($c.Trim().Split([Environment]::NewLine)[0])" }
    $n = ''
    try { $n = ((& node -v | Out-String).Trim()) } catch { $n = '' }
    if ($n -match 'v(2[0-9]|[3-9][0-9])') { $script:nodeOk = $true; $script:npmOk = $true; ($script:checks | Where-Object { $_.Name -eq 'Node 20+' }).Ok = $true; ($script:checks | Where-Object { $_.Name -eq 'npm' }).Ok = $true; Write-Ok "Node: $n" }
    $g = ''
    try { $g = ((& git --version | Out-String).Trim()) } catch { $g = '' }
    if ($g -match 'git version') { $script:gitOk = $true; Write-Ok "Git: $g" }
    $script:PyBin = $null
    $script:PyArgs = @()
    $pa = ''
    try { $pa = (& py -3 --version | Out-String) } catch { $pa = '' }
    if ($pa -match 'Python 3\.(1[1-9]|[2-9][0-9])') {
        $script:PyBin = 'py'; $script:PyArgs = @('-3')
        $script:pyOk = $true; ($script:checks | Where-Object { $_.Name -eq 'Python 3.11+' }).Ok = $true
        Write-Ok "Python: $($pa.Trim())"
    } else {
        $pb = ''
        try { $pb = (& python --version | Out-String) } catch { $pb = '' }
        if ($pb -match 'Python 3\.(1[1-9]|[2-9][0-9])') {
            $script:PyBin = 'python'; $script:PyArgs = @()
            $script:pyOk = $true; ($script:checks | Where-Object { $_.Name -eq 'Python 3.11+' }).Ok = $true
            Write-Ok "Python: $($pb.Trim())"
        } elseif (($script:LaragonPython) -and (Test-Path -LiteralPath $script:LaragonPython)) {
            $pc = ''
            try { $pc = (& $script:LaragonPython --version | Out-String) } catch { $pc = '' }
            if ($pc -match 'Python 3\.(1[1-9]|[2-9][0-9])') {
                $script:PyBin = $script:LaragonPython; $script:PyArgs = @()
                $script:pyOk = $true; ($script:checks | Where-Object { $_.Name -eq 'Python 3.11+' }).Ok = $true
                Write-Ok "Python (Laragon): $($pc.Trim())"
            }
        }
    }
}

# winget PHP manifests rot fast (proven 2026-10-01: the pinned 8.3.31 zip
# 404s). Try winget first (cheap), then fall back to a direct download with
# version discovery from windows.php.net (latest 8.3.x NTS VS16 x64).
function Install-PhpNts {
    $wingetOk = $false
    try { Install-WithWinget 'PHP.PHP.NTS.8.3' 'PHP 8.3 NTS'; $wingetOk = $true }
    catch { Write-Warn "winget PHP failed: $($_.Exception.Message) - trying direct download" }
    Invoke-ToolReprobe
    if ($script:phpOk) { return }
    if ($wingetOk) { throw 'PHP installed via winget but php -v still fails - fix PATH manually and re-run' }
    Write-Info 'Discovering latest PHP 8.3 NTS build from windows.php.net ...'
    $page = ''
    try {
        $page = (Invoke-WebRequest -Uri 'https://windows.php.net/download/' -UseBasicParsing -UserAgent $script:BrowserUa | Select-Object -ExpandProperty Content)
    } catch { throw "windows.php.net unreachable: $($_.Exception.Message)" }
    $vers = @()
    foreach ($m in ([regex]::Matches($page, 'php-(8\.3\.\d+)-nts-Win32-vs16-x64\.zip'))) { $vers += $m.Groups[1].Value }
    if ($vers.Count -eq 0) { throw 'no PHP 8.3 NTS x64 build found on windows.php.net/download - install PHP manually' }
    $best = ($vers | Sort-Object { [version]$_ } -Descending | Select-Object -First 1)
    $zipUrl = "https://windows.php.net/downloads/releases/php-${best}-nts-Win32-vs16-x64.zip"
    $dest = Join-Path $script:NativeRoot ("php\php-${best}-nts")
    $dstPhp = Join-Path $dest 'php.exe'
    if (-not (Test-Path -LiteralPath $dstPhp)) {
        $zip = Join-Path ([System.IO.Path]::GetTempPath()) "php-${best}-nts.zip"
        if (-not (Test-Path -LiteralPath $zip)) {
            Write-Info "Downloading PHP $best NTS (~30MB) ..."
            try {
                $pp = $ProgressPreference; $ProgressPreference = 'SilentlyContinue'
                try { Invoke-WebRequest -Uri $zipUrl -OutFile $zip -UseBasicParsing -UserAgent $script:BrowserUa }
                finally { $ProgressPreference = $pp }
            } catch { throw "PHP download failed: $($_.Exception.Message)" }
        }
        if (((Get-Item -LiteralPath $zip).Length) -lt 5MB) { throw "PHP zip suspiciously small - delete $zip and re-run" }
        try { New-Item -ItemType Directory -Path $dest -Force | Out-Null } catch {}
        Write-Info 'Extracting PHP ...'
        try { Expand-Archive -LiteralPath $zip -DestinationPath $dest -Force }
        catch { throw "PHP extraction failed: $($_.Exception.Message)" }
        try { Remove-Item -LiteralPath $zip -Force -ErrorAction SilentlyContinue } catch {}
    }
    if (-not (Test-Path -LiteralPath $dstPhp)) { throw "php.exe not found after extract: $dstPhp" }
    $prodIni = Join-Path $dest 'php.ini-production'
    $ini = Join-Path $dest 'php.ini'
    if ((-not (Test-Path -LiteralPath $ini)) -and (Test-Path -LiteralPath $prodIni)) {
        Copy-Item -LiteralPath $prodIni -Destination $ini -Force
        Write-Ok "php.ini seeded from php.ini-production: $ini"
    }
    Add-SessionPathOnce $dest | Out-Null
    Persist-UserPathOnce
    Invoke-ToolReprobe
    if (-not $script:phpOk) { throw 'PHP extracted but php -v still fails - fix PATH manually and re-run' }
    Write-Ok "Native PHP ready: $dest"
}

# No winget package exists for PHP Composer: use the official Inno setup.
function Install-ComposerSetup {
    Write-Info 'Installing Composer via official Composer-Setup.exe (/VERYSILENT) ...'
    $url = 'https://getcomposer.org/Composer-Setup.exe'
    $dst = Join-Path ([System.IO.Path]::GetTempPath()) 'Composer-Setup.exe'
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $url -OutFile $dst -UseBasicParsing -UserAgent $script:BrowserUa
    } catch { throw "Composer download failed: $($_.Exception.Message) - install manually: https://getcomposer.org/download/" }
    try { Start-Process -FilePath $dst -ArgumentList '/VERYSILENT', '/NORESTART' -Wait }
    catch { throw "Composer setup failed: $($_.Exception.Message)" }
    Write-Ok 'Composer setup finished'
}

# Starts Laragon's mysqld detached for THIS install run (no service layer -
# that stays documented in production/DEPLOY-NEW-MACHINE.md). Initializes an
# empty-root data dir when none exists yet.
function Start-LaragonMysql {
    $lr = Find-LaragonRoot
    if (-not $lr) { return $false }
    $mysqld = Get-ChildItem (Join-Path $lr 'bin\mysql\*\bin\mysqld.exe') -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if (-not $mysqld) { return $false }
    $mBase = Split-Path $mysqld.Directory.FullName -Parent
    $ini = Join-Path $mBase 'my.ini'
    $dataDir = $null
    if (Test-Path -LiteralPath $ini) {
        $dm = Select-String -LiteralPath $ini -Pattern '^\s*datadir\s*=\s*(.+?)\s*$' | Select-Object -First 1
        if ($dm) { $dataDir = $dm.Matches[0].Groups[1].Value.Trim().Trim('"').Trim("'") }
    }
    if ([string]::IsNullOrWhiteSpace($dataDir)) { $dataDir = Join-Path $mBase 'data' }
    if (-not [System.IO.Path]::IsPathRooted($dataDir)) { $dataDir = Join-Path $mBase $dataDir }
    $dataDir = $dataDir -replace '/', '\'
    $init = @('mysql.ib', 'ibdata1', 'mysql') | Where-Object { Test-Path -LiteralPath (Join-Path $dataDir $_) } | Select-Object -First 1
    if (-not $init) {
        $alt = Get-ChildItem (Join-Path $lr 'data\mysql*') -Directory -ErrorAction SilentlyContinue | Where-Object {
            (Test-Path -LiteralPath (Join-Path $_.FullName 'mysql.ib')) -or (Test-Path -LiteralPath (Join-Path $_.FullName 'ibdata1'))
        } | Select-Object -First 1
        if ($alt) { $dataDir = $alt.FullName }
    }
    $init = @('mysql.ib', 'ibdata1', 'mysql') | Where-Object { Test-Path -LiteralPath (Join-Path $dataDir $_) } | Select-Object -First 1
    if (-not (Test-Path -LiteralPath $dataDir)) { try { New-Item -ItemType Directory -Path $dataDir -Force | Out-Null } catch {} }
    if (-not $init) {
        Write-Info "initializing MySQL data dir with empty root: $dataDir ..."
        try { & $mysqld.FullName --initialize-insecure --datadir=($dataDir -replace '\\', '/') | Out-Null }
        catch { Write-Warn "mysqld --initialize-insecure failed: $($_.Exception.Message)"; return $false }
        $init = @('mysql.ib', 'ibdata1', 'mysql') | Where-Object { Test-Path -LiteralPath (Join-Path $dataDir $_) } | Select-Object -First 1
        if (-not $init) { Write-Warn 'data dir initialization did not produce system tables'; return $false }
    }
    try {
        $margs = @()
        if (Test-Path -LiteralPath $ini) { $margs += ('--defaults-file=' + ($ini -replace '\\', '/')) }
        $margs += ('--datadir=' + ($dataDir -replace '\\', '/'))
        $margs += '--console'
        Write-Info "starting mysqld detached ($($mysqld.FullName)) ..."
        Start-Process -FilePath $mysqld.FullName -ArgumentList $margs -WindowStyle Hidden
    } catch { Write-Warn "mysqld start failed: $($_.Exception.Message)"; return $false }
    for ($i = 1; $i -le 18; $i++) {
        Start-Sleep -Seconds 5
        if (Test-Tcp '127.0.0.1' 3306) { return $true }
    }
    Write-Warn 'mysqld started but 3306 stayed dark for 90s'
    return $false
}

# --- Native tier (no Laragon): real Windows services, cloud-server parity ---
$script:NativeRoot = 'C:\hrm-services'
$script:NativeMysqlDir = $null
$script:NativeRedisDir = $null
$script:NativeNginxDir = $null
$script:MysqlZipUrl = 'https://cdn.mysql.com//archives/mysql-8.4/mysql-8.4.3-winx64.zip'
$script:MysqlZipName = 'mysql-8.4.3-winx64.zip'
$script:MysqlVerDir = 'mysql-8.4.3-winx64'
$script:RedisZipUrl = 'https://github.com/tporadowski/redis/releases/download/v5.0.14.1/Redis-x64-5.0.14.1.zip'
$script:RedisZipName = 'Redis-x64-5.0.14.1.zip'
$script:NginxZipUrl = 'https://nginx.org/download/nginx-1.30.5.zip'
$script:NginxZipName = 'nginx-1.30.5.zip'
$script:NginxVerDir = 'nginx-1.30.5'
# Browser UA for downloads: some CDNs (Oracle) reject script user-agents.
$script:BrowserUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'

function Install-NativeMysql {
    $svc = Get-Service 'HRM-MySQL' -ErrorAction SilentlyContinue
    if ($svc) {
        Write-Ok "MySQL service HRM-MySQL already exists (status $($svc.Status))"
        return $true
    }
    $base = Join-Path $script:NativeRoot 'mysql'
    $verDir = Join-Path $base $script:MysqlVerDir
    $mysqld = Join-Path $verDir 'bin\mysqld.exe'
    if (-not (Test-Path -LiteralPath $mysqld)) {
        $zip = Join-Path ([System.IO.Path]::GetTempPath()) $script:MysqlZipName
        if (-not (Test-Path -LiteralPath $zip)) {
            Write-Info 'Downloading MySQL 8.4 LTS ZIP from Oracle CDN (several hundred MB) ...'
            try {
                [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
                $pp = $ProgressPreference; $ProgressPreference = 'SilentlyContinue'
                try { Invoke-WebRequest -Uri $script:MysqlZipUrl -OutFile $zip -UseBasicParsing -UserAgent $script:BrowserUa }
                finally { $ProgressPreference = $pp }
            } catch { throw "MySQL download failed: $($_.Exception.Message) - use Laragon instead (-AutoInstall without -Native)" }
        }
        $mb = [math]::Round(((Get-Item -LiteralPath $zip).Length / 1MB), 1)
        if (((Get-Item -LiteralPath $zip).Length) -lt 50MB) { throw "MySQL zip suspiciously small (${mb}MB) - delete $zip and re-run" }
        Write-Ok "MySQL zip ready (${mb}MB)"
        try { New-Item -ItemType Directory -Path $base -Force | Out-Null } catch {}
        Write-Info 'Extracting MySQL (takes a few minutes) ...'
        try { Expand-Archive -LiteralPath $zip -DestinationPath $base -Force }
        catch { throw "MySQL extraction failed: $($_.Exception.Message)" }
        try { Remove-Item -LiteralPath $zip -Force -ErrorAction SilentlyContinue } catch {}
    }
    if (-not (Test-Path -LiteralPath $mysqld)) { throw "mysqld not found after extract: $mysqld" }
    $script:NativeMysqlDir = $verDir
    Write-Ok "MySQL binaries: $verDir"
    $dataDir = Join-Path $script:NativeRoot 'mysql-data'
    try { New-Item -ItemType Directory -Path $dataDir -Force | Out-Null } catch {}
    $ini = Join-Path $verDir 'my.ini'
    # NOTE: never use `'a' + (...)` concatenation as a bare @() element:
    # PowerShell 5.1 splits it into TWO array elements (proven 2026-10-01).
    # Precompute full lines in statement position (always safe) instead.
    $verDirFwd = $verDir -replace '\\', '/'
    $dataDirFwd = $dataDir -replace '\\', '/'
    $iniLines = @(
        '[mysqld]',
        "basedir=$verDirFwd",
        "datadir=$dataDirFwd",
        'port=3306',
        'character-set-server=utf8mb4',
        'collation-server=utf8mb4_unicode_ci',
        'max_connections=200'
    )
    Set-Content -LiteralPath $ini -Value $iniLines -Encoding ASCII
    Write-Ok "my.ini written: $ini"
    $hasData = (Test-Path -LiteralPath (Join-Path $dataDir 'mysql.ib')) -or (Test-Path -LiteralPath (Join-Path $dataDir 'ibdata1'))
    if (-not $hasData) {
        Write-Info 'Initializing MySQL data dir with empty root (takes a minute) ...'
        # Start-Process (not `&`): the `&` operator misreports forking
        # mysqld invocations (silent exit 1 with no output, proven 2026-10-01).
        $initProc = Start-Process -FilePath $mysqld -ArgumentList '--initialize-insecure', ('--datadir=' + ($dataDir -replace '\\', '/')) -Wait -PassThru -NoNewWindow
        if ($initProc.ExitCode -ne 0) { throw "mysqld --initialize-insecure exited $($initProc.ExitCode) - see the data-dir .err log" }
        $hasData = (Test-Path -LiteralPath (Join-Path $dataDir 'mysql.ib')) -or (Test-Path -LiteralPath (Join-Path $dataDir 'ibdata1'))
        if (-not $hasData) { throw 'data dir initialization did not produce system tables - see the error log in the data dir' }
        Write-Ok 'data dir initialized (root has empty password)'
    } else { Write-Ok 'data dir already initialized (reusing)' }
    Write-Info 'Registering Windows service HRM-MySQL (automatic start) ...'
    $instProc = Start-Process -FilePath $mysqld -ArgumentList '--install', 'HRM-MySQL', ('--defaults-file=' + ($ini -replace '\\', '/')) -Wait -PassThru -NoNewWindow
    if ($instProc.ExitCode -ne 0) { throw "mysqld --install exited $($instProc.ExitCode)" }
    if (-not (Get-Service 'HRM-MySQL' -ErrorAction SilentlyContinue)) { throw 'HRM-MySQL service was not created - run mysqld manually once to see the error' }
    try { Set-Service -Name 'HRM-MySQL' -StartupType Automatic } catch {}
    try { Start-Service -Name 'HRM-MySQL' } catch { throw "could not start HRM-MySQL: $($_.Exception.Message)" }
    for ($i = 1; $i -le 18; $i++) {
        Start-Sleep -Seconds 5
        if (Test-Tcp '127.0.0.1' 3306) { break }
    }
    if (-not (Test-Tcp '127.0.0.1' 3306)) {
        $errLog = Get-ChildItem (Join-Path $dataDir '*.err') -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        if ($errLog) { Write-Host '  --- tail of MySQL error log ---' -ForegroundColor Yellow; Get-Content -LiteralPath $errLog.FullName -Tail 15 | ForEach-Object { Write-Host "  $_" -ForegroundColor Yellow } }
        throw 'HRM-MySQL did not listen on 3306 within 90s - inspect the error log above'
    }
    Write-Ok 'HRM-MySQL running on 3306 (service, automatic)'
    # Belt and braces: TCP clients connect to 127.0.0.1, which reverse-resolves
    # to localhost on Windows, but an explicit account removes all doubt.
    try {
        $mysqlExe = Join-Path $verDir 'bin\mysql.exe'
        & $mysqlExe -u root -e "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION; FLUSH PRIVILEGES;" | Out-Null
        Write-Ok "127.0.0.1 root access ensured"
    } catch { Write-Warn '127.0.0.1 grant skipped (root@localhost normally suffices on Windows)' }
    return $true
}

function Install-NativeRedis {
    $svc = Get-Service 'hrm-redis' -ErrorAction SilentlyContinue
    if ($svc) {
        Write-Ok "Redis service hrm-redis already exists (status $($svc.Status))"
        return $true
    }
    $dir = Join-Path $script:NativeRoot 'redis'
    $exe = Join-Path $dir 'redis-server.exe'
    if (-not (Test-Path -LiteralPath $exe)) {
        $zip = Join-Path ([System.IO.Path]::GetTempPath()) $script:RedisZipName
        if (-not (Test-Path -LiteralPath $zip)) {
            Write-Info 'Downloading Redis 5.0.14.1 for Windows (tporadowski, ~13MB) ...'
            try {
                [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
                $pp = $ProgressPreference; $ProgressPreference = 'SilentlyContinue'
                try { Invoke-WebRequest -Uri $script:RedisZipUrl -OutFile $zip -UseBasicParsing -UserAgent $script:BrowserUa }
                finally { $ProgressPreference = $pp }
            } catch { throw "Redis download failed: $($_.Exception.Message)" }
        }
        if (((Get-Item -LiteralPath $zip).Length) -lt 1MB) { throw "Redis zip suspiciously small - delete $zip and re-run" }
        try { New-Item -ItemType Directory -Path $dir -Force | Out-Null } catch {}
        Write-Info 'Extracting Redis ...'
        try { Expand-Archive -LiteralPath $zip -DestinationPath $dir -Force }
        catch { throw "Redis extraction failed: $($_.Exception.Message)" }
        try { Remove-Item -LiteralPath $zip -Force -ErrorAction SilentlyContinue } catch {}
        $found = Get-ChildItem $dir -Recurse -Filter 'redis-server.exe' -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($found -and ($found.Directory.FullName -ne $dir)) {
            Get-ChildItem -LiteralPath $found.Directory.FullName | ForEach-Object { Move-Item -LiteralPath $_.FullName -Destination $dir -Force }
            Write-Ok 'Redis binaries normalized to a flat layout'
        }
    }
    if (-not (Test-Path -LiteralPath $exe)) { throw "redis-server.exe not found after extract: $exe" }
    $script:NativeRedisDir = $dir
    Write-Ok "Redis binaries: $dir"
    $dataDir = Join-Path $script:NativeRoot 'redis-data'
    try { New-Item -ItemType Directory -Path $dataDir -Force | Out-Null } catch {}
    # Own conf copy: absolute dir/logfile (a service starts in System32, so
    # relative paths would scatter data). Later lines override earlier ones.
    $tpl = Get-ChildItem $dir -Filter '*.conf' -ErrorAction SilentlyContinue | Where-Object { $_.Name -match 'windows-service|windows' } | Select-Object -First 1
    if (-not $tpl) { throw 'no redis .conf template found in the Redis package' }
    $conf = Join-Path $dir 'hrm-redis.conf'
    Copy-Item -LiteralPath $tpl.FullName -Destination $conf -Force
    # Same @() concatenation rule as my.ini above: full lines precomputed.
    $dataDirFwd = $dataDir -replace '\\', '/'
    $redisDirLine = 'dir ' + $dataDirFwd
    $redisLogLine = 'logfile ' + $dataDirFwd + '/redis.log'
    Add-Content -LiteralPath $conf -Value @('', '# --- HRM native service overrides (absolute paths) ---', 'port 6379', 'bind 127.0.0.1 ::1', $redisDirLine, 'dbfilename dump.rdb', $redisLogLine) -Encoding ASCII
    Write-Ok "Redis conf: $conf"
    Write-Info 'Registering Windows service hrm-redis (automatic start) ...'
    try { & $exe $conf --service-install --service-name hrm-redis --port 6379 | Out-Null }
    catch { throw "redis --service-install failed: $($_.Exception.Message)" }
    $svc2 = Get-Service 'hrm-redis' -ErrorAction SilentlyContinue
    if (-not $svc2) { throw 'hrm-redis service was not created - run redis-server.exe manually once to see the error' }
    try { Set-Service -Name 'hrm-redis' -StartupType Automatic } catch {}
    try { & $exe --service-start --service-name hrm-redis | Out-Null }
    catch { throw "redis --service-start failed: $($_.Exception.Message)" }
    for ($i = 1; $i -le 12; $i++) {
        Start-Sleep -Seconds 5
        if (Test-Tcp '127.0.0.1' 6379) { break }
    }
    if (-not (Test-Tcp '127.0.0.1' 6379)) { throw 'hrm-redis did not listen on 6379 within 60s' }
    Write-Ok 'hrm-redis running on 6379 (service, automatic)'
    return $true
}

# Nginx web tier (Option B): user browsers get :80 with a real concurrent
# backend, while `artisan serve :8000` keeps serving only fast ADMS/bridge
# callbacks (single-threaded on Windows by design - see supervisor note).
# Layout mirrors production: C:\nginx ran standalone; here everything lives
# under C:\hrm-services\nginx with conf generated from production/nginx-hrm.conf.
# Runtime ownership belongs to the supervisor (Start-HRM-Windows.ps1); this
# function only stages FILES and validates the config (`nginx -t`).
function Install-NativeNginx([string] $ProjectRoot, [string] $LanIp) {
    $nRoot = Join-Path $script:NativeRoot 'nginx'
    $exe = Join-Path $nRoot 'nginx.exe'
    if (-not (Test-Path -LiteralPath $exe)) {
        $zip = Join-Path ([System.IO.Path]::GetTempPath()) $script:NginxZipName
        if (-not (Test-Path -LiteralPath $zip)) {
            Write-Info 'Downloading Nginx 1.30.5 stable for Windows (~2MB, nginx.org) ...'
            try {
                $pp = $ProgressPreference; $ProgressPreference = 'SilentlyContinue'
                try { Invoke-WebRequest -Uri $script:NginxZipUrl -OutFile $zip -UseBasicParsing -UserAgent $script:BrowserUa }
                finally { $ProgressPreference = $pp }
            } catch { throw "Nginx download failed: $($_.Exception.Message)" }
        }
        if (((Get-Item -LiteralPath $zip).Length) -lt 500KB) { throw "Nginx zip suspiciously small - delete $zip and re-run" }
        $stage = Join-Path ([System.IO.Path]::GetTempPath()) 'hrm-nginx-stage'
        if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force }
        try { New-Item -ItemType Directory -Path $stage -Force | Out-Null } catch {}
        Write-Info 'Extracting Nginx ...'
        try { Expand-Archive -LiteralPath $zip -DestinationPath $stage -Force }
        catch { throw "Nginx extraction failed: $($_.Exception.Message)" }
        $src = Join-Path $stage $script:NginxVerDir
        if (-not (Test-Path -LiteralPath (Join-Path $src 'nginx.exe'))) { throw "nginx.exe not found in the extracted package" }
        try { New-Item -ItemType Directory -Path $nRoot -Force | Out-Null } catch {}
        Get-ChildItem -LiteralPath $src | ForEach-Object { Move-Item -LiteralPath $_.FullName -Destination $nRoot -Force }
        Remove-Item -LiteralPath $stage -Recurse -Force -ErrorAction SilentlyContinue
        try { Remove-Item -LiteralPath $zip -Force -ErrorAction SilentlyContinue } catch {}
    }
    if (-not (Test-Path -LiteralPath $exe)) { throw "nginx.exe not found after extract: $exe" }
    $script:NativeNginxDir = $nRoot
    Write-Ok "Nginx binaries: $nRoot"
    foreach ($req in @('mime.types', 'fastcgi_params')) {
        if (-not (Test-Path -LiteralPath (Join-Path $nRoot "conf\$req"))) { throw "Nginx package incomplete: conf\$req missing" }
    }
    $tpl = Join-Path $ProjectRoot 'production\nginx-hrm.conf'
    if (-not (Test-Path -LiteralPath $tpl)) { throw "Nginx template missing: $tpl" }
    $publicRoot = (($ProjectRoot -replace '\\', '/') + '/public')
    $storagePub = (($ProjectRoot -replace '\\', '/') + '/storage/app/public')
    $confText = [System.IO.File]::ReadAllText($tpl)
    $confText = $confText.Replace('%%PUBLIC_ROOT%%', $publicRoot)
    $confText = $confText.Replace('%%LAN_IP%%', $LanIp)
    $confText = $confText.Replace('%%STORAGE_PUBLIC%%', $storagePub)
    if ($confText -match '%%[A-Z_]+%%') { throw 'Nginx template tokens left unreplaced - check production/nginx-hrm.conf' }
    $confPath = Join-Path $nRoot 'conf\nginx.conf'
    Copy-Item -LiteralPath $confPath -Destination ($confPath + '.dist-bak') -Force -ErrorAction SilentlyContinue
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($confPath, $confText, $utf8NoBom)
    Write-Ok "Nginx conf written: $confPath (root=$publicRoot, lan=$LanIp)"
    foreach ($d in @('logs', 'temp', 'temp\client_body_temp', 'temp\proxy_temp', 'temp\fastcgi_temp', 'temp\uwsgi_temp', 'temp\scgi_temp')) {
        try { New-Item -ItemType Directory -Path (Join-Path $nRoot $d) -Force | Out-Null } catch {}
    }
    $t = Start-Process -FilePath $exe -ArgumentList '-t', '-c', $confPath, '-p', $nRoot -Wait -PassThru -NoNewWindow
    if ($t.ExitCode -ne 0) { throw "nginx -t FAILED for the generated conf (exit $($t.ExitCode)) - inspect $confPath" }
    Write-Ok 'nginx -t: configuration test successful'
    return $true
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

$LRootAudit = Find-LaragonRoot
$mysqlBinGlob = if ($LRootAudit) { Join-Path $LRootAudit 'bin\mysql\*\bin\mysqld.exe' } else { 'C:\laragon\bin\mysql\*\bin\mysqld.exe' }
$mysqlUp = Test-Tcp '127.0.0.1' 3306
$mysqlSvc = Get-Service 'HRM-MySQL' -ErrorAction SilentlyContinue
$laragonMysql = Get-ChildItem $mysqlBinGlob -ErrorAction SilentlyContinue | Select-Object -First 1
if ($mysqlUp) { Write-Ok 'MySQL: port 3306 reachable' }
elseif ($mysqlSvc) { Write-Warn "MySQL: service HRM-MySQL exists but not listening (status: $($mysqlSvc.Status)) - will try to start it" }
elseif ($laragonMysql) { Write-Warn "MySQL: Laragon mysqld found at $($laragonMysql.FullName) but not running - start Laragon or the HRM-MySQL service" }
else { Write-Warn 'MySQL: not detected (SQLite fallback will be used unless you install MySQL 8.x / Laragon)' }

$redisUp = Test-Tcp '127.0.0.1' 6379
$redisSvc = Get-Service 'hrm-redis' -ErrorAction SilentlyContinue
$laragonRedisExe = $null
$laragonRedisConf = $null
if ($LRootAudit) {
    $rExe = Get-ChildItem (Join-Path $LRootAudit 'bin\redis\*\redis-server.exe') -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
    if ($rExe) {
        $laragonRedisExe = $rExe.FullName
        foreach ($cf in @('redis.windows.conf', 'redis.conf')) {
            $cp = Join-Path $rExe.Directory.FullName $cf
            if (Test-Path -LiteralPath $cp) { $laragonRedisConf = $cp; break }
        }
    }
}
$laragonRedis = (-not [string]::IsNullOrWhiteSpace($laragonRedisExe))
if ($redisUp) { Write-Ok 'Redis: port 6379 reachable' }
elseif ($redisSvc) { Write-Warn "Redis: service hrm-redis exists (status: $($redisSvc.Status)) - will try to start it" }
elseif ($laragonRedis) { Write-Warn 'Redis: Laragon redis-server.exe found but not running - will try to start it' }
else { Write-Warn 'Redis: not detected - queue/cache/session fall back to database/file automatically' }

if ($AutoInstall) {
    Write-Host ''
    Write-Host '--- AutoInstall (verified package IDs, Oct 2026) ---' -ForegroundColor Cyan
    try { & winget --version | Out-Null } catch { throw 'winget not available - install App Installer from Microsoft Store first.' }
    if (-not $Minimal) {
        # Full tier: ONE bundle for PHP + MySQL + Redis + Node + git + Composer.
        if (-not (Find-LaragonRoot)) {
            Write-Info 'Installing Laragon Full (~230MB: PHP + MySQL + Redis + Node + git + Composer) - takes several minutes ...'
            try { Install-WithWinget 'LeNgocKhoa.Laragon' 'Laragon Full' }
            catch { Write-Warn "Laragon install failed: $($_.Exception.Message) - falling back to individual packages" }
        }
        $lr2 = Find-LaragonRoot
        if ($lr2) { Import-LaragonTools $lr2 }
        Invoke-ToolReprobe
    } else {
        Write-Info 'Minimal stack: Laragon skipped (SQLite DB, no MySQL/Redis services)'
    }
    if (-not $phpOk) {
        try { Install-WithWinget 'Microsoft.VCRedist.2015+.x64' 'VC++ Redistributable' } catch { Write-Warn $_.Exception.Message }
        try { Install-PhpNts } catch { Write-Warn $_.Exception.Message }
    }
    if (-not $nodeOk) { try { Install-WithWinget 'OpenJS.NodeJS.LTS' 'Node.js LTS' } catch { Write-Warn $_.Exception.Message } }
    if (-not $gitOk) { try { Install-WithWinget 'Git.Git' 'Git' } catch { Write-Warn $_.Exception.Message } }
    if (-not $pyOk) { try { Install-WithWinget 'Python.Python.3.12' 'Python 3.12' } catch { Write-Warn $_.Exception.Message } }
    if (-not $composerOk) { try { Install-ComposerSetup } catch { Write-Warn $_.Exception.Message } }
    $env:Path = $env:Path + ';' + [System.Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path', 'User')
    # Winget installers only take effect for NEW processes; re-probe inside
    # this session so freshly installed tools are picked up without re-run.
    Invoke-ToolReprobe
    Write-Warn 'If PATH still misses new tools, CLOSE this window, open a new one, and re-run the installer.'
}

if ($Native) {
    Write-Host ''
    Write-Host '--- Native services (MySQL 8.4 + Redis 5 as Windows services) ---' -ForegroundColor Cyan
    if (-not $isAdmin) { throw '-Native registers Windows services and requires Administrator. Re-run elevated (double-click INSTALL-HRM.bat).' }
    # Native implies tool setup: winget tools first when they are missing.
    if (-not ($phpOk -and $composerOk -and $nodeOk -and $pyOk)) {
        Write-Info 'Native tier: installing missing tools first ...'
        try { & winget --version | Out-Null } catch { throw 'winget not available - install App Installer from Microsoft Store first.' }
        if (-not $phpOk) {
            try { Install-WithWinget 'Microsoft.VCRedist.2015+.x64' 'VC++ Redistributable' } catch { Write-Warn $_.Exception.Message }
            try { Install-PhpNts } catch { Write-Warn $_.Exception.Message }
        }
        if (-not $nodeOk) { try { Install-WithWinget 'OpenJS.NodeJS.LTS' 'Node.js LTS' } catch { Write-Warn $_.Exception.Message } }
        if (-not $gitOk) { try { Install-WithWinget 'Git.Git' 'Git' } catch { Write-Warn $_.Exception.Message } }
        if (-not $pyOk) { try { Install-WithWinget 'Python.Python.3.12' 'Python 3.12' } catch { Write-Warn $_.Exception.Message } }
        if (-not $composerOk) { try { Install-ComposerSetup } catch { Write-Warn $_.Exception.Message } }
        $env:Path = $env:Path + ';' + [System.Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path', 'User')
        Invoke-ToolReprobe
    }
    try { Install-NativeMysql } catch { Write-Err "native MySQL failed: $($_.Exception.Message)"; throw }
    try { Install-NativeRedis } catch { Write-Err "native Redis failed: $($_.Exception.Message)"; throw }
    try { Install-NativeNginx $Root (Get-LanIp) } catch { Write-Err "native Nginx failed: $($_.Exception.Message)"; throw }
    $mysqlUp = Test-Tcp '127.0.0.1' 3306
    $redisUp = Test-Tcp '127.0.0.1' 6379
}

$missing = @($checks | Where-Object { -not $_.Ok })
if ($CheckOnly) {
    Write-Host ''
    Write-Host '--- integration status (informational, fixed by a full run as admin) ---' -ForegroundColor Cyan
    foreach ($p in @(80, 8000, 8080, 8081, 5000)) {
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
        Write-Host '  Re-run with -AutoInstall (Laragon bundle, or individual packages with -Minimal).' -ForegroundColor DarkGray
    }
    try { Stop-Transcript | Out-Null } catch {}
    if ($missing.Count -eq 0) { exit 0 } else { exit 1 }
}

# Laragon recovery (full run only): import its PHP/Node/Python/git/Composer
# into this session (Laragon leaves them out of PATH unless you click
# Tools > PATH > Add Laragon to PATH in its GUI).
$LRoot = Find-LaragonRoot
if ($LRoot) {
    Write-Ok "Laragon bundle: $LRoot"
    Import-LaragonTools $LRoot
    Invoke-ToolReprobe
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
        # No loaded ini (fresh builds ship only php.ini-production): seed it.
        if (([string]::IsNullOrWhiteSpace($iniFile) -or (-not (Test-Path -LiteralPath $iniFile))) -and ($phpBin)) {
            $prodIni = Join-Path (Split-Path $phpBin -Parent) 'php.ini-production'
            $newIni = Join-Path (Split-Path $phpBin -Parent) 'php.ini'
            if (Test-Path -LiteralPath $prodIni) {
                Copy-Item -LiteralPath $prodIni -Destination $newIni -Force
                $iniFile = $newIni
                Write-Ok "php.ini seeded from php.ini-production: $newIni"
            }
        }
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
    if ((-not $redisUp) -and (-not [string]::IsNullOrWhiteSpace($laragonRedisExe)) -and (-not [string]::IsNullOrWhiteSpace($laragonRedisConf))) {
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

# -Native promises MySQL: a fresh template defaults to sqlite, so point a
# JUST-CREATED .env at the native server (never touch an existing .env).
if ($envJustCreated -and $Native) {
    Set-DotEnvValue $envFile 'DB_CONNECTION' 'mysql'
    Set-DotEnvValue $envFile 'DB_HOST' '127.0.0.1'
    Set-DotEnvValue $envFile 'DB_PORT' '3306'
    Set-DotEnvValue $envFile 'DB_DATABASE' 'hrmair'
    Set-DotEnvValue $envFile 'DB_USERNAME' 'root'
    Set-DotEnvValue $envFile 'DB_PASSWORD' ''
    Write-Ok 'fresh .env defaulted to native MySQL (hrmair/root/empty) for -Native'
}

# DB mode decision
$conn = 'sqlite'
$envText = Get-Content -LiteralPath $envFile -Raw
if ($envText -match '(?m)^DB_CONNECTION=(\w+)') { $conn = $Matches[1].Trim().ToLower() }
if ($DbMode -ne 'auto') { $conn = $DbMode }
if (($conn -eq 'mysql') -and (-not (Test-Tcp '127.0.0.1' 3306))) {
    Write-Info 'MySQL 3306 dark - trying to start Laragon MySQL for this install ...'
    if (Start-LaragonMysql) { Write-Ok 'Laragon MySQL is up (this session; register a service for reboots per production/DEPLOY-NEW-MACHINE.md)' }
    else { Write-Warn 'Laragon MySQL could not be started automatically - open Laragon and click Start All, then re-run' }
}
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

    # Native tier: point the backup module at the native MySQL client tools
    # (templates point at Laragon paths which do not exist without Laragon).
    if ($script:NativeMysqlDir) {
        $nd = Join-Path $script:NativeMysqlDir 'bin\mysqldump.exe'
        $nm = Join-Path $script:NativeMysqlDir 'bin\mysql.exe'
        if ((Test-Path -LiteralPath $nd) -and (Test-Path -LiteralPath $nm)) {
            Set-DotEnvValue $envFile 'BACKUP_MYSQLDUMP_PATH' $nd
            Set-DotEnvValue $envFile 'BACKUP_MYSQL_PATH' $nm
            Write-Ok 'backup tools pointed at native MySQL bin'
        }
    }

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
            # $PyBin resolves to `py -3`, `python`, or Laragon python.exe
            # (no `py` launcher on Laragon-only machines) - see Invoke-ToolReprobe.
            Write-Info "creating venv ($PyBin -m venv zkteco-service\venv) ..."
            & $PyBin @PyArgs -m venv (Join-Path $Root 'zkteco-service\venv')
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

    # 4) Firewall: LAN browsers + fingerprint devices must reach these ports
    # (:80 = Nginx user traffic, 8080 = Reverb, 8081 = ADMS, 5000 = bridge).
    foreach ($p in @(80, 8000, 8080, 8081, 5000)) {
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
    # -Native promises real services: they must exist and run afterwards.
    if ($Native) {
        foreach ($sn in @('HRM-MySQL', 'hrm-redis')) {
            $sv = Get-Service $sn -ErrorAction SilentlyContinue
            if (-not $sv) { Write-Err "native service missing: $sn"; $fail++ }
            elseif ($sv.Status -ne 'Running') { Write-Err "native service not running: $sn ($($sv.Status)) - run: Start-Service $sn"; $fail++ }
            else { Write-Ok "native service running: $sn" }
        }
    }
    # Nginx web tier: files staged by Install-NativeNginx, runtime owned by
    # the supervisor. Validate the config here (needs no ports, no admin).
    $nExe = Join-Path 'C:\hrm-services\nginx' 'nginx.exe'
    $nConf = Join-Path 'C:\hrm-services\nginx' 'conf\nginx.conf'
    if ((Test-Path -LiteralPath $nExe) -and (Test-Path -LiteralPath $nConf)) {
        Assert-Path $nExe 'nginx binary (user web tier :80)'
        $nt = Start-Process -FilePath $nExe -ArgumentList '-t', '-c', $nConf, '-p', 'C:\hrm-services\nginx' -Wait -PassThru -NoNewWindow
        if ($nt.ExitCode -eq 0) { Write-Ok 'nginx -t: configuration test successful' }
        else { Write-Err 'nginx -t FAILED - inspect C:\hrm-services\nginx\conf\nginx.conf'; $fail++ }
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
