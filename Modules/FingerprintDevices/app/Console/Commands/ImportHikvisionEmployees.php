<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Support\AppliesDeviceOrgDefaults;
use Modules\Users\Models\User;

class ImportHikvisionEmployees extends Command
{
    use AppliesDeviceOrgDefaults;

    protected $signature = 'hikvision:import-employees
                            {--device= : Device ID to import from (default: first Hikvision device)}
                            {--expire-before= : Exclude users whose validity ended before this date (Y-m-d)}
                            {--dry-run : Show what would be done without saving}
                            {--no-fingerprints : Skip fingerprint template import}';

    protected $description = 'Import unique employees and fingerprint templates from Hikvision device';

    public function handle(
        DeviceAdapterResolver $adapterResolver,
    ): int {
        $dryRun = $this->option('dry-run');
        $skipFingerprints = $this->option('no-fingerprints');

        $expireBefore = $this->option('expire-before')
            ? Carbon::parse($this->option('expire-before'))
            : Carbon::parse('2026-07-15');

        $this->info('=== Hikvision Employee Import ===');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - no data will be saved');
            $this->newLine();
        }

        $device = $this->findDevice();
        if (! $device) {
            return self::FAILURE;
        }

        $adapter = $this->resolveAdapter($device, $adapterResolver);

        $this->info("Device: {$device->name} ({$device->ip_address})");
        $this->info("Filtering out users with validity ended before: {$expireBefore->format('Y-m-d')}");
        $this->newLine();

        // Step 1: Pull all users from device
        $this->info('Step 1/4: Pulling users from device...');
        $deviceUsers = $adapter->getUsers(
            $device->ip_address,
            $device->port,
            (string) $device->comm_key,
            (int) ($device->timeout ?? 30),
        );

        if (empty($deviceUsers)) {
            $this->warn('No users found on device.');

            return self::SUCCESS;
        }

        $this->info('  Found '.count($deviceUsers).' total users on device');
        $this->newLine();

        // Step 2: Filter out expired users using validity data from device
        $this->info('Step 2/4: Checking user validity dates...');
        $validUsers = [];
        $expiredCount = 0;

