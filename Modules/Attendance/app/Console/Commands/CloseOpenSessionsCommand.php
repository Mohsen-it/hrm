<?php

namespace Modules\Attendance\Console\Commands;

use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\ScheduleResolverService;

/**
 * Attendance:CloseOpenSessions — close stale open sessions automatically.
 *
 * Every duty day has a scheduled check-out derived from the rotation's time
 * table (جدول الوقت). Once that exit deadline plus the configured grace has
 * passed, an open session is almost certainly a forgotten exit punch — or a
 * stray midnight punch whose employee already left. This command closes those
 * sessions at the schedule's expected exit time (not the run time), so daily
 * summaries and reports stop counting them as missing check-outs.
 *
 * It only touches sessions whose check-in is older than the exit deadline for
 * their duty day, and never sessions still inside their window.
 */
class CloseOpenSessionsCommand extends Command
{
    protected $signature = 'attendance:close-open-sessions
                            {--date= : Only close sessions of this date (YYYY-MM-DD). Defaults to yesterday and older.}
                            {--older-than= : Close sessions whose duty day is at least this many days ago (default: 1).}
                            {--user= : Limit to one employee id.}
                            {--dry-run : Report what would be closed without changing anything.}';

    protected $description = 'Close open attendance sessions whose scheduled exit deadline has passed';

    public function __construct(
        private AttendanceSessionService $sessionService,
        private ScheduleResolverService $scheduleResolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = (int) ($this->option('older-than') ?? 1);
        $cutoffDate = Carbon::now()->subDays($days)->toDateString();

        $query = AttendanceSession::query()
            ->open()
            ->whereNotNull('check_in_at')
            ->where('attendance_date', '<=', $cutoffDate);

        if ($this->option('date')) {
            $query->where('attendance_date', $this->option('date'));
        }
        if ($this->option('user')) {
            $query->where('user_id', (int) $this->option('user'));
        }

        $sessions = $query->orderBy('attendance_date')->orderBy('check_in_at')->get();

        if ($sessions->isEmpty()) {
            $this->info('No open sessions to close.');

            return self::SUCCESS;
        }

        $closed = 0;
        $skipped = 0;
        $now = Carbon::now();

        foreach ($sessions as $session) {
            $deadline = $this->exitDeadline($session);

            if ($deadline === null) {
                // No time table and no window: cannot decide — leave it for
                // operator review.
                $skipped++;
                $this->line(sprintf('  SKIP  #%d %s (%s) — no resolvable exit deadline',
                    $session->id, $session->attendance_date, $session->user_id));

                continue;
            }

            if ($now->lt($deadline)) {
                // Still inside the scheduled exit window — not stale yet.
                $skipped++;
                $this->line(sprintf('  KEEP  #%d %s — window ends %s',
                    $session->id, $session->attendance_date, $deadline->format('Y-m-d H:i')));

                continue;
            }

            $line = sprintf('  %s  #%d %s user=%d → close at %s',
                $dryRun ? 'WOULD' : 'CLOSE',
                $session->id,
                $session->attendance_date,
                $session->user_id,
                $deadline->format('Y-m-d H:i'));

            if ($dryRun) {
                $this->line($line);
                $closed++;
            } else {
                $this->line($line);
                $this->sessionService->closeSession($session, $deadline, [
                    'source' => 'auto',
                    'notes' => 'أغلق تلقائياً: موعد الخروج المتوقع حسب جدول الوقت قد انتهى',
                ]);
                $closed++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d session(s) %s, %d kept/skipped.',
            $dryRun ? 'Dry run' : 'Done',
            $closed,
            $dryRun ? 'would be closed' : 'closed',
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * Resolve the exit deadline of one open session.
     *
     * Prefers the rotation's time table (expected check-out + grace); falls
     * back to the session's stored expected_check_out when no live schedule
     * is available.
     */
    private function exitDeadline(AttendanceSession $session): ?DateTimeImmutable
    {
        $resolved = $this->scheduleResolver->resolve($session->user_id, $session->attendance_date->toDateString());
        $expectedOut = $resolved['expected_check_out'] ?? $session->expected_check_out;

        if (! $expectedOut) {
            return null;
        }

        $isOvernight = (bool) ($resolved['is_overnight'] ?? false);
        $slot = $expectedOut;
        if (preg_match('/^\d{2}:\d{2}$/', (string) $slot) === 1) {
            $slot = $session->attendance_date->toDateString().' '.(string) $slot.':00';
        }

        $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $slot);
        if (! $deadline) {
            return null;
        }
        if ($isOvernight) {
            $deadline = $deadline->modify('+1 day');
        }

        // Grace minutes come from the rotation's TIME TABLE (att_time_schedules),
        // read directly the way DailyReportService does — the resolver's
        // out_above_margin is an absolute window edge (e.g. "15:30"), not a
        // minute count, and must not be treated as a duration.
        $assignment = app(RotationAssignmentRepository::class)
            ->getAssignmentForDate($session->user_id, $session->attendance_date->toDateString());
        $grace = (int) ($assignment?->rotation?->timeSchedule?->out_above_margin ?? 0);

        return $grace > 0 ? $deadline->modify("+{$grace} minutes") : $deadline;
    }
}
