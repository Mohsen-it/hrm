<?php

namespace Modules\Shifts\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Shifts\Services\BiometricShiftSyncService;

/**
 * SyncBiometricShiftsCommand — daily conflict-free biometric reconciliation.
 *
 * Consumes the ScheduleResolver contract and writes "excused" summary rows for
 * intercepted employees (leave/swap). Present/late/absent rows remain owned by
 * the Attendance module's auto-calculation, so this command never clobbers
 * good data — it only bypasses absence for excused employees.
 *
 *   php artisan shifts:sync-biometric --date=2026-07-16
 *   php artisan shifts:sync-biometric --date=2026-07-16 --no-write
 */
class SyncBiometricShiftsCommand extends Command
{
    protected $signature = 'shifts:sync-biometric
        {--date= : Target date (Y-m-d). Defaults to today.}
        {--no-write : Resolve only, do not write excused rows.}';

    protected $description = 'Reconcile biometric logs against the dynamic shift engine (excused interception).';

    public function handle(BiometricShiftSyncService $sync): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $write = ! $this->option('no-write');

        $this->info("Resolving dynamic shift status for {$date->toDateString()} (write=".($write ? 'yes' : 'no').')');

        $matrix = $sync->syncDate($date, $write);

        $excused = 0;
        $absent = 0;
        $present = 0;
        $rest = 0;

        foreach ($matrix as $row) {
            match ($row['status']) {
                'excused' => $excused++,
                'absent' => $absent++,
                'present' => $present++,
                default => $rest++,
            };
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Scoped employees', count($matrix)],
                ['Excused (leave/swap)', $excused],
                ['Present', $present],
                ['Absent', $absent],
                ['Rest / other', $rest],
            ]
        );

        return self::SUCCESS;
    }
}
