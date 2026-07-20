<?php

namespace Modules\FingerprintDevices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Users\Models\User;

/**
 * BackfillDeviceOrgDefaults — fill empty company_id / branch_id /
 * subordination_id on imported users using the defaults configured on
 * the device they were recovered from.
 *
 * Resolution order per user:
 *   1. The first user_fingerprint row (lowest captured_at, then id).
 *   2. Manual mapping via --map=user_device_map.csv (user_id,device_id).
 *   3. Marked as orphan in the report and skipped.
 *
 * Existing values are NEVER overwritten unless --overwrite is passed.
 */
class BackfillDeviceOrgDefaults extends Command
{
    protected $signature = 'devices:backfill-orgs
                            {--device=* : Limit to one or more device IDs}
                            {--user=* : Limit to one or more user IDs}
                            {--map= : Path to a CSV file (user_id,device_id) for orphan users}
                            {--chunk=100 : Process users in chunks of this size}
                            {--dry-run : Show what would change without saving}
                            {--overwrite : Overwrite existing values (default: only fill empty fields)}
                            {--output= : Write a detailed CSV report to this path}';

    protected $description = 'Backfill company/branch/subordination on device-imported users from the device defaults';

    /** @var array<int, array<string, mixed>> */
    private array $report = [];

    /** @var resource|null */
    private $reportHandle = null;

