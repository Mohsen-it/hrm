<?php

namespace Modules\UserActivity\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Settings\Services\SettingService;
use Modules\UserActivity\Repositories\UserActivityRepository;
use Modules\Users\Models\User;

/**
 * UserActivityService — records user actions and builds the monitoring
 * summaries shown on the activity-log pages.
 *
 * "Active working time" is computed from the recorded request timestamps:
 * consecutive actions separated by less than IDLE_GAP_MINUTES belong to the
 * same working session; a gap of that length or longer closes the session.
 * The idle period itself is never counted, so a short break (e.g. leaving
 * the computer for two minutes) must not inflate the total. A single
 * session is capped at MAX_SESSION_MINUTES so a forgotten open tab never
 * inflates the totals.
 */
class UserActivityService
{
    /**
     * Default idle gap (minutes). A gap of this length or longer between two
     * recorded actions closes the working session, so idle time is not
     * counted. The value can be overridden through the settings store (see
     * {@see self::idleGapMinutes()}) or via
     * `config('useractivity.idle_gap_minutes')`.
     */
    public const IDLE_GAP_MINUTES = 2;

    public const MAX_SESSION_MINUTES = 16 * 60;

    /**
     * Settings key holding the admin-configurable idle gap.
     */
    private const IDLE_GAP_SETTING_KEY = 'useractivity.idle_gap_minutes';

    /**
     * Actions that represent a real change inside the system, as opposed to
     * mere browsing (view / open_create / open_edit). Used to split the
     * totals into "real operations" vs "views" so a report can tell how
     * many operations were actually performed.
     *
     * @var array<int, string>
     */
    private const MUTATION_ACTIONS = [
        'create', 'edit', 'delete', 'approve', 'reject', 'cancel',
        'assign', 'unassign', 'transfer', 'export', 'publish', 'regenerate',
        'sync', 'adjust', 'set', 'grant', 'copy',
    ];

    /**
     * Actions that only record browsing the interface.
     *
     * @var array<int, string>
     */
    private const VIEW_ACTIONS = ['view', 'open_create', 'open_edit'];

    public function __construct(
        private UserActivityRepository $repository,
        private SettingService $settingService,
    ) {}

    /**
     * Record a single user action (called by the request middleware).
     */
    public function record(
        int $userId,
        string $action,
        ?string $entity,
        string $method,
        ?string $url,
        ?string $ip,
        ?string $userAgent
    ): void {
        $this->repository->create([
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'method' => $method,
            'url' => $url === null ? null : substr($url, 0, 500),
            'ip_address' => $ip,
            'user_agent' => $userAgent === null ? null : substr($userAgent, 0, 500),
            'created_at' => now(),
        ]);
    }

    /**
     * Record a successful sign-in (Login event).
     */
    public function recordLogin(User $user): void
    {
        $this->record(
            (int) $user->getAuthIdentifier(),
            'login',
            'auth',
            'POST',
            null,
            request()->ip(),
            request()->userAgent()
        );
    }

    /**
     * Record a sign-out (Logout event).
     */
    public function recordLogout(User $user): void
    {
        $this->record(
            (int) $user->getAuthIdentifier(),
            'logout',
            'auth',
            'POST',
            null,
            request()->ip(),
            request()->userAgent()
        );
    }

    /**
     * Total active minutes derived from a set of activity timestamps.
     *
     * Timestamps do not need to be pre-sorted. Sessions are merged while the
     * gap between consecutive actions is shorter than `$idleGapMinutes`; a
     * gap of that length or longer closes the session, so the idle period is
     * excluded. Durations are accumulated in seconds and rounded once to the
     * nearest minute (e.g. a 90-second span counts as 2 minutes) so short
     * sessions are not systematically undercounted.
     *
     * @param  iterable<int, Carbon|string|int>  $timestamps
     */
    public function calculateActiveMinutes(
        iterable $timestamps,
        int $idleGapMinutes = self::IDLE_GAP_MINUTES,
        int $maxSessionMinutes = self::MAX_SESSION_MINUTES
    ): int {
        $times = collect($timestamps)
            ->map(static fn ($t): int => $t instanceof Carbon ? $t->getTimestamp() : (is_int($t) ? $t : Carbon::parse($t)->getTimestamp()))
            ->sort()
            ->values()
            ->all();

        if ($times === []) {
            return 0;
        }

        return $this->activeMinutesFromUnix(
            $times,
            max(0, $idleGapMinutes) * 60,
            max(0, $maxSessionMinutes) * 60
        );
    }

