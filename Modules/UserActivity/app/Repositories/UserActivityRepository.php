<?php

namespace Modules\UserActivity\Repositories;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\UserActivity\Models\UserActivityLog;
use Modules\Users\Models\User;

/**
 * UserActivityRepository — all database access for the activity logs.
 */
class UserActivityRepository
{
    /**
     * Persist a new activity row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UserActivityLog
    {
        return UserActivityLog::create($data);
    }

    /**
     * Stream every (user_id, created_at) pair inside the range in small
     * chunks, ordered by user then time.
     *
     * Reporting must never hydrate Eloquent models for the whole range:
     * with hundreds of thousands of rows that exhausts the PHP memory
     * limit and the page dies with a 500. Raw query-builder rows keep the
     * memory footprint flat regardless of table size.
     */
    public function chunkRangeTimestamps(Carbon $from, Carbon $to, callable $callback, int $chunkSize = 10000): void
    {
        // chunkById (not offset chunking): each page seeks on the primary
        // key, so later pages stay as fast as the first ones.
        DB::table('user_activity_logs')
            ->select(['id', 'user_id', 'created_at'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->chunkById($chunkSize, $callback);
    }

    /**
     * Stream one user's (created_at, action, entity) rows in small chunks,
     * oldest first. Same memory rationale as {@see self::chunkRangeTimestamps()}.
     */
    public function chunkUserRows(int $userId, Carbon $from, Carbon $to, callable $callback, int $chunkSize = 5000): void
    {
        // chunkById (not offset chunking): each page seeks on the primary
        // key, so later pages stay as fast as the first ones.
        DB::table('user_activity_logs')
            ->select(['id', 'created_at', 'action', 'entity'])
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->chunkById($chunkSize, $callback);
    }

    /**
     * The newest log rows for one user inside the range (detail timeline).
     *
     * Only the display fields are selected and the query is capped, so it
     * stays cheap no matter how many rows the user has in total.
     *
     * @return Collection<int, object>
     */
    public function recentForUser(int $userId, Carbon $from, Carbon $to, int $limit = 100): Collection
    {
        return DB::table('user_activity_logs')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'action', 'entity', 'method', 'url', 'ip_address', 'created_at']);
    }

    /**
     * Per-user aggregates for every user with activity inside the range.
     *
     * @return Collection<int, object>
     */
    public function aggregateByUser(Carbon $from, Carbon $to): Collection
    {
        return DB::table('user_activity_logs')
            ->join('users', 'users.id', '=', 'user_activity_logs.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->leftJoin('positions', 'positions.id', '=', 'users.position_id')
            ->whereBetween('user_activity_logs.created_at', [$from, $to])
            ->groupBy('users.id')
            ->orderByDesc(DB::raw('COUNT(user_activity_logs.id)'))
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'users.employee_code',
                'users.avatar',
                'users.department_id',
                'users.position_id',
                'departments.department_name',
                'positions.position_name',
                DB::raw('COUNT(user_activity_logs.id) as actions'),
                DB::raw("SUM(CASE WHEN user_activity_logs.action = 'login' THEN 1 ELSE 0 END) as logins"),
                DB::raw('MIN(user_activity_logs.created_at) as first_active_at'),
                DB::raw('MAX(user_activity_logs.created_at) as last_active_at'),
            ]);
    }

    /**
     * Aggregates for a single user inside the range (may be all-zero).
     *
     * @return object{actions: int, logins: int, first_active_at: ?string, last_active_at: ?string}
     */
    public function userAggregates(int $userId, Carbon $from, Carbon $to): object
    {
        return DB::table('user_activity_logs')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as actions')
            ->selectRaw("SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) as logins")
            ->selectRaw('MIN(created_at) as first_active_at')
            ->selectRaw('MAX(created_at) as last_active_at')
            ->first() ?? (object) ['actions' => 0, 'logins' => 0, 'first_active_at' => null, 'last_active_at' => null];
    }

    /**
     * Users matching a name / email / employee-code search.
     *
     * @return Collection<int, object>
     */
    public function searchUsers(string $search): Collection
    {
        return User::query()
            ->withoutSuperAdmin()
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->leftJoin('positions', 'positions.id', '=', 'users.position_id')
            ->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.full_name_ar', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.employee_code', 'like', "%{$search}%");
            })
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'users.employee_code',
                'users.avatar',
                'users.department_id',
                'users.position_id',
                'departments.department_name',
                'positions.position_name',
            ]);
    }

    /**
     * The most frequent entity+action combinations across all users.
     *
     * @return Collection<int, object>
     */
    public function topEntities(Carbon $from, Carbon $to, int $limit = 6): Collection
    {
        return DB::table('user_activity_logs')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('entity', 'action')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->get(['entity', 'action', DB::raw('COUNT(*) as count')]);
    }

    /**
     * Total number of employees (excluding the system super-admin).
     */
    public function countEmployees(): int
    {
        return User::query()->withoutSuperAdmin()->count();
    }
}
