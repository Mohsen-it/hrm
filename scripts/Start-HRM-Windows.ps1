[CmdletBinding()]
param(
    [switch] $SkipBuild,
    [switch] $NoBridge,
    [switch] $Help
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ServerIp = '10.10.250.2'
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$LaravelPort = 8000
$ReverbPort = 8080
$AdmsPort = 8081
$BridgePort = 5000
$Python = Join-Path $Root 'zkteco-service\venv\Scripts\python.exe'

if ($Help) {
    Write-Host 'Usage: Start-HRM-Windows.bat [-SkipBuild] [-NoBridge]'
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
        throw "$Name cannot start because port $Port is already in use by PID $listener. Stop the old process first."
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
        [string] $LogPath
    )

    $arguments = '/d /c "{0} >> ""{1}"" 2>&1"' -f $Command, $LogPath
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
    & php artisan migrate:status *> (Join-Path $Root 'storage\logs\hrm-migration-status.log')
    if ($LASTEXITCODE -ne 0) { throw 'Database preflight failed. See storage\logs\hrm-migration-status.log.' }

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

$job = New-HrmJob
$services = [System.Collections.Generic.List[object]]::new()
try {
    $services.Add((Start-HrmProcess -Job $job -Name 'Laravel' -WorkingDirectory $Root -Command "php artisan serve --host=0.0.0.0 --port=$LaravelPort" -LogPath (Join-Path $Root 'storage\logs\hrm-laravel-server.log')))
    Wait-HrmPort -Port $LaravelPort -Name 'Laravel'

    $services.Add((Start-HrmProcess -Job $job -Name 'Queue worker' -WorkingDirectory $Root -Command 'php artisan queue:work --queue=default --tries=3 --timeout=90 --sleep=1 --memory=512' -LogPath (Join-Path $Root 'storage\logs\hrm-queue.log')))

    $services.Add((Start-HrmProcess -Job $job -Name 'Reverb' -WorkingDirectory $Root -Command "php artisan reverb:start --host=0.0.0.0 --port=$ReverbPort" -LogPath (Join-Path $Root 'storage\logs\hrm-reverb.log')))
    Wait-HrmPort -Port $ReverbPort -Name 'Reverb'

    $services.Add((Start-HrmProcess -Job $job -Name 'ADMS' -WorkingDirectory (Join-Path $Root 'zkteco-service') -Command "`"$Python`" adms_server.py --host 0.0.0.0 --port $AdmsPort --laravel http://127.0.0.1:$LaravelPort" -LogPath (Join-Path $Root 'zkteco-service\logs\adms-launcher.log')))
    Wait-HrmPort -Port $AdmsPort -Name 'ADMS'

    if (-not $NoBridge) {
        $bridgeCommand = "set `"ZKTECO_PYTHON_SERVICE_HOST=0.0.0.0`" && set `"ZKTECO_PYTHON_SERVICE_PORT=$BridgePort`" && `"$Python`" app.py"
        $services.Add((Start-HrmProcess -Job $job -Name 'ZKTeco bridge' -WorkingDirectory (Join-Path $Root 'zkteco-service') -Command $bridgeCommand -LogPath (Join-Path $Root 'zkteco-service\logs\bridge.log')))
        Wait-HrmPort -Port $BridgePort -Name 'ZKTeco bridge'
    }

    Write-Host ''
    Write-Host '[OK] HRM services are running:' -ForegroundColor Green
    Write-Host "     Laravel: http://${ServerIp}:$LaravelPort"
    Write-Host "     Reverb:  ws://${ServerIp}:$ReverbPort"
    Write-Host "     ADMS:    http://${ServerIp}:$AdmsPort"
    if (-not $NoBridge) { Write-Host "     Bridge:  http://${ServerIp}:$BridgePort" }
    Write-Host ''
    Write-Host 'Close this CMD window to stop and clean up every HRM process.' -ForegroundColor Cyan
    Write-Host 'Press Q to stop the services cleanly.' -ForegroundColor Cyan

    while ($true) {
        foreach ($service in $services) {
            if ($service.Process.HasExited) {
                Start-Sleep -Seconds 3
                Restart-HrmProcess -Job $job -Service $service
            }
        }

        if ([Console]::KeyAvailable -and [Console]::ReadKey($true).Key -eq [ConsoleKey]::Q) {
            break
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
