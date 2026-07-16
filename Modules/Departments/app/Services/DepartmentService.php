<?php

namespace Modules\Departments\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Departments\Models\Department;
use Modules\Departments\Repositories\DepartmentRepository;

class DepartmentService
{
    public function __construct(
        private DepartmentRepository $repository
    ) {}

    /**
     * Get all departments with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllDepartments(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get all departments belonging to a specific branch.
     *
     * @return Collection<int, Department>
     */
    public function getDepartmentsByBranch(int $branchId): Collection
    {
        return $this->repository->getByBranch($branchId);
    }

    /**
     * Get all departments belonging to a specific company.
     *
     * @return Collection<int, Department>
     */
    public function getDepartmentsByCompany(int $companyId): Collection
    {
        return $this->repository->getByCompany($companyId);
    }

    /**
     * Get all root (top-level) departments.
     *
     * @return Collection<int, Department>
     */
    public function getRootDepartments(): Collection
    {
        return $this->repository->getRoots();
    }

    /**
     * Find a department by its primary key.
     */
    public function getDepartmentById(int $id): ?Department
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new department.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createDepartment(array $data): Department
    {
        $validated = $this->validateDepartmentData($data);

        return $this->repository->create($validated);
    }

    /**
     * Update the given department.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateDepartment(Department $department, array $data): Department
    {
        $validated = $this->validateDepartmentData($data, $department->id, $department->branch_id);

        return $this->repository->update($department, $validated);
    }

    /**
     * Soft delete the given department.
     */
    public function deleteDepartment(Department $department): bool
    {
        return $this->repository->delete($department);
    }

    /**
     * Validate department data. The department_code must be unique within the same branch.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateDepartmentData(array $data, ?int $ignoreId = null, ?int $branchId = null): array
    {
        $targetBranchId = $branchId ?? ($data['branch_id'] ?? null);

        $rules = [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_code' => [
                'required', 'string', 'max:50',
                $this->uniqueDepartmentCodeRule($ignoreId, $targetBranchId),
            ],
            'department_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Build the unique rule for department_code scoped to a branch.
     *
     * @return array<int, string>
     */
    protected function uniqueDepartmentCodeRule(?int $ignoreId, ?int $branchId): array
    {
        if (! $branchId) {
            return ['string'];
        }

        $table = 'departments';
        $column = 'department_code';
        $ignore = $ignoreId ? (string) $ignoreId : 'NULL';

        $wheres = "branch_id = {$branchId}";

        return ["unique:{$table},{$column},{$ignore},id,{$wheres}"];
    }
}
