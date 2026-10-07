<?php

namespace Modules\Attendance\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Attendance\Services\DailyAttendanceSummaryService;
use Modules\Attendance\Services\PunchWindowService;
use Modules\Shifts\Services\ScheduleResolverService;

/**
 * Attendance:RepairMissedCheckouts — rebind real exit punches the punch
 * classifier discarded.
 *
 * Background: the rotation's legacy absolute exit-window times (att_rotations
 * .out_ahead_margin / .out_above_margin) used to override the linked time
 * schedule unconditionally. A day schedule's own margins then had no say: an
 * 08:00-15:00 schedule with out_ahead_margin = 59 opens its exit window at
 * 14:01, but a leftover legacy "14:50" opened it at 14:50 instead. Every exit
 * punch in that dead zone (14:01-14:49) was classified "extra" — kept in the
 * device log for audit, never bound to a session — so the session stayed open
 * forever and every report published "لم يسجل خروج" about employees who had
 * demonstrably badged out.
 *
 * RotationEngine now unions both sources for day duties, so those same
 * punches classify as check_out. This command walks the ALREADY PROCESSED
 * device logs, and for every punch that now classifies as a real exit while
 * its owner's session is still open, closes the session with that punch —
 * exactly what the pipeline would have written had the window been correct
 * from the start. The stored punch_type is corrected to check_out too, so the
 * log no longer contradicts itself.
 *
 * Conservative by construction: a punch is only ever used as an exit when the
 * CURRENT schedule says it is one, the employee really did check in before
 * it, and the session is still open. Accidental / mid-duty punches that stay
 * outside every window are left untouched, and a duty with no punch at all is
 * never invented here (that is attendance:repair-fabricated-sessions' job).
 *
 * Affected daily summaries are recalculated so no report keeps the old
 * numbers.
 *
 * ALWAYS run with --dry-run first: it prints the full before/after of every
 * row it would touch and changes nothing.
 */
class RepairMissedCheckoutsCommand extends Command
{
    protected $signature = 'attendance:repair-missed-checkouts
                            {--from= : Start date (YYYY-MM-DD). Defaults to the earliest device punch.}
                            {--to= : End date (YYYY-MM-DD). Defaults to today.}
                            {--user= : Limit to one employee id.}
                            {--apply : Actually write the repairs. Without it the command is a dry run.}
                            {--skip-summaries : Do not recalculate daily summaries.}';

    protected $description = 'Rebind exit punches discarded by a too-narrow exit window onto their open session';

    /** The window scanned for open sessions either side of the discarded punches. */
    private const DUTY_WINDOW_HOURS = 48;

    /** Rows closed, by outcome. */
    private array $tally = ['rebind' => 0, 'skipped' => 0];

    /** Session ids already closed in this run, so one punch closes its duty once. */
    private array $closedSessions = [];

    /** Memoised overnight-duty flags, keyed by employee and duty date. */
    private array $overnightCache = [];

