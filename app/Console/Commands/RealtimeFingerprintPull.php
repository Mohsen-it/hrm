<?php

namespace App\Console\Commands;

use App\Services\ZKTecoPythonBridgeService;
use Illuminate\Console\Command;
use Modules\Attendance\Services\RawAttendanceLogService;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;
use Modules\Users\Models\User;

/**
 * RealtimeFingerprintPull — long-running poll of every active device.
 *
 * Every N seconds the command asks every active device for its latest
 * attendance rows and persists them as raw attendance logs. Designed
 * to be run as a single long-running process (or run for `--minutes`
 * minutes at a time).
 */
class RealtimeFingerprintPull extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fingerprints:realtime-pull
                            {--interval=30 : Seconds between polls}
                            {--minutes=0 : Stop after N minutes (0 = run forever)}';

    /**
     * The console command description.
     */
    protected $description = 'Continuously pull attendance from every active fingerprint device and save them';

    /**
     * Execute the console command.
     */
    public function handle(
        ZKTecoPythonBridgeService $bridge,
        FingerprintDeviceService $deviceService,
        RawAttendanceLogService $rawLogService,
    ): int {
        $interval = max(5, (int) $this->option('interval'));
        $minutes = (int) $this->option('minutes');
        $deadline = $minutes > 0 ? now()->addMinutes($minutes) : null;

        if (! $bridge->ensureServiceRunning()) {
            $this->components->error('ZKTeco Python bridge is not reachable.');

            return self::FAILURE;
        }

        $this->info("Starting real-time pull (interval={$interval}s)...");

        // Pre-load employee code mapping
        $employeeCodeToUserId = User::pluck('id', 'employee_code')->toArray();

        while (true) {
            $devices = FingerprintDevice::query()
                ->where('status', '!=', 'deactivated')
                ->get();

            $totalPulled = 0;
            $totalSaved = 0;

            foreach ($devices as $device) {
                $records = $deviceService->syncAttendance($device);
                $pulled = is_array($records) ? count($records) : 0;
                $totalPulled += $pulled;

                if ($pulled === 0) {
                    continue;
                }

                $rows = [];
                foreach ($records as $record) {
                    $externalId = trim((string) ($record['user_id'] ?? ''));
                    $timestamp = $record['timestamp'] ?? null;

                    if ($externalId === '' || ! $timestamp) {
                        continue;
                    }

                    $userPk = $employeeCodeToUserId[$externalId] ?? null;

                    $rows[] = [
                        'user_id' => $userPk,
                        'device_id' => $device->id,
                        'device_user_id' => $externalId,
                        'punch_time' => $timestamp,
                        'punch_type' => $this->resolvePunchType($record),
                        'verify_type' => $this->resolveVerifyType($record),
                        'work_code' => (int) ($record['status'] ?? 0),
                        'source' => 'device_pull',
                        'processed' => false,
                        'ip_address' => $device->ip_address,
                        'raw_data' => $record,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $result = $rawLogService->bulkImport($rows);
                $totalSaved += $result['inserted'];
            }

            $this->line('['.now()->toDateTimeString()."] polled {$devices->count()} device(s), pulled={$totalPulled}, saved={$totalSaved}");

            if ($deadline && now()->greaterThanOrEqualTo($deadline)) {
                $this->info('Reached the configured time limit; stopping.');

                return self::SUCCESS;
            }

            sleep($interval);
        }
    }

    /**
     * Resolve punch type from device record.
     */
    protected function resolvePunchType(array $record): string
    {
        $status = $record['status'] ?? null;
        if ($status !== null) {
            return ((int) $status) === 1 ? 'check_out' : 'check_in';
        }

        return 'unknown';
    }

    /**
     * Resolve verify type from device record.
     */
    protected function resolveVerifyType(array $record): string
    {
        $punch = $record['punch'] ?? null;
        if ($punch === null) {
            return 'fingerprint';
        }

        return match ((int) $punch) {
            0 => 'fingerprint',
            1 => 'card',
            2 => 'password',
            default => 'fingerprint',
        };
    }
}
