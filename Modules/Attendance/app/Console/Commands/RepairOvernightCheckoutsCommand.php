<?php

namespace Modules\Attendance\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\RotationEngine;

/**
 * Attendance:RepairOvernightCheckouts — fix last-block-day sessions that were
 * closed by the same-day evening punch instead of the departure punch.
 *
 * Background: for overnight duty blocks (1-3 single day, day 3 of 3-9, day 7
 * of 7-21) the duty ends on the departure morning. A same-day evening punch
 * is presence proof only (extra) and must never close the session. Before the
 * classifier fix, evening punches inside the legacy same-day exit window
 * closed the session, leaving the real departure punch dangling as a
 * checkout-only row on the rest day.
 *
 * For every last-block-day session closed the same evening while a departure
 * punch exists the next morning, this command:
 *  - moves the checkout to the departure punch (metrics recomputed),
 *  - deletes the dangling checkout-only row(s),
 *  - reclassifies the evening punch(es) to extra.
 */
class RepairOvernightCheckoutsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:repair-overnight-checkouts
                            {--from= : Start date (YYYY-MM-DD). Defaults to 10 days ago.}
                            {--to= : End date (YYYY-MM-DD). Defaults to today.}
                            {--dry-run : List the fixes without applying them.}';

    /**
     * The console command description.
     */
    protected $description = 'Move last-duty-day checkouts from evening punches to departure-morning punches';

    public function __construct(
        private RotationAssignmentRepository $assignmentRepository,
        private RotationEngine $rotationEngine,
        private AttendanceSessionService $sessionService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $from = (string) ($this->option('from') ?? now()->subDays(10)->toDateString());
        $to = (string) ($this->option('to') ?? now()->toDateString());
        $dryRun = (bool) $this->option('dry-run');

        $fixed = 0;
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $fixed += $this->repairDay($cursor->toDateString(), $dryRun);
            $cursor->addDay();
        }

        $this->info(($dryRun ? '[DRY-RUN] Would repair ' : 'Repaired ').$fixed.' session(s).');

        return self::SUCCESS;
    }

    private function repairDay(string $date, bool $dryRun): int
    {
        $fixed = 0;
        $departureDate = Carbon::parse($date)->addDay()->toDateString();

        $assignments = $this->assignmentRepository->getAssignmentsForDate($date);

        foreach ($assignments as $assignment) {
            $rotation = $assignment->rotation;
            $group = $assignment->rotationGroup;
            if (! $rotation || ! $group) {
                continue;
            }

            $times = $this->rotationEngine->resolveTimes($assignment);
            if (! ($times['is_overnight'] ?? false)) {
                continue;
            }

            if (! $this->rotationEngine->isLastWorkDayOfBlock($rotation, $group, $date)) {
                continue;
            }

            $fixed += $this->repairEmployeeDay(
                (int) $assignment->employee_id, $date, $departureDate, $times, $dryRun,
            );
        }

        return $fixed;
    }

    /**
     * @param  array<string, mixed>  $times
     */
    private function repairEmployeeDay(int $employeeId, string $date, string $departureDate, array $times, bool $dryRun): int
    {
        $fixed = 0;

        // Sessions of the duty day closed the same evening (wrong checkout).
        $sessions = AttendanceSession::forUser($employeeId)->onDate($date)
            ->whereNotNull('check_in_at')
            ->whereNotNull('check_out_at')
            ->get()
            ->filter(fn (AttendanceSession $s) => $s->check_out_at->toDateString() === $date);

        if ($sessions->isEmpty()) {
            return 0;
        }

        $departurePunches = $this->departurePunches($employeeId, $departureDate, $times);
        if ($departurePunches->isEmpty()) {
            return 0;
        }

        foreach ($sessions as $session) {
            // The departure punch that belongs to this duty: the first punch
            // after the check-in inside the departure-morning window.
            $departure = $departurePunches
                ->first(fn (RawAttendanceLog $log) => Carbon::parse($log->punch_time)->gt($session->check_in_at));

            if (! $departure) {
                continue;
            }

            $departureAt = Carbon::parse($departure->punch_time);
            $this->line("  user#{$employeeId} {$date}: session#{$session->id} checkout {$session->check_out_at->format('H:i')} → departure {$departureAt->format('m-d H:i')}");

            if ($dryRun) {
                $fixed++;

                continue;
            }

            // 1. Drop the dangling checkout-only row created by the departure punch.
            AttendanceSession::forUser($employeeId)->onDate($departureDate)
                ->whereNull('check_in_at')
                ->where('check_out_at', $departure->punch_time)
                ->delete();

            // 2. Drop dangling checkout-only rows of extra evening punches on the duty day.
            $window = $this->sameDayExitBounds($date, $times);
            if ($window !== null) {
                $eveningLogs = RawAttendanceLog::query()
                    ->where('user_id', $employeeId)
                    ->whereBetween('punch_time', $window)
                    ->where('punch_type', 'check_out')
                    ->get();
                foreach ($eveningLogs as $log) {
                    AttendanceSession::forUser($employeeId)->onDate($date)
                        ->whereNull('check_in_at')
                        ->where('check_out_at', $log->punch_time)
                        ->delete();
                    $log->forceFill(['punch_type' => 'extra'])->save();
                }
            }

            // 3. Move the checkout to the departure punch (metrics recomputed).
            $this->sessionService->closeSession($session->fresh(), $departureAt);
            if ($departure->punch_type !== 'check_out') {
                $departure->forceFill(['punch_type' => 'check_out'])->save();
            }
            $fixed++;
        }

        return $fixed;
    }

    /**
     * Raw punches on the departure morning inside the next-day exit window.
     *
     * @param  array<string, mixed>  $times
     * @return Collection<int, RawAttendanceLog>
     */
    private function departurePunches(int $employeeId, string $departureDate, array $times): Collection
    {
        $checkOut = $times['check_out'] ?? null;
        $ahead = $times['next_day_out_ahead_margin'] ?? null;
        $above = $times['next_day_out_above_margin'] ?? null;

        if (! is_string($checkOut) || ! is_string($ahead) || ! is_string($above)
            || preg_match('/^\d{2}:\d{2}/', $checkOut) !== 1
            || preg_match('/^\d{2}:\d{2}/', $ahead) !== 1
            || preg_match('/^\d{2}:\d{2}/', $above) !== 1) {
            return collect();
        }

        $start = Carbon::parse($departureDate.' '.substr($ahead, 0, 5));
        $end = Carbon::parse($departureDate.' '.substr($above, 0, 5));
        if ($end->lt($start)) {
            $end = $end->addDay();
        }

        $bounds = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];

        return RawAttendanceLog::query()
            ->where('user_id', $employeeId)
            ->whereBetween('punch_time', $bounds)
            ->orderBy('punch_time')
            ->get();
    }

    /**
     * Bounds of the rotation's same-day exit window for a duty date.
     *
     * Raw punches are stored as naive local wall time, so the local window
     * is used directly with no timezone conversion.
     *
     * @param  array<string, mixed>  $times
     * @return array{0: string, 1: string}|null
     */
    private function sameDayExitBounds(string $date, array $times): ?array
    {
        $ahead = $times['out_ahead_margin'] ?? null;
        $above = $times['out_above_margin'] ?? null;

        if (! is_string($ahead) || ! is_string($above)
            || preg_match('/^\d{2}:\d{2}/', $ahead) !== 1
            || preg_match('/^\d{2}:\d{2}/', $above) !== 1) {
            return null;
        }

        $start = Carbon::parse($date.' '.substr($ahead, 0, 5));
        $end = Carbon::parse($date.' '.substr($above, 0, 5));
        if ($end->lt($start)) {
            $end = $end->addDay();
        }

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }
}
