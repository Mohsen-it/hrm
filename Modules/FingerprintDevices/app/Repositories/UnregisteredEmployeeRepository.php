<?php

namespace Modules\FingerprintDevices\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Users\Models\User;

/**
 * Repository for querying employees who have NO biometric fingerprints registered.
 */
class UnregisteredEmployeeRepository
{
    public function query(): Builder
    {
        return User::query()->active();
    }

    /**
     * Get paginated employees without any fingerprint templates.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()
                ->select([
                    'id', 'employee_code', 'first_name', 'last_name', 'name',
                    'full_name_ar', 'full_name_en', 'email', 'avatar',
                    'company_id', 'branch_id', 'department_id', 'subordination_id',
                    'status', 'is_active_employee',
                ])
                ->with([
                    'company:id,company_name',
                    'branch:id,branch_name',
                    'department:id,department_name',
                    'subordination:id,code,name_ar,name_en',
                ])
                ->whereDoesntHave('fingerprintTemplates'),
            $filters
        )
            ->orderBy('users.id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Count employees without any fingerprint templates.
     *
     * @param  array<string, mixed>  $filters
     */
    public function countAll(array $filters = []): int
    {
        return $this->applyFilters(
            $this->query()->whereDoesntHave('fingerprintTemplates'),
            $filters
        )->count();
    }

    /**
     * Apply filters to the query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->withoutSuperAdmin();

        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('full_name_ar', 'like', "%{$search}%")
                    ->orWhere('full_name_en', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        $query->when($filters['branch_id'] ?? null, function (Builder $q, int $branchId): void {
            $q->where('branch_id', $branchId);
        });

        $query->when($filters['department_id'] ?? null, function (Builder $q, int $departmentId): void {
            $q->where('department_id', $departmentId);
        });

        $query->when($filters['subordination_id'] ?? null, function (Builder $q, int $subordinationId): void {
            $q->where('subordination_id', $subordinationId);
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', (int) $filters['status']);
        });

        $query->when(isset($filters['is_active_employee']), function (Builder $q) use ($filters): void {
            $q->where('is_active_employee', (bool) $filters['is_active_employee']);
        });

        return $query;
    }
}
