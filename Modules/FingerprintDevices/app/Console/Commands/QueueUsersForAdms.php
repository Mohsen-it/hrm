<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\Users\Models\User;

class QueueUsersForAdms extends Command
{
    protected $signature = 'fingerprints:queue-users
                            {--device= : Device ID or serial number (default: all devices)}
                            {--dry-run : Show what would be queued without actually queuing}';

    protected $description = 'Queue all active users for ADMS push to fingerprint devices';

    public function handle(DeviceCommandService $commandService): int
    {
        $deviceFilter = $this->option('device');
        $dryRun = $this->option('dry-run');

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

        $totalQueued = 0;

        foreach ($devices as $device) {
            $this->info("Device: {$device->name} (SN: {$device->serial_number}, IP: {$device->ip_address})");

            if ($dryRun) {
                $userCount = User::query()
                    ->where('status', 1)
                    ->whereNotNull('employee_code')
                    ->where('employee_code', '!=', '')
                    ->count();
                $this->line("  [DRY RUN] Would queue {$userCount} user commands.");
                $totalQueued += $userCount;
            } else {
                $result = $commandService->queueAllUsersForDevice($device->id);
                $this->line("  Queued: {$result['queued']} users");
                $totalQueued += $result['queued'];
            }

            $this->newLine();
        }

        $this->info("Total: {$totalQueued} user commands queued.");
        $this->info('Devices will pick up commands on next getrequest poll (within ~5 seconds).');

        return self::SUCCESS;
    }
}