    public function __construct(
        private AttendanceSessionService $sessionService,
        private DailyAttendanceSummaryService $summaryService,
        private PunchWindowService $punchWindowService,
        private ScheduleResolverService $scheduleResolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $to = (string) ($this->option('to') ?? now()->toDateString());

        $query = RawAttendanceLog::query()
            // Only punches the classifier rejected as 'extra': a real exit the
            // window threw away. check_in/check_out rows are already bound and
            // 'unknown' rows carry no window evidence at all.
            ->where('punch_type', 'extra')
            ->where('processed', 1)
            ->whereNotNull('user_id')
            ->whereNull('deleted_at');

        $from = $this->option('from');
        if (! $from) {
            $from = substr((string) (clone $query)->min('punch_time'), 0, 10) ?: '2000-01-01';
        }

        $query->whereBetween('punch_time', [$from.' 00:00:00', $to.' 23:59:59']);

        if ($userId = $this->option('user')) {
            $query->where('user_id', (int) $userId);
        }

        $punches = $query->orderBy('punch_time')->get(['id', 'user_id', 'punch_time']);

        if ($punches->isEmpty()) {
            $this->info("No discarded exit punches found between {$from} and {$to}.");

            return self::SUCCESS;
        }

        $openSessions = $this->openSessionsByUser($punches);

        $this->line(sprintf(
            '%s: %d discarded punch(es) in %s..%s — scanning for real exits against the CURRENT windows.',
            $apply ? 'Applying' : 'DRY RUN',
            $punches->count(),
            $from,
            $to,
        ));
        $this->newLine();

        $summaryDates = [];

        foreach ($punches as $punch) {
            $at = Carbon::parse((string) $punch->punch_time);
            // The same set the pipeline closes on a real check-out: every open
            // session this employee started before the punch, inside one duty
            // window. A punch can never close a session it precedes.
            $targets = $this->targetsFor($openSessions, (int) $punch->user_id, $at);

            if ($targets->isEmpty()) {
                $this->tally['skipped']++;

                continue;
            }

            if (($this->punchWindowService->classify((int) $punch->user_id, $at, true)['type'] ?? null) !== 'check_out') {
                // Still outside every window under the corrected schedule: a
                // genuine extra punch (accidental, mid-duty). Leave it alone.
                $this->tally['skipped']++;

                continue;
            }

            $this->tally['rebind']++;
            $this->renderRow($punch, $targets, $at, $apply);

            if (! $apply) {
                foreach ($targets as $target) {
                    $this->closedSessions[(int) $target->id] = true;
                }

                continue;
            }

            // The stored type contradicted the device log for weeks; make it
            // tell the truth again so the audit trail matches the session.
            RawAttendanceLog::query()->whereKey($punch->id)->update(['punch_type' => 'check_out']);

            foreach ($targets as $target) {
                $this->sessionService->closeSession($target, $at, [
                    'source' => 'repair',
                    'notes' => 'صُحِّح: بصمة خروج داخل نطاق جدول الوقت صُنِّفت كبصمة إضافية',
                ]);
                $summaryDates[$target->user_id.':'.$target->attendance_date?->toDateString()] = true;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: rebound=%d, left untouched=%d (of %d discarded punches)',
            $apply ? 'Done' : 'Dry run',
            $this->tally['rebind'],
            $this->tally['skipped'],
            $punches->count(),
        ));

        if (! $apply) {
            $this->line('Nothing was written. Re-run with --apply to perform these repairs.');

            return self::SUCCESS;
        }

        if ($this->option('skip-summaries')) {
            $this->line('Daily summaries left untouched (--skip-summaries).');

            return self::SUCCESS;
        }

        $recalculated = 0;
        foreach (array_keys($summaryDates) as $key) {
            [$userId, $date] = explode(':', $key);
            if ($date === '') {
                continue;
            }
            $this->summaryService->recalculateForUserAndDate((int) $userId, $date);
            $recalculated++;
        }
        $this->info("Recalculated {$recalculated} daily summary row(s).");

        return self::SUCCESS;
    }

    /**
     * Every still-open session of the scanned employees, grouped by employee.
     *
     * @param  Collection<int, RawAttendanceLog>  $punches
     * @return Collection<int, Collection<int, AttendanceSession>>
     */
    private function openSessionsByUser(Collection $punches): Collection
    {
        $userIds = $punches->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->all();
        if ($userIds === []) {
            return collect();
        }

        $bounds = [
            Carbon::parse((string) $punches->min('punch_time'))->subHours(self::DUTY_WINDOW_HOURS),
            Carbon::parse((string) $punches->max('punch_time')),
        ];

        return AttendanceSession::query()
            ->whereIn('user_id', $userIds)
            ->whereNull('check_out_at')
            ->whereNotNull('check_in_at')
            ->whereBetween('check_in_at', $bounds)
            ->orderBy('check_in_at')
            ->get()
            ->groupBy('user_id');
    }

