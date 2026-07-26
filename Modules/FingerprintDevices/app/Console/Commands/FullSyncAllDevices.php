<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\DeviceFullSyncService;
use Modules\FingerprintDevices\Services\DevicePushService;

/**
 * FullSyncAllDevices — pull then push users, fingerprints, and face
 * templates across ALL active ZKTeco devices.
 *
 * This ensures every device has the same biometric data.
 */
class FullSyncAllDevices extends Command
{
    protected $signature = 'fingerprints:full-sync
                            {--device= : Restrict to a single device id}
                            {--dry-run : Show what would be done without contacting devices}
                            {--force : Clear local fingerprints before pulling (re-sync from scratch)}
                            {--skip-push : Pull only, do not push back to devices}
                            {--skip-pull : Push only, do not pull from devices}';

    protected $description = 'Full bidirectional sync: pull users+fingerprints+faces from all devices, then push to all devices';

    public function handle(
        DeviceFullSyncService $syncService,
        DevicePushService $pushService,
    ): int {
        set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $skipPush = (bool) $this->option('skip-push');
        $skipPull = (bool) $this->option('skip-pull');
        $deviceId = $this->option('device');

        $devices = FingerprintDevice::query()
            ->where('status', '!=', 'deactivated')
            ->when($deviceId, fn ($q) => $q->where('id', (int) $deviceId))
            ->with('deviceType')
            ->get();

        if ($devices->isEmpty()) {
            $this->error('No active devices found.');

            return self::FAILURE;
        }

        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║   ZKTeco Full Sync — All Devices            ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->newLine();

        $deviceNames = $devices->pluck('name', 'id')->toArray();
        $this->table(
            ['ID', 'Device', 'IP'],
            $devices->map(fn ($d) => [$d->id, $d->name, $d->ip_address])->all()
        );

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be made.');
            $this->newLine();
        }

        $startedAt = microtime(true);

        // ── Phase 1: PULL ──────────────────────────────────────
        if (! $skipPull) {
            $this->info('━━━ Phase 1: PULL from devices ━━━');
            $this->newLine();

            foreach ($devices as $device) {
                $this->line("  📥 Pulling from: <info>{$device->name}</info> ({$device->ip_address})");

                if ($dryRun) {
                    $this->line('    → Would pull: users, fingerprints, face_photos, attendance');

                    continue;
                }

                try {
                    $result = $syncService->run($device, [
                        'info' => true,
                        'users' => true,
                        'fingerprints' => true,
                        'face_photos' => true,
                        'attendance' => true,
                        'clear_local_cache' => $force,
                    ]);

                    $t = $result['totals'] ?? [];
                    $this->line('    → Users matched: <info>'.($t['users_matched'] ?? 0).'</info>');
                    $this->line('    → Fingerprints saved: <info>'.($t['fingerprints_saved'] ?? 0).'</info>');
                    $this->line('    → Face photos saved: <info>'.($t['face_photos_saved'] ?? 0).'</info>');
                    $this->line('    → Attendance saved: <info>'.($t['attendance_saved'] ?? 0).'</info>');

                    if (! empty($result['errors'])) {
                        foreach ($result['errors'] as $err) {
                            $this->error("    ⚠ {$err}");
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("    ✗ Failed: {$e->getMessage()}");
                }

                $this->newLine();
            }
        }

        // ── Phase 2: PUSH ──────────────────────────────────────
        if (! $skipPush) {
            $this->info('━━━ Phase 2: PUSH to devices ━━━');
            $this->newLine();

            foreach ($devices as $device) {
                $this->line("  📤 Pushing to: <info>{$device->name}</info> ({$device->ip_address})");

                if ($dryRun) {
                    $this->line('    → Would push: users, fingerprints, face_photos');

                    continue;
                }

                try {
                    $result = $pushService->push(
                        deviceId: $device->id,
                        options: [
                            'push_users' => true,
                            'push_fingerprints' => true,
                            'push_face_photos' => true,
                        ],
                        userId: auth()->id(),
                    );

                    $s = $result['summary'] ?? [];
                    $this->line('    → Users pushed: <info>'.($s['pushed_users'] ?? 0).'</info>');
                    $this->line('    → Fingerprints pushed: <info>'.($s['pushed_fingerprints'] ?? 0).'</info>');
                    $this->line('    → Face photos pushed: <info>'.($s['pushed_face_photos'] ?? 0).'</info>');

                    if (! empty($result['errors'])) {
                        foreach ($result['errors'] as $err) {
                            $this->error("    ⚠ {$err}");
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("    ✗ Failed: {$e->getMessage()}");
                }

                $this->newLine();
            }
        }

        $duration = round(microtime(true) - $startedAt, 2);

        $this->info('╔══════════════════════════════════════════════╗');
        $this->info("║   Done! Duration: {$duration}s");
        $this->info('╚══════════════════════════════════════════════╝');

        return self::SUCCESS;
    }
}