    /**
     * Core session-merging algorithm over ascending unix timestamps.
     *
     * Kept in one place so the chunked report aggregations (which never
     * materialize Carbon instances for memory reasons) compute exactly the
     * same totals as {@see self::calculateActiveMinutes()}.
     *
     * @param  array<int, int>  $timestamps  ascending unix timestamps
     */
    private function activeMinutesFromUnix(array $timestamps, int $idleGapSeconds, int $maxSessionSeconds): int
    {
        if ($timestamps === []) {
            return 0;
        }

        $totalSeconds = 0;
        $sessionStart = $timestamps[0];
        $last = $sessionStart;
        $count = count($timestamps);

        for ($i = 1; $i < $count; $i++) {
            $current = $timestamps[$i];

            // A gap of at least $idleGapSeconds closes the session. The idle
            // period itself is never counted, so a short break (e.g. leaving
            // the computer for two minutes) must not inflate the total.
            if ($current - $last >= $idleGapSeconds) {
                $totalSeconds += min($last - $sessionStart, $maxSessionSeconds);
                $sessionStart = $current;
            }

            $last = $current;
        }

        $totalSeconds += min($last - $sessionStart, $maxSessionSeconds);

        return max(0, (int) round($totalSeconds / 60));
    }

    /**
     * Raw `created_at` value (`Y-m-d H:i:s` app-local string) to a unix
     * timestamp without building a Carbon instance.
     */
    private function toUnixTimestamp(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $string = (string) $value;
        $ts = strtotime($string);

        // Fail loud on unparseable values, mirroring Carbon::parse().
        return $ts === false ? Carbon::parse($string)->getTimestamp() : $ts;
    }

    /**
     * The idle gap in minutes used for the active-time computation.
     *
     * Resolution order: persisted setting → module config → default. The
     * setting is editable from the activity pages, so the threshold can be
     * changed dynamically without touching any code.
     */
    public function idleGapMinutes(): int
    {
        return (int) $this->settingService->getValue(
            self::IDLE_GAP_SETTING_KEY,
            config('useractivity.idle_gap_minutes', self::IDLE_GAP_MINUTES)
        );
    }

    /**
     * Persist a new idle gap (clamped to the supported 1–120 minute range).
     */
    public function saveIdleGapMinutes(int $minutes): void
    {
        $this->settingService->setValue(
            self::IDLE_GAP_SETTING_KEY,
            max(1, min(120, $minutes)),
            [
                'type' => 'integer',
                'group' => 'general',
                'name_ar' => 'فجوة الخمول (دقائق)',
                'name_en' => 'Idle gap (minutes)',
                'description' => __('useractivity.idle_gap_setting_description'),
            ],
        );
    }

    /**
     * Active minutes using the configured idle gap (persisted setting →
     * module config → default).
     *
     * @param  iterable<int, Carbon|string|int>  $timestamps
     */
    private function activeMinutes(iterable $timestamps): int
    {
        return $this->calculateActiveMinutes(
            $timestamps,
            $this->idleGapMinutes(),
            self::MAX_SESSION_MINUTES
        );
    }

