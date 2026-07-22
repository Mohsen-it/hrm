<?php

namespace Modules\Users\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Users\Models\User;

class UserRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = [
        'company',
        'branch',
        'department',
        'subordination',
        'shift',
    ];

    /**
     * Get a new query builder for the users table.
     */
    public function query(): Builder
    {
        return User::query();
    }

    /**
     * Get all users with optional filters and pagination.
     * The system super-admin (id = 10000) is always excluded.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()
                ->select(['id', 'employee_code', 'first_name', 'last_name', 'avatar', 'email', 'company_id', 'branch_id', 'department_id', 'subordination_id', 'shift_id', 'status', 'created_at'])
                ->with([
                    'company:id,company_name',
                    'branch:id,branch_name',
                    'department:id,department_name',
                    'subordination:id,code,name_ar,name_en',
                    'shift:id,shift_name',
                ]),
            $filters
        )
            ->orderBy('users.id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find a user by its primary key.
     */
    public function findById(int $id): ?User
    {
        return $this->query()
            ->with([
                'company',
                'branch',
                'department',
                'position',
                'grade',
                'subordination',
                'shift',
                'manager',
                'shifts',
                'roles',
                'permissions',
                'subordinates',
            ])
            ->find($id);
    }

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('email', $email)
            ->first();
    }

    /**
     * Find a user by employee code.
     */
    public function findByEmployeeCode(string $code): ?User
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('employee_code', $code)
            ->first();
    }

    /**
     * Get all users belonging to a specific company.
     *
     * @return Collection<int, User>
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all users belonging to a specific branch.
     *
     * @return Collection<int, User>
     */
    public function getByBranch(int $branchId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all users belonging to a specific department.
     *
     * @return Collection<int, User>
     */
    public function getByDepartment(int $departmentId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all users assigned to a specific shift.
     *
     * @return Collection<int, User>
     */
    public function getByShift(int $shiftId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('shift_id', $shiftId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all users with a specific grade.
     *
     * @return Collection<int, User>
     */
    public function getByGrade(int $gradeId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('grade_id', $gradeId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all active employees without pagination.
     *
     * @return Collection<int, User>
     */
    public function getActive(): Collection
    {
        return $this->query()
            ->active()
            ->with($this->defaultWith)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get active users belonging to a specific company.
     *
     * @return Collection<int, User>
     */
    public function getActiveByCompany(int $companyId): Collection
    {
        return $this->query()
            ->active()
            ->with($this->defaultWith)
            ->where('company_id', $companyId)
            ->orderBy('users.id', 'desc')
            ->get();
    }

    /**
     * Create a new user record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Update the given user record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh($this->defaultWith);
    }

    /**
     * Soft delete the given user record.
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Permanently remove the user from storage.
     */
    public function forceDelete(User $user): bool
    {
        return $user->forceDelete();
    }

    /**
     * Bulk delete users by their IDs.
     *
     * @param  array<int, int>  $ids
     * @return int Number of deleted rows
     */
    public function bulkDelete(array $ids): int
    {
        return $this->query()
            ->withoutSuperAdmin()
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Apply filters to the user query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        // Exclude the system super-admin by default
        $query->withoutSuperAdmin();

        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('full_name_ar', 'like', "%{$search}%")
                    ->orWhere('full_name_en', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
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

        $query->when($filters['position_id'] ?? null, function (Builder $q, int $positionId): void {
            $q->where('position_id', $positionId);
        });

        $query->when($filters['grade_id'] ?? null, function (Builder $q, int $gradeId): void {
            $q->where('grade_id', $gradeId);
        });

        $query->when($filters['subordination_id'] ?? null, function (Builder $q, int $subordinationId): void {
            $q->where('subordination_id', $subordinationId);
        });

        $query->when($filters['shift_id'] ?? null, function (Builder $q, int $shiftId): void {
            $q->where('shift_id', $shiftId);
        });

        $query->when($filters['manager_id'] ?? null, function (Builder $q, int $managerId): void {
            $q->where('manager_id', $managerId);
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', (int) $filters['status']);
        });

        $query->when(isset($filters['is_active_employee']), function (Builder $q) use ($filters): void {
            $q->where('is_active_employee', (bool) $filters['is_active_employee']);
        });

        $query->when($filters['employment_type'] ?? null, function (Builder $q, string $type): void {
            $q->where('employment_type', $type);
        });

        $query->when($filters['role'] ?? null, function (Builder $q, string $role): void {
            $q->whereHas('roles', function (Builder $sub) use ($role): void {
                $sub->where('name', $role);
            });
        });

        return $query;
    }
}