        foreach ($deviceUsers as $du) {
            $externalId = trim((string) ($du['user_id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $validEnd = trim((string) ($du['valid_end'] ?? ''));

            if ($validEnd !== '' && $validEnd !== '2037-12-31T23:59:59') {
                try {
                    $endDate = Carbon::parse($validEnd)->startOfDay();
                    $cutoff = $expireBefore->copy()->endOfDay();
                    if ($endDate->lte($cutoff)) {
                        $expiredCount++;
                        $this->line("    [EXPIRED] {$externalId} - ".($du['name'] ?? '')." (ended: {$validEnd})");

                        continue;
                    }
                } catch (\Throwable) {
                    // Invalid date, include user
                }
            }

            $validUsers[] = $du;
        }

        $this->info('  Valid users: '.count($validUsers));
        $this->info('  Expired users: '.$expiredCount);
        $this->newLine();

        if (empty($validUsers)) {
            $this->warn('No valid users to import.');

            return self::SUCCESS;
        }

        // Step 3: Create/update users in DB
        $this->info('Step 3/4: Creating employees in database...');
        $employeeCodeToUserId = User::pluck('id', 'employee_code')->toArray();

        $created = 0;
        $skipped = 0;
        $matched = [];

        foreach ($validUsers as $du) {
            $externalId = trim((string) ($du['user_id'] ?? ''));
            $name = (string) ($du['name'] ?? '');

            if ($externalId === '') {
                continue;
            }

            $userPk = $employeeCodeToUserId[$externalId] ?? null;

            if ($userPk) {
                $skipped++;
                $matched[] = [
                    'uid' => (int) ($du['uid'] ?? 0),
                    'user_id' => $externalId,
                    'name' => $name,
                    'user_pk' => $userPk,
                    'status' => 'exists',
                ];

                continue;
            }

            if ($dryRun) {
                $created++;
                $matched[] = [
                    'uid' => (int) ($du['uid'] ?? 0),
                    'user_id' => $externalId,
                    'name' => $name,
                    'user_pk' => null,
                    'status' => 'would_create',
                ];

                $this->line("  [WOULD CREATE] {$externalId} - {$name}");

                continue;
            }

            $autoName = $name !== '' ? $name : 'User '.$externalId;

            $emailBase = 'device_'.strtolower($externalId);
            $email = $emailBase.'@hrm.local';
            $attempt = 1;
            while (User::where('email', $email)->exists()) {
                $email = $emailBase.'_'.$attempt.'@hrm.local';
                $attempt++;
            }

            $user = User::create($this->applyDeviceOrgDefaults($device, [
                'employee_code' => $externalId,
                'name' => $autoName,
                'full_name_ar' => $autoName,
                'email' => $email,
                'password' => bcrypt('password'),
                'status' => 1,
                'is_active_employee' => true,
            ]));

            $created++;
            $employeeCodeToUserId[$externalId] = $user->id;

            $matched[] = [
                'uid' => (int) ($du['uid'] ?? 0),
                'user_id' => $externalId,
                'name' => $name,
                'user_pk' => $user->id,
                'status' => 'created',
            ];

            $this->line("  [CREATED] {$externalId} - {$name} (id: {$user->id})");
        }

        $this->info("  Created: {$created}, Already exists: {$skipped}");
        $this->newLine();

        // Step 4: Pull fingerprint templates
        if ($skipFingerprints) {
            $this->warn('Skipping fingerprint import (--no-fingerprints flag)');
        } else {
            $this->info('Step 4/4: Pulling fingerprint templates...');
            $fingerprintsSaved = 0;
            $fingerprintErrors = [];

            foreach ($matched as $entry) {
                if (! $entry['user_pk']) {
                    continue;
                }

                $userPk = (int) $entry['user_pk'];
                $uid = (int) $entry['uid'];

                if ($dryRun) {
                    continue;
                }

                try {
                    $templates = $adapter->getFingerprintTemplates(
                        $device->ip_address,
                        $device->port,
                        (string) $device->comm_key,
                        (int) ($device->timeout ?? 30),
                        $uid,
                    );
                    $templates = is_array($templates) ? $templates : [];
                } catch (\Throwable $e) {
                    $fingerprintErrors[] = "{$entry['user_id']}: {$e->getMessage()}";
                    $this->line("  [ERROR] {$entry['user_id']} - {$e->getMessage()}");

                    continue;
                }

                foreach ($templates as $tpl) {
                    $templateData = (string) ($tpl['template'] ?? '');
                    if ($templateData === '') {
                        continue;
                    }

                    $fingerId = (int) ($tpl['fid'] ?? 0);

                    $existing = UserFingerprint::query()
                        ->where('device_id', $device->id)
                        ->where('user_id', $userPk)
                        ->where('finger_id', $fingerId)
                        ->first();

                    $payload = [
                        'user_id' => $userPk,
                        'device_id' => $device->id,
                        'finger_id' => $fingerId,
                        'template_data' => $templateData,
                        'template_format' => 'hikvision-isapi',
                        'template_version' => 9,
                        'quality' => 0,
                        'is_master' => $fingerId === 0,
                        'captured_at' => now(),
                        'synced_at' => now(),
                    ];

                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        UserFingerprint::create($payload);
                    }

                    $fingerprintsSaved++;
                }
            }

            $this->info("  Fingerprints saved: {$fingerprintsSaved}");
            if (! empty($fingerprintErrors)) {
                $this->warn('  Fingerprint errors: '.count($fingerprintErrors));
            }
        }
        $this->newLine();

        // Update device stats
        if (! $dryRun && $created > 0) {
            $device->update([
                'user_count' => User::count(),
                'last_synced_at' => now(),
            ]);
        }

        // Summary
        $this->info('=== Import Summary ===');
        $this->info("  Device: {$device->name}");
        $this->info('  Total on device: '.count($deviceUsers));
        $this->info('  Valid users: '.count($validUsers));
        $this->info("  Expired (filtered out): {$expiredCount}");
        $this->info("  Created in DB: {$created}");
        $this->info("  Already existed: {$skipped}");
        if (! $skipFingerprints && ! $dryRun) {
            $this->info("  Fingerprints saved: {$fingerprintsSaved}");
        }
        $this->newLine();

        Log::info('Hikvision employee import completed', [
            'device_id' => $device->id,
            'total_on_device' => count($deviceUsers),
            'valid_users' => count($validUsers),
            'expired' => $expiredCount,
            'created' => $created,
            'existing' => $skipped,
        ]);

        return self::SUCCESS;
    }

    private function findDevice(): ?FingerprintDevice
    {
        $deviceId = $this->option('device');

        $query = FingerprintDevice::query()->where('status', '!=', 'deactivated');

        if ($deviceId) {
            $query->where('id', (int) $deviceId);
        } else {
            $query->whereHas('deviceType', function ($q) {
                $q->where('manufacturer', 'like', '%hikvision%')
                    ->orWhere('manufacturer', 'like', '%hik%');
            });
        }

        $device = $query->first();

        if (! $device) {
            $this->error('No Hikvision device found. Use --device=ID to specify a device.');

            return null;
        }

        return $device;
    }

    private function resolveAdapter(FingerprintDevice $device, DeviceAdapterResolver $adapterResolver): DeviceAdapterInterface
    {
        $typeName = strtolower($device->deviceType->manufacturer ?? '');

        $driver = match (true) {
            str_contains($typeName, 'zkteco'), str_contains($typeName, 'zk') => 'zkteco',
            str_contains($typeName, 'suprema') => 'suprema',
            str_contains($typeName, 'hikvision'), str_contains($typeName, 'hik') => 'hikvision',
            default => config('attendanceintegration.default_driver', 'zkteco'),
        };

        return $adapterResolver->getAdapter($driver);
    }
}
