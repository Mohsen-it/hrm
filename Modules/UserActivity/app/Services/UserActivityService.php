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
     * @param  iterable<int, Carbon|string>  $timestamps
     */
    public function calculateActiveMinutes(
        iterable $timestamps,
        int $idleGapMinutes = self::IDLE_GAP_MINUTES,
        int $maxSessionMinutes = self::MAX_SESSION_MINUTES
    ): int {
        $times = collect($timestamps)
            ->map(static fn ($t): Carbon => $t instanceof Carbon ? $t : Carbon::parse($t))
            ->sort()
            ->values();

        if ($times->isEmpty()) {
            return 0;
        }

        $idleGapSeconds = max(0, $idleGapMinutes) * 60;
        $maxSessionSeconds = max(0, $maxSessionMinutes) * 60;

        $totalSeconds = 0;
        $sessionStart = $times->first()->getTimestamp();
        $last = $sessionStart;

        foreach ($times->slice(1) as $time) {
            $current = $time->getTimestamp();

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
     * @param  iterable<int, Carbon|string>  $timestamps
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

        $logs = $this->repository->logsForUser((int) $user->getAuthIdentifier(), $fromLocal, $toLocal);
        $timestamps = $logs->pluck('created_at');

        $kpis = [
            'total_actions' => $logs->count(),
            'real_actions' => $logs->whereIn('action', self::MUTATION_ACTIONS)->count(),
            'views' => $logs->whereIn('action', self::VIEW_ACTIONS)->count(),
            'logins' => $logs->where('action', 'login')->count(),
            'active_minutes' => $this->activeMinutes($timestamps),
            'active_days' => $timestamps->map(static fn (Carbon $c): string => $c->toDateString())->unique()->count(),
            'first_active_at' => $this->formatDateTime($logs->first()?->created_at),
            'last_active_at' => $this->formatDateTime($logs->last()?->created_at),
        ];

        $breakdown = $logs
            ->groupBy(static fn ($log): string => $log->entity ?: 'other')
            ->flatMap(function (Collection $group, string $entity): array {
                return $group->groupBy('action')
                    ->map(fn (Collection $actions): int => $actions->count())
                    ->map(fn (int $count, string $action): array => [
                        'entity' => $entity,
                        'action' => $action,
                        'count' => $count,
                    ])
                    ->values()
                    ->all();
            })
            ->sortByDesc('count')
            ->values()
            ->take(12)
            ->all();

        $daily = $logs->groupBy(static fn ($log): string => $log->created_at->toDateString())
            ->map(function (Collection $group, string $date): array {
                return [
                    'date' => $date,
                    'actions' => $group->count(),
                    'active_minutes' => $this->activeMinutes($group->pluck('created_at')),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        $timeline = $logs->reverse()->take(100)->values()->map(static fn ($log): array => [
            'id' => $log->id,
            'action' => $log->action,
            'entity' => $log->entity,
            'method' => $log->method,
            'url' => $log->url,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
        ])->all();

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
        $grouped = [];

        foreach ($this->repository->allInRange($fromLocal, $toLocal) as $log) {
            $userId = (int) $log->user_id;

            $grouped[$userId][] = $log->created_at;
        }

        $result = [];

        foreach ($grouped as $userId => $times) {
            $result[$userId] = [
                'minutes' => $this->activeMinutes($times),
                'days' => collect($times)->map(static fn (Carbon $c): string => $c->toDateString())->unique()->count(),
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