    /** @var array<int, string> */
    private array $reportHeader = [
        'user_id', 'employee_code', 'user_name',
        'device_id', 'device_name',
        'company_before', 'company_after', 'company_changed',
        'branch_before', 'branch_after', 'branch_changed',
        'subordination_before', 'subordination_after', 'subordination_changed',
        'status', 'note',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $deviceIds = array_map('intval', (array) $this->option('device'));
        $userIds = array_map('intval', (array) $this->option('user'));
        $mapPath = $this->option('map');
        $outputPath = $this->option('output');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        if ($overwrite) {
            $this->warn('--overwrite is ON: existing company/branch/subordination values will be replaced.');
        } else {
            $this->info('Mode: only fill empty fields (use --overwrite to replace existing values).');
        }

        $this->newLine();

        $manualMap = $this->loadMap($mapPath);

        if ($outputPath) {
            $this->reportHandle = fopen($outputPath, 'w');
            if ($this->reportHandle === false) {
                $this->error("Cannot open report file for writing: {$outputPath}");

                return self::FAILURE;
            }
            fputcsv($this->reportHandle, $this->reportHeader);
        }

        $totals = [
            'scanned' => 0,
            'updated' => 0,
            'skipped_complete' => 0,
            'orphan' => 0,
            'no_device_defaults' => 0,
            'errors' => 0,
        ];

        $query = User::query()
            ->where(function (Builder $q) {
                $q->whereNull('company_id')
                    ->orWhereNull('branch_id')
                    ->orWhereNull('subordination_id');
            });

        if (! empty($userIds)) {
            $query->whereIn('id', $userIds);
        }

        $this->info('Scanning users with at least one missing org field...');
        $this->newLine();

        $query->orderBy('id')->chunkById($chunkSize, function ($users) use (
            &$totals, $deviceIds, $manualMap, $dryRun, $overwrite
        ) {
            foreach ($users as $user) {
                $totals['scanned']++;

                $device = $this->resolveDevice($user->id, $deviceIds, $manualMap);

                if (! $device) {
                    $totals['orphan']++;
                    $this->writeReportRow($user, null, null, null, 'orphan', 'no device mapping');
                    $this->line("  [ORPHAN]   user #{$user->id} ({$user->employee_code}) — no device mapping");

                    continue;
                }

                if (! $device->default_company_id && ! $device->default_branch_id && ! $device->default_subordination_id) {
                    $totals['no_device_defaults']++;
                    $this->writeReportRow($user, $device, null, null, 'no_device_defaults', 'device has no default_* set');
                    $this->line("  [NO DEFAULTS] user #{$user->id} ({$user->employee_code}) — device #{$device->id} has no default_* set");

                    continue;
                }

                $changes = $this->computeChanges($user, $device, $overwrite);

                if (empty(array_filter($changes, fn ($c) => $c['changed']))) {
                    $totals['skipped_complete']++;
                    $this->writeReportRow($user, $device, $changes, 'complete', 'nothing_to_change');
                    $this->line("  [OK]       user #{$user->id} ({$user->employee_code}) — already complete on device #{$device->id}");

                    continue;
                }

                if (! $dryRun) {
                    try {
                        DB::transaction(function () use ($user, $changes) {
                            $payload = array_filter([
                                'company_id' => $changes['company']['changed'] ? $changes['company']['after'] : null,
                                'branch_id' => $changes['branch']['changed'] ? $changes['branch']['after'] : null,
                                'subordination_id' => $changes['subordination']['changed'] ? $changes['subordination']['after'] : null,
                            ], fn ($v) => $v !== null);

                            if (! empty($payload)) {
                                User::where('id', $user->id)->update($payload);
                            }
                        });
                        $totals['updated']++;
                        $this->writeReportRow($user, $device, $changes, 'updated', '');
                        $this->info("  [UPDATED]  user #{$user->id} ({$user->employee_code}) ← device #{$device->id} ({$device->name})");
                    } catch (\Throwable $e) {
                        $totals['errors']++;
                        $this->writeReportRow($user, $device, $changes, 'error', $e->getMessage());
                        $this->error("  [ERROR]    user #{$user->id} — {$e->getMessage()}");
                    }
                } else {
                    $totals['updated']++;
                    $this->writeReportRow($user, $device, $changes, 'would_update', '(dry-run)');
                    $this->line("  [DRY]      user #{$user->id} ({$user->employee_code}) ← device #{$device->id} ({$device->name})");
                }
            }
        });

        $this->newLine();
        $this->info('=== Backfill summary ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Users scanned', $totals['scanned']],
                ['Updated (or would update on dry-run)', $totals['updated']],
                ['Skipped (already complete)', $totals['skipped_complete']],
                ['Orphans (no device mapping)', $totals['orphan']],
                ['Skipped (device has no defaults)', $totals['no_device_defaults']],
                ['Errors', $totals['errors']],
            ]
        );

        if ($this->reportHandle) {
            fclose($this->reportHandle);
            $this->info("Detailed report written to: {$outputPath}");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve which device the user was recovered from.
     *
     * @param  array<int, int>  $deviceFilter
     * @param  array<int, int>  $manualMap
     */
    private function resolveDevice(int $userId, array $deviceFilter, array $manualMap): ?FingerprintDevice
    {
        if (array_key_exists($userId, $manualMap)) {
            $deviceId = $manualMap[$userId];

            return FingerprintDevice::find($deviceId);
        }

        $fp = UserFingerprint::query()
            ->where('user_id', $userId)
            ->orderByRaw('COALESCE(captured_at, synced_at, created_at) ASC')
            ->orderBy('id', 'asc')
            ->first();

        if (! $fp || ! $fp->device_id) {
            return null;
        }

        if (! empty($deviceFilter) && ! in_array((int) $fp->device_id, $deviceFilter, true)) {
            return null;
        }

        return FingerprintDevice::find($fp->device_id);
    }

    /**
     * @return array{company: array{before:?int,after:?int,changed:bool}, branch: array{before:?int,after:?int,changed:bool}, subordination: array{before:?int,after:?int,changed:bool}}
     */
    private function computeChanges(User $user, FingerprintDevice $device, bool $overwrite): array
    {
        $make = function (?int $before, ?int $default) use ($overwrite): array {
            if ($default === null) {
                return ['before' => $before, 'after' => $before, 'changed' => false];
            }

            if ($before !== null && ! $overwrite) {
                return ['before' => $before, 'after' => $before, 'changed' => false];
            }

            return [
                'before' => $before,
                'after' => $default,
                'changed' => $before !== $default,
            ];
        };

        return [
            'company' => $make($user->company_id, $device->default_company_id),
            'branch' => $make($user->branch_id, $device->default_branch_id),
            'subordination' => $make($user->subordination_id, $device->default_subordination_id),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function loadMap(?string $path): array
    {
        if (! $path) {
            return [];
        }

        if (! is_readable($path)) {
            $this->error("Map file not readable: {$path}");

            return [];
        }

        $map = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Cannot open map file: {$path}");

            return [];
        }

        $header = fgetcsv($handle);
        $userCol = is_array($header) ? array_search('user_id', $header, true) : false;
        $deviceCol = is_array($header) ? array_search('device_id', $header, true) : false;

        if ($userCol === false || $deviceCol === false) {
            $this->error('Map CSV must have a header with user_id,device_id columns.');

            fclose($handle);

            return [];
        }

        while (($row = fgetcsv($handle)) !== false) {
            $userId = (int) ($row[$userCol] ?? 0);
            $deviceId = (int) ($row[$deviceCol] ?? 0);
            if ($userId > 0 && $deviceId > 0) {
                $map[$userId] = $deviceId;
            }
        }

        fclose($handle);
        $this->info('Loaded '.count($map)." manual mappings from {$path}");

        return $map;
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    private function writeReportRow(User $user, ?FingerprintDevice $device, ?array $changes, string $status, string $note): void
    {
        if (! $this->reportHandle) {
            return;
        }

        $company = $changes['company'] ?? ['before' => $user->company_id, 'after' => $user->company_id, 'changed' => false];
        $branch = $changes['branch'] ?? ['before' => $user->branch_id, 'after' => $user->branch_id, 'changed' => false];
        $sub = $changes['subordination'] ?? ['before' => $user->subordination_id, 'after' => $user->subordination_id, 'changed' => false];

        fputcsv($this->reportHandle, [
            $user->id,
            $user->employee_code,
            $user->full_name_ar ?: $user->name,
            $device?->id,
            $device?->name,
            $company['before'],
            $company['after'],
            $company['changed'] ? '1' : '0',
            $branch['before'],
            $branch['after'],
            $branch['changed'] ? '1' : '0',
            $sub['before'],
            $sub['after'],
            $sub['changed'] ? '1' : '0',
            $status,
            $note,
        ]);
    }
}
