<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\DeviceFullSyncService;

class PullTemplatesDirect extends Command
{
    protected $signature = 'fingerprints:pull-direct
                            {--device= : Device ID or serial number (default: all)}
                            {--test-only : Only test connectivity, do not pull}
                            {--force : Pull even if already synced recently}';

    protected $description = 'Pull users, fingerprints, and face templates via direct TCP connection (pyzk)';

    public function handle(DeviceFullSyncService $syncService): int
    {
        $deviceFilter = $this->option('device');
        $testOnly = $this->option('test-only');
        $force = $this->option('force');

        $bridgeUrl = rtrim(config('attendanceintegration.drivers.zkteco.bridge_url', 'http://127.0.0.1:5000'), '/');

        // Test Python bridge
        $this->info('Checking Python bridge...');
        try {
            $health = Http::timeout(3)->get("{$bridgeUrl}/health");
            if (! $health->successful()) {
                $this->error('Python bridge is not running. Start it first:');
                $this->line('  cd D:\hrm\zkteco-service');
                $this->line('  python app.py');

                return self::FAILURE;
            }
            $this->info('Python bridge: OK');
        } catch (\Throwable $e) {
            $this->error('Cannot connect to Python bridge: '.$e->getMessage());
            $this->line('Start it first:');
            $this->line('  cd D:\hrm\zkteco-service');
            $this->line('  python app.py');

            return self::FAILURE;
        }

        $this->newLine();

        // Get devices
        $query = FingerprintDevice::query()->where('status', '!=', 'deactivated');
        if ($deviceFilter) {
            if (is_numeric($deviceFilter)) {
                $query->where('id', $deviceFilter);
            } else {
                $query->where('serial_number', $deviceFilter);
            }
        }
        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->error('No devices found.');

            return self::FAILURE;
        }

        $this->info("Found {$devices->count()} device(s).");
        $this->newLine();

        // Test connectivity first
        $reachableDevices = collect();
        foreach ($devices as $device) {
            $this->line("Testing: {$device->name} ({$device->ip_address}:{$device->port})...");

            try {
                $result = Http::timeout(10)->post("{$bridgeUrl}/device/test-connection", [
                    'ip' => $device->ip_address,
                    'port' => $device->port,
                    'password' => (int) $device->comm_key,
                    'timeout' => 10,
                ]);

                if ($result->successful() && ($result->json('connected') ?? false)) {
                    $this->info('  Connected!');
                    $reachableDevices->push($device);
                } else {
                    $this->error('  Cannot connect: '.($result->json('error') ?? 'timeout'));
                }
            } catch (\Throwable $e) {
                $this->error('  Connection failed: '.$e->getMessage());
            }
        }

        $this->newLine();

        if ($reachableDevices->isEmpty()) {
            $this->error('No devices reachable. Enable TCP/IP on the devices:');
            $this->line('  Menu → COMMUNICATION → TCP/IP → Enable');

            return self::FAILURE;
        }

        if ($testOnly) {
            $this->info("Reachable: {$reachableDevices->count()}/{$devices->count()}");

            return self::SUCCESS;
        }

        // Pull from each reachable device
        $this->info("Pulling from {$reachableDevices->count()} device(s)...");
        $this->newLine();

        foreach ($reachableDevices as $device) {
            $this->info("=== {$device->name} (SN: {$device->serial_number}) ===");

            $result = $syncService->run($device, [
                'info' => true,
                'users' => true,
                'fingerprints' => true,
                'face_photos' => true,
                'attendance' => false,
            ], function ($step, $status, $message, $percent, $data) {
                $icon = match ($status) {
                    'running' => '...',
                    'ok' => 'OK',
                    'failed' => 'FAILED',
                    default => $status,
                };
                $this->line("  [{$icon}] {$step}: {$message} ({$percent}%)");
            });

            // Show summary
            $totals = $result['totals'] ?? [];
            $this->newLine();
            $this->line('  Users on device: '.($totals['users_on_device'] ?? 0));
            $this->line('  Users matched: '.($totals['users_matched'] ?? 0));
            $this->line('  Fingerprints saved: '.($totals['fingerprints_saved'] ?? 0));
            $this->line('  Face photos saved: '.($totals['face_photos_saved'] ?? 0));

            if (! empty($result['errors'])) {
                $this->newLine();
                $this->error('  Errors:');
                foreach ($result['errors'] as $err) {
                    $this->line("    - {$err}");
                }
            }

            $this->newLine();
        }

        $this->info('Done!');

        return self::SUCCESS;
    }
}
