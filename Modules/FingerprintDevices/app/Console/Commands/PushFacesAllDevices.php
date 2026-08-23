<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\DevicePushService;

/**
 * PushFacesAllDevices — push ALL face templates and face photos
 * to every active ZKTeco device in one go.
 *
 * This is a lightweight alternative to fingerprints:full-sync that
 * only handles the face push step (no pull, no users, no fingerprints).
 */
class PushFacesAllDevices extends Command
{
    protected $signature = 'fingerprints:push-faces
                            {--device= : Restrict to a single device id or serial}
                            {--dry-run : Show what would be done without contacting devices}';

    protected $description = 'Push all face templates and face photos to every active ZKTeco device';

    public function handle(DevicePushService $pushService): int
    {
        set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $deviceId = $this->option('device');

        $devices = FingerprintDevice::query()
            ->where('is_push_enabled', true)
            ->where('status', '!=', 'deactivated')
            ->when($deviceId, fn ($q) => $q->where(function ($q2) use ($deviceId) {
                $q2->where('id', $deviceId)->orWhere('serial_number', $deviceId);
            }))
            ->with('deviceType')
            ->get()
            ->filter(fn (FingerprintDevice $d) => $d->getDriverName() === 'zkteco');

        if ($devices->isEmpty()) {
            $this->error('No active ZKTeco push-enabled devices found.');

            return self::FAILURE;
        }

        // Count face data available
        $faceTemplateCount = DB::table('user_fingerprints')
            ->whereBetween('finger_id', [50, 54])
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->count();

        $facePhotoCount = DB::table('users')
            ->whereNotNull('face_photo_path')
            ->where('face_photo_path', '!=', '')
            ->count();

        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║   ZKTeco Face Push — All Devices            ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->newLine();

        $this->line('  📊 Available face data:');
        $this->line("     Face templates (biodata): <info>{$faceTemplateCount}</info>");
        $this->line("     Face photos (JPEG):       <info>{$facePhotoCount}</info>");
        $this->newLine();

        $this->table(
            ['ID', 'Device', 'IP', 'Serial'],
            $devices->map(fn ($d) => [$d->id, $d->name, $d->ip_address, $d->serial_number])->all()
        );

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be made.');
            $this->newLine();
        }

        $startedAt = microtime(true);
        $totalPushed = ['face_templates' => 0, 'face_photos' => 0];
        $totalFailed = ['face_templates' => 0, 'face_photos' => 0];
        $totalSkipped = ['face_templates' => 0, 'face_photos' => 0];
        $allErrors = [];

        foreach ($devices as $device) {
            $this->line("  📤 Pushing to: <info>{$device->name}</info> ({$device->ip_address})");

            if ($dryRun) {
                $this->line('    → Would push: face templates + face photos');

                continue;
            }

            try {
                $result = $pushService->push(
                    deviceId: $device->id,
                    options: [
                        'push_users' => false,
                        'push_fingerprints' => false,
                        'push_face_photos' => true,
                    ],
                    userId: auth()->id(),
                );

                $s = $result['summary'] ?? [];

                $pushedT = (int) ($s['pushed_face_templates'] ?? 0);
                $failedT = (int) ($s['failed_face_templates'] ?? 0);
                $skippedT = (int) ($s['skipped_face_templates'] ?? 0);
                $pushedP = (int) ($s['pushed_face_photos'] ?? 0);
                $failedP = (int) ($s['failed_face_photos'] ?? 0);
                $skippedP = (int) ($s['skipped_face_photos'] ?? 0);

                $totalPushed['face_templates'] += $pushedT;
                $totalPushed['face_photos'] += $pushedP;
                $totalFailed['face_templates'] += $failedT;
                $totalFailed['face_photos'] += $failedP;
                $totalSkipped['face_templates'] += $skippedT;
                $totalSkipped['face_photos'] += $skippedP;

                $this->line("    → Face templates: <info>{$pushedT}</info> pushed, {$failedT} failed, {$skippedT} skipped");
                $this->line("    → Face photos:    <info>{$pushedP}</info> pushed, {$failedP} failed, {$skippedP} skipped");

                if (! empty($result['errors'])) {
                    foreach ($result['errors'] as $err) {
                        $this->error("    ⚠ {$err}");
                        $allErrors[] = "{$device->name}: {$err}";
                    }
                }

                $status = $result['status'] ?? 'unknown';
                $statusIcon = $status === 'completed' ? '✅' : ($status === 'partial' ? '⚠️' : '❌');
                $this->line("    → Status: {$statusIcon} {$status}");
            } catch (\Throwable $e) {
                $this->error("    ✗ Failed: {$e->getMessage()}");
                $allErrors[] = "{$device->name}: {$e->getMessage()}";
            }

            $this->newLine();
        }

        $duration = round(microtime(true) - $startedAt, 2);

        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║   RESULTS                                   ║');
        $this->info('╠══════════════════════════════════════════════╣');
        $this->info("║  Face templates pushed: {$totalPushed['face_templates']}");
        $this->info("║  Face templates failed: {$totalFailed['face_templates']}");
        $this->info("║  Face photos pushed:    {$totalPushed['face_photos']}");
        $this->info("║  Face photos failed:    {$totalFailed['face_photos']}");
        $this->info("║  Duration: {$duration}s");
        $this->info('╚══════════════════════════════════════════════╝');

        if ($allErrors) {
            $this->newLine();
            $this->warn(sprintf('%d error(s):', count($allErrors)));
            foreach ($allErrors as $error) {
                $this->warn("  - {$error}");
            }
        }

        return ($totalFailed['face_templates'] + $totalFailed['face_photos']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