    /**
     * The open sessions this punch would close, oldest first.
     *
     * One real exit closes every session the employee opened for the SAME
     * duty: the duty day's own sessions (a repeated visit opens a second one)
     * plus the previous calendar day's session when this punch is an overnight
     * duty's departure-morning exit.
     *
     * Two boundaries this deliberately does NOT copy from the pipeline:
     *  - its 48-hour net would close a session from two days earlier and stamp
     *    a 40-hour duty on a day that ended long ago;
     *  - a next-day punch is only the previous day's exit for an OVERNIGHT
     *    duty, or for the post-midnight late exit the punch classifier and
     *    the missing-checkout report both already accept. A DAY duty must
     *    otherwise exit on its own day, so an ordinary next-day checkout
     *    proves the exit was forgotten — closing it here would erase the very
     *    violation the daily report exists to publish.
     *
     * Anything older, or a previous-day day duty exited by a normal-hour
     * punch, is left to the stale-session cleanup and the missing-checkout
     * report, which own them.
     *
     * @param  Collection<int, Collection<int, AttendanceSession>>  $openSessions
     * @return Collection<int, AttendanceSession>
     */
    private function targetsFor(Collection $openSessions, int $userId, Carbon $at): Collection
    {
        return $openSessions->get($userId, collect())
            ->reject(fn (AttendanceSession $s) => isset($this->closedSessions[(int) $s->id]))
            ->filter(function (AttendanceSession $s) use ($userId, $at): bool {
                if ($s->check_in_at === null || $s->check_in_at->gte($at)) {
                    return false;
                }

                $dutyDay = $s->attendance_date?->toDateString() ?? $s->check_in_at->toDateString();

                if ($dutyDay === $at->toDateString()) {
                    return true;
                }

                if ($dutyDay !== $at->copy()->subDay()->toDateString()) {
                    return false;
                }

                // Post-midnight (00:00-04:59) is the late-night exit the
                // classifier attaches to the previous duty day, whatever the
                // rotation. Same exemption the missing-checkout report applies.
                return (int) $at->format('H') < 5 || $this->isOvernightDuty($userId, $dutyDay);
            })
            ->values();
    }

    /**
     * Whether the employee's duty on that day is an overnight (multi-day)
     * schedule — the only case where the exit belongs to the next morning.
     */
    private function isOvernightDuty(int $userId, string $dutyDay): bool
    {
        $key = $userId.'|'.$dutyDay;

        if (! array_key_exists($key, $this->overnightCache)) {
            $this->overnightCache[$key] = (bool) ($this->scheduleResolver->resolve($userId, $dutyDay)['is_overnight'] ?? false);
        }

        return $this->overnightCache[$key];
    }

    /**
     * Print the before/after of one punch so the operator can audit it.
     *
     * @param  Collection<int, AttendanceSession>  $targets
     */
    private function renderRow(RawAttendanceLog $punch, Collection $targets, Carbon $at, bool $apply): void
    {
        $first = $targets->first();
        $name = $first?->session?->name ?? $first?->user?->name ?? ('user#'.$punch->user_id);
        $sessionIds = $targets->map(fn (AttendanceSession $s) => '#'.$s->id)->implode(',');
        $checkIns = $targets->map(fn (AttendanceSession $s) => $s->check_in_at?->format('H:i'))->implode(',');

        $this->line(sprintf(
            '  raw#%-6d %-26s punch %s   sessions %s (in %s)   ->   check_out %s%s',
            $punch->id,
            $this->truncate((string) $name),
            $at->format('Y-m-d H:i'),
            $sessionIds,
            $checkIns,
            $at->format('Y-m-d H:i'),
            $apply ? '   [written]' : '',
        ));
    }

    private function truncate(string $value): string
    {
        $value = trim($value);

        return mb_strlen($value) > 26 ? mb_substr($value, 0, 25).'…' : $value;
    }
}
