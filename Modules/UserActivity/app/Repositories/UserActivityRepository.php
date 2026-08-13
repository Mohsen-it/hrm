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
     * All log rows inside a UTC range — used for active-time computations.
     *
     * @return Collection<int, UserActivityLog>
     */
    public function allInRange(Carbon $from, Carbon $to): Collection
    {
        return UserActivityLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['id', 'user_id', 'created_at']);
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
     * Every log row for one user inside the range, ordered chronologically.
     *
     * @return Collection<int, UserActivityLog>
     */
    public function logsForUser(int $userId, Carbon $from, Carbon $to): Collection
    {
        return UserActivityLog::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Total number of employees (excluding the system super-admin).
     */
    public function countEmployees(): int
    {
        return User::query()->withoutSuperAdmin()->count();
    }
}
