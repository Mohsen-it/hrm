<?php

namespace Modules\Attendance\Console\Commands;

use App\Services\ZKTecoPythonBridgeService;
use Illuminate\Console\Command;
use Modules\Attendance\Services\RawAttendanceLogService;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;
use Modules\Users\Models\User;

/**
 * Attendance:SyncFingerprints — pull attendance rows from every active device
 * and persist them as raw attendance logs.
 */
class SyncFingerprintsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:sync-fingerprints
                            {--device= : Restrict to a single device id}
                            {--no-bridge : Bypass the Python bridge (use the PHP adapter only)}
                            {--no-process : Skip processing raw logs into sessions}';

    /**
     * The console command description.
     */
    protected $description = 'Pull attendance rows from one or more fingerprint devices and save them';

    /**
     * Execute the console command.
     */
    public function handle(
        FingerprintDeviceService $deviceService,
        ZKTecoPythonBridgeService $bridge,
        RawAttendanceLogService $rawLogService,
    ): int {
        $deviceId = $this->option('device');
        $useBridge = ! $this->option('no-bridge');
        $noProcess = $this->option('no-process');

        if ($useBridge && ! $bridge->ensureServiceRunning()) {
            $this->components->error('ZKTeco Python bridge is not reachable. Use --no-bridge to skip it.');

            return self::FAILURE;
        }

        $query = FingerprintDevice::query()->where('status', '!=', 'deactivated');
        if ($deviceId !== null) {
            $query->where('id', (int) $deviceId);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->components->warn('No active devices to sync.');

            return self::SUCCESS;
        }

        $totalPulled = 0;
        $totalSaved = 0;

        foreach ($devices as $device) {
            $records = $deviceService->syncAttendance($device);
            $pulled = is_array($records) ? count($records) : 0;
            $totalPulled += $pulled;

            if ($pulled === 0) {
                $this->line("- Device #{$device->id} ({$device->name}): 0 records");

                continue;
            }

            // Build employee_code => user_id mapping
            $employeeCodeToUserId = User::pluck('id', 'employee_code')->toArray();

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

            $this->line("- Device #{$device->id} ({$device->name}): pulled={$pulled}, saved={$result['inserted']}, skipped={$result['skipped']}");
        }

        $this->info("Done. Pulled {$totalPulled} rows, saved {$totalSaved} new records from ".$devices->count().' device(s).');

        return self::SUCCESS;
    }

    /**
     * Resolve punch type from device record.
     */
    protected function resolvePunchType(array $record): string
    {
        // ZKTeco status: 0 = check-in, 1 = check-out
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
