<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;

/**
 * DistributeAllFaces — queue face-template commands for ALL users to ALL
 * ZKTeco push-enabled devices.
 *
 * Unlike DistributeMissingFaceSets (which only handles complete 15-index
 * enrollment sets), this command queues EVERY face template regardless of
 * set completeness. Partial sets still work on iFace firmware — the device
 * stores whatever indices it receives and uses them for recognition.
 */
class DistributeAllFaces extends Command
{
    protected $signature = 'fingerprints:distribute-all-faces
                            {--device= : Device ID or serial (default: all ZKTeco push devices)}
                            {--dry-run : Show what would be queued without queuing}
                            {--limit=0 : Max users to process (0 = all)}';

    protected $description = 'Queue face-template commands for ALL users to ALL ZKTeco devices (complete and partial sets)';

    public function handle(
        FaceTemplateDistributionService $distributionService,
        DeviceCommandService $commandService,
    ): int {
        set_time_limit(0);

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $deviceFilter = $this->option('device');

        // ── Resolve target devices ──
        $devices = FingerprintDevice::query()
            ->with('deviceType')
            ->where('is_push_enabled', true)
            ->when($deviceFilter, fn ($q) => $q->where(function ($q2) use ($deviceFilter) {
                $q2->where('id', $deviceFilter)->orWhere('serial_number', $deviceFilter);
            }))
            ->get()
            ->filter(fn (FingerprintDevice $d) => $d->getDriverName() === 'zkteco');

        if ($devices->isEmpty()) {
            $this->error('No active ZKTeco push-enabled devices found.');

            return self::FAILURE;
        }

        // ── Resolve ALL users who have face templates ──
        $userQuery = DB::table('user_fingerprints')
            ->whereBetween('finger_id', [50, 54])
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->selectRaw('DISTINCT user_id');

        if ($limit > 0) {
            $userQuery->limit($limit);
        }

        $userIds = $userQuery->pluck('user_id')->map(fn ($id) => (int) $id)->toArray();

        if (empty($userIds)) {
            $this->error('No users with face templates found.');

            return self::FAILURE;
        }

        $faceTemplateCount = DB::table('user_fingerprints')
            ->whereIn('user_id', $userIds)
            ->whereBetween('finger_id', [50, 54])
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->count();

        $this->info('╔══════════════════════════════════════════════════════╗');
        $this->info('║   Distribute ALL Face Templates — All Devices       ║');
        $this->info('╚══════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->line('  👥 Users with faces: <info>'.count($userIds).'</info> users');
        $this->line('  📊 Total face templates: <info>'.$faceTemplateCount.'</info>');
        $this->line('  📱 Target devices: <info>'.$devices->count().'</info>');
        $this->newLine();

        if ($dryRun) {
            $this->warn('[DRY RUN] No commands will be queued.');
            $this->newLine();
        }

        $totalQueued = 0;
        $totalDuplicate = 0;
        $totalSkipped = 0;
        $totalFailed = 0;
        $allErrors = [];
        $startedAt = microtime(true);

        foreach ($devices as $device) {
            $this->line("  📤 Target: <info>{$device->name}</info> (SN: {$device->serial_number})");

            if ($dryRun) {
                // Count what would be queued
                $wouldQueue = DB::table('user_fingerprints')
                    ->whereIn('user_id', $userIds)
                    ->where('template_type', 'face')
                    ->where('template_format', 'zkteco-face-push')
                    ->whereNotNull('template_data')
                    ->where('template_data', '!=', '')
                    ->where(function ($q) use ($device) {
                        $q->whereNull('device_serial')
                            ->orWhere('device_serial', '!=', $device->serial_number);
                    })
                    ->count();
                $this->line("    → Would queue: <info>{$wouldQueue}</info> face template commands");

                continue;
            }

            try {
                // CRITICAL: Pre-register users on the device BEFORE queuing
                // face templates.  iFace 880 Plus rejects BIODATA (face data)
                // for unknown PINs with -3.  Queuing user_update first ensures
                // the user exists when the face template command arrives.
                $usersWithFaces = User::query()
                    ->whereIn('id', $userIds)
                    ->whereNotNull('employee_code')
                    ->where('employee_code', '!=', '')
                    ->get(['id', 'employee_code', 'name', 'full_name_ar']);

                $userQueued = 0;
                foreach ($usersWithFaces as $u) {
                    $cmd = $commandService->queueUserUpdate(
                        $device->id,
                        (string) $u->employee_code,
                        (string) ($u->full_name_ar ?: $u->name ?: $u->employee_code),
                    );
                    if ($cmd->wasRecentlyCreated) {
                        $userQueued++;
                    }
                }
                if ($userQueued > 0) {
                    $this->line("    → Queued <info>{$userQueued}</info> user registrations (pre-flight)");
                }

                $result = $distributionService->queueForDevice($device, $userIds);
                $queued = $result['queued_face_templates'] ?? 0;
                $dupes = $result['duplicate_face_commands'] ?? 0;
                $skipped = $result['skipped_face_templates'] ?? 0;
                $failed = $result['failed_face_templates'] ?? 0;

                $totalQueued += $queued;
                $totalDuplicate += $dupes;
                $totalSkipped += $skipped;
                $totalFailed += $failed;

                $this->line("    → Queued: <info>{$queued}</info> face commands | Duplicates (skip): {$dupes} | Skipped: {$skipped} | Failed: {$failed}");

                if (! empty($result['errors'])) {
                    foreach (array_slice($result['errors'], 0, 5) as $err) {
                        $this->error("    ⚠ {$err}");
                        $allErrors[] = "{$device->name}: {$err}";
                    }
                    if (count($result['errors']) > 5) {
                        $this->warn('    ... and '.(count($result['errors']) - 5).' more errors');
                    }
                }
            } catch (\Throwable $e) {
                $this->error("    ✗ Exception: {$e->getMessage()}");
                $allErrors[] = "{$device->name}: {$e->getMessage()}";
                $totalFailed++;
            }

            $this->newLine();
        }

        $duration = round(microtime(true) - $startedAt, 2);

        $this->info('╔══════════════════════════════════════════════════════╗');
        $this->info('║   RESULTS                                            ║');
        $this->info('╠══════════════════════════════════════════════════════╣');
        $this->info("║  📤 Queued (new): {$totalQueued}");
        $this->info("║  🔄 Duplicates (already exist): {$totalDuplicate}");
        $this->info("║  ⏭  Skipped (empty PIN or data): {$totalSkipped}");
        $this->info("║  ❌ Failed: {$totalFailed}");
        $this->info("║  ⏱  Duration: {$duration}s");

        $pendingFace = DB::table('device_commands')
            ->where('command_type', 'face_template')
            ->where('status', 'pending')
            ->count();
        $this->info("║  📋 Total pending face commands now: {$pendingFace}");
        $this->info('╚══════════════════════════════════════════════════════╝');

        if ($allErrors) {
            $this->newLine();
            $this->warn(sprintf('%d error(s) total:', count($allErrors)));
            foreach (array_slice($allErrors, 0, 10) as $error) {
                $this->warn("  - {$error}");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete — nothing was queued.');
        }

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
