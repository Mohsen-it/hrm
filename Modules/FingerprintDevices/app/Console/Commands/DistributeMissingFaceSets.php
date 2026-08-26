<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\BridgeBiometricSyncService;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;

class DistributeMissingFaceSets extends Command
{
    protected $signature = 'fingerprints:distribute-missing-faces
                            {--device= : Device ID or serial number (default: all ZKTeco push devices)}
                            {--dry-run : Show what would be queued without actually queuing}';

    protected $description = 'Push complete stored face-template sets to ZKTeco devices missing them';

    private const COMPLETE_SET_SIZE = 15;

    public function handle(
        DeviceCommandService $commandService,
        BridgeBiometricSyncService $bridgeSync,
        FaceTemplateDistributionService $distributionService,
    ): int {
        $devices = $this->targetDevices();

        if ($devices->isEmpty()) {
            $this->error('No ZKTeco push-enabled devices found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // Group every stored face record into (user, source device, set) and
        // keep only the groups that form a full 15-index enrollment.
        $completeGroups = $this->completeSetGroups();

        $this->info(sprintf('Complete enrollment sets available for %d user/device pairs.', $completeGroups->count()));

        $totalUsers = 0;
        $totalFaceCommands = 0;
        $errors = [];

        foreach ($devices as $device) {
            $usersOnDevice = $this->usersWithCompleteSetOn($device->serial_number);
            $missing = $completeGroups
                ->filter(fn (Collection $group) => ! $usersOnDevice->contains($group->first()->user_id))
                ->map(fn (Collection $group) => $group->first()->user_id)
                ->unique()
                ->values();

            if ($missing->isEmpty()) {
                $this->line(sprintf('  %s: up to date (%d users with complete sets).', $device->name, $usersOnDevice->count()));

                continue;
            }

            $this->line(sprintf('  %s: %d users missing a complete face set.', $device->name, $missing->count()));

            foreach ($missing as $userId) {
                $user = User::find($userId);
                $pin = (string) ($user?->employee_code ?? '');

                if (! $user) {
                    $errors[] = "User {$userId}: not found or soft-deleted; skipped";

                    continue;
                }

                if ($pin === '') {
                    $errors[] = "User {$userId} ({$user->name}): no employee_code; skipped";

                    continue;
                }

                $source = $this->newestCompleteGroupForUser($completeGroups, $userId);

                if (! $source) {
                    $errors[] = "User {$userId} ({$pin}): no complete source set found; skipped";

                    continue;
                }

                $sourceSet = $source->first();

                if ($dryRun) {
                    $this->line(sprintf('    [DRY RUN] would queue user_update + face set for %s (%s) <- %s', $pin, $user->name, $sourceSet->device_serial));

                    continue;
                }

                try {
                    // Ensure the employee exists on the target via the bridge
                    // (push USERINFO corrupts Arabic names on this firmware).
                    $bridgeSync->syncUser(
                        $device,
                        $pin,
                        (string) ($user->full_name_ar ?: $user->name ?: $pin),
                    );

                    $result = $distributionService->queueSetForDevice(
                        $device,
                        $userId,
                        (string) $sourceSet->device_serial,
                        (string) $sourceSet->face_template_set_id,
                    );

                    $totalUsers++;
                    $totalFaceCommands += $result['queued_face_templates'];

                    $this->line(sprintf(
                        '    queued %s (%s) <- %s: %d face templates',
                        $pin,
                        $user->name,
                        $sourceSet->device_serial,
                        $result['queued_face_templates'],
                    ));
                } catch (\Throwable $exception) {
                    $errors[] = "User {$userId} ({$pin}): {$exception->getMessage()}";
                }
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete — nothing was queued.');
        } else {
            $this->newLine();
            $this->info(sprintf('Queued %d user updates and %d face-template commands.', $totalUsers, $totalFaceCommands));
        }

        if ($errors) {
            $this->newLine();
            $this->warn(sprintf('%d issue(s):', count($errors)));

            foreach ($errors as $error) {
                $this->warn('  - '.$error);
            }

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, FingerprintDevice> */
    private function targetDevices(): Collection
    {
        $query = FingerprintDevice::query()->where('is_push_enabled', true);

        if ($device = $this->option('device')) {
            $query->where(function ($q) use ($device): void {
                $q->where('id', $device)->orWhere('serial_number', $device);
            });
        }

        return $query->get()->filter(fn (FingerprintDevice $device) => $device->getDriverName() === 'zkteco');
    }

    /**
     * Group stored face records into (user, source device, set) enrollments and
     * keep only groups containing all 15 template indices exactly once.
     *
     * @return Collection<int, Collection<int, object>>
     */
    private function completeSetGroups(): Collection
    {
        return DB::table('user_fingerprints')
            ->where('template_type', 'face')
            ->where('template_format', 'zkteco-face-push')
            ->whereNotNull('face_template_set_id')
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->get(['user_id', 'device_serial', 'face_template_set_id', 'template_index', 'updated_at'])
            ->groupBy(fn ($row) => $row->user_id.'|'.$row->device_serial.'|'.$row->face_template_set_id)
            ->filter(function (Collection $group): bool {
                // Keep only null values out: template_index 0 is a valid index.
                $indices = $group->pluck('template_index')->filter(fn ($index) => $index !== null)->unique();

                return $indices->count() === self::COMPLETE_SET_SIZE;
            });
    }

    /** @return Collection<int, int> */
    private function usersWithCompleteSetOn(string $serial): Collection
    {
        return DB::table('user_fingerprints')
            ->where('template_type', 'face')
            ->where('template_format', 'zkteco-face-push')
            ->where('device_serial', $serial)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(DISTINCT template_index) = ?', [self::COMPLETE_SET_SIZE])
            ->pluck('user_id');
    }

    /**
     * @param  Collection<int, Collection<int, object>>  $groups
     * @return Collection<int, object>|null
     */
    private function newestCompleteGroupForUser(Collection $groups, int $userId): ?Collection
    {
        return $groups
            ->filter(fn (Collection $group) => (int) $group->first()->user_id === $userId)
            ->sortByDesc(fn (Collection $group) => $group->max('updated_at'))
            ->first();
    }
}