    /**
     * Summary data for the monitoring index page.
     *
     * @return array{
     *     totals: array<string, mixed>,
     *     top_entities: array<int, array<string, mixed>>,
     *     users: array<string, mixed>,
     * }
     */
    public function overview(string $from, string $to, ?string $search, int $page = 1, int $perPage = 15): array
    {
        [$fromLocal, $toLocal] = $this->localDayBounds($from, $to);

        $search = trim((string) $search);

        $rows = $search === ''
            ? $this->repository->aggregateByUser($fromLocal, $toLocal)
            : $this->repository->searchUsers($search)->map(function ($user) use ($fromLocal, $toLocal): object {
                $aggregate = $this->repository->userAggregates((int) $user->id, $fromLocal, $toLocal);

                return (object) [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_code' => $user->employee_code,
                    'avatar' => $user->avatar,
                    'department_name' => $user->department_name,
                    'position_name' => $user->position_name,
                    'actions' => (int) $aggregate->actions,
                    'logins' => (int) $aggregate->logins,
                    'first_active_at' => $aggregate->first_active_at,
                    'last_active_at' => $aggregate->last_active_at,
                ];
            });

        $timings = $this->timingsPerUser($fromLocal, $toLocal);

        $users = $rows->map(function ($row) use ($timings): array {
            $userId = (int) $row->id;
            $timing = $timings[$userId] ?? ['minutes' => 0, 'days' => 0];

            return [
                'id' => $userId,
                'name' => $row->name,
                'email' => $row->email,
                'employee_code' => $row->employee_code,
                'avatar_url' => $row->avatar ? Storage::disk('public')->url($row->avatar) : null,
                'department_name' => $row->department_name ?? null,
                'position_name' => $row->position_name ?? null,
                'actions' => (int) ($row->actions ?? 0),
                'logins' => (int) ($row->logins ?? 0),
                'active_minutes' => (int) $timing['minutes'],
                'active_days' => (int) $timing['days'],
                'first_active_at' => $this->formatDateTime($row->first_active_at ?? null),
                'last_active_at' => $this->formatDateTime($row->last_active_at ?? null),
            ];
        })->sortByDesc('active_minutes')->sortByDesc('actions')->values();

        $activeUsers = $users->where('actions', '>', 0)->count();

        $totals = [
            'active_users' => $activeUsers,
            'inactive_users' => $search === '' ? max(0, $this->repository->countEmployees() - $activeUsers) : 0,
            'total_actions' => (int) $users->sum('actions'),
            'total_active_minutes' => (int) $users->sum('active_minutes'),
        ];

        return [
            'totals' => $totals,
            'top_entities' => $this->repository->topEntities($fromLocal, $toLocal)
                ->map(fn ($row): array => [
                    'entity' => $row->entity,
                    'action' => $row->action,
                    'count' => (int) $row->count,
                ])
                ->values()
                ->all(),
            'users' => $this->paginate($users, $page, $perPage),
        ];
    }

    /**
     * Full detail for one user (KPIs, breakdown, daily series, timeline).
     *
     * @return array<string, mixed>
     */
    public function userDetail(User $user, string $from, string $to): array
    {
        [$fromLocal, $toLocal] = $this->localDayBounds($from, $to);
        $userId = (int) $user->getAuthIdentifier();

        // Single chunked pass over lightweight query-builder rows. Loading
        // full Eloquent models for a heavy user exhausts PHP memory and the
        // page dies with a 500 — only plain scalars are accumulated here.
        $total = 0;
        $real = 0;
        $views = 0;
        $logins = 0;
        $firstActiveAt = null;
        $lastActiveAt = null;
        $allTimes = [];
        $breakdown = [];
        $daily = [];

        $this->repository->chunkUserRows($userId, $fromLocal, $toLocal, function ($rows) use (
            &$total, &$real, &$views, &$logins, &$firstActiveAt, &$lastActiveAt, &$allTimes, &$breakdown, &$daily
        ): void {
            foreach ($rows as $row) {
                $total++;

                $action = (string) $row->action;
                $createdAt = (string) $row->created_at;

                if (in_array($action, self::MUTATION_ACTIONS, true)) {
                    $real++;
                }
                if (in_array($action, self::VIEW_ACTIONS, true)) {
                    $views++;
                }
                if ($action === 'login') {
                    $logins++;
                }

                $timestamp = $this->toUnixTimestamp($createdAt);
                $allTimes[] = $timestamp;

                $date = substr($createdAt, 0, 10);
                if (! isset($daily[$date])) {
                    $daily[$date] = ['actions' => 0, 'times' => []];
                }
                $daily[$date]['actions']++;
                $daily[$date]['times'][] = $timestamp;

                $entity = $row->entity ?: 'other';
                $key = $entity."\0".$action;
                if (! isset($breakdown[$key])) {
                    $breakdown[$key] = ['entity' => $entity, 'action' => $action, 'count' => 0];
                }
                $breakdown[$key]['count']++;

                // Rows arrive in id order, which can differ from created_at
                // order when timestamps are backfilled — track the extremes.
                if ($firstActiveAt === null || $createdAt < $firstActiveAt) {
                    $firstActiveAt = $createdAt;
                }
                if ($lastActiveAt === null || $createdAt > $lastActiveAt) {
                    $lastActiveAt = $createdAt;
                }
            }
        });

        $kpis = [
            'total_actions' => $total,
            'real_actions' => $real,
            'views' => $views,
            'logins' => $logins,
            'active_minutes' => $this->activeMinutes($allTimes),
            'active_days' => count($daily),
            'first_active_at' => $this->formatDateTime($firstActiveAt),
            'last_active_at' => $this->formatDateTime($lastActiveAt),
        ];

        $breakdown = collect($breakdown)
            ->sortByDesc('count')
            ->values()
            ->take(12)
            ->all();

        ksort($daily);
        $dailyRows = [];
        foreach ($daily as $date => $group) {
            $dailyRows[] = [
                'date' => $date,
                'actions' => $group['actions'],
                'active_minutes' => $this->activeMinutes($group['times']),
            ];
        }
        $daily = $dailyRows;

        $timeline = $this->repository->recentForUser($userId, $fromLocal, $toLocal, 100)
            ->map(fn ($log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'entity' => $log->entity,
                'method' => $log->method,
                'url' => $log->url,
                'ip_address' => $log->ip_address,
                'created_at' => $this->formatDateTime($log->created_at),
            ])
            ->values()
            ->all();

        return [
            'kpis' => $kpis,
            'breakdown' => $breakdown,
            'daily' => $daily,
            'timeline' => $timeline,
        ];
    }

    /**
     * Active minutes and distinct active days per user inside the range.
     *
     * @return array<int, array{minutes: int, days: int}>
     */
    private function timingsPerUser(Carbon $fromLocal, Carbon $toLocal): array
    {
        // Chunked pass over raw (user_id, created_at) pairs. Only plain
        // unix ints are kept in memory — never Eloquent models. Sorting
        // happens inside calculateActiveMinutes(), so chunk order is free.
        $times = [];
        $dates = [];

        $this->repository->chunkRangeTimestamps($fromLocal, $toLocal, function ($rows) use (&$times, &$dates): void {
            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                $createdAt = (string) $row->created_at;

                $times[$userId][] = $this->toUnixTimestamp($createdAt);
                $dates[$userId][substr($createdAt, 0, 10)] = true;
            }
        });

        $result = [];

        foreach ($times as $userId => $userTimes) {
            $result[$userId] = [
                'minutes' => $this->activeMinutes($userTimes),
                'days' => count($dates[$userId]),
            ];
        }

        return $result;
    }

    /**
     * Build a LengthAwarePaginator payload from a collection.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function paginate(Collection $items, int $page, int $perPage): array
    {
        $total = $items->count();
        $page = max(1, $page);

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return $paginator->toArray();
    }

    /**
     * Local-day boundary Carbon instances for a local date range.
     *
     * Activity rows are persisted with `now()` and Eloquent formats that
     * Carbon in the app timezone, so the datetimes stored in
     * `user_activity_logs.created_at` are naive app-local values (e.g.
     * `2026-08-12 22:00:07` for Asia/Riyadh). Queries must therefore use
     * app-local day bounds — shifting them to UTC would exclude every row
     * recorded after UTC midnight of the `to` day and empty the reports.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function localDayBounds(string $from, string $to): array
    {
        $fromDay = Carbon::parse($from);
        $toDay = Carbon::parse($to);

        return [
            $fromDay->copy()->startOfDay(),
            $toDay->copy()->endOfDay(),
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value->format('Y-m-d H:i:s') : Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
