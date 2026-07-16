<?php

namespace Modules\Positions\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Positions\Models\Position;
use Modules\Positions\Repositories\PositionRepository;

class PositionService
{
    public function __construct(
        private PositionRepository $repository
    ) {}

    /**
     * Get all positions with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllPositions(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get all active positions.
     *
     * @return Collection<int, Position>
     */
    public function getActivePositions(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Get all positions for a specific department.
     *
     * @return Collection<int, Position>
     */
    public function getPositionsByDepartment(int $departmentId): Collection
    {
        return $this->repository->getByDepartment($departmentId);
    }

    /**
     * Get all positions for a specific branch.
     *
     * @return Collection<int, Position>
     */
    public function getPositionsByBranch(int $branchId): Collection
    {
        return $this->repository->getByBranch($branchId);
    }

    /**
     * Get all positions for a specific company.
     *
     * @return Collection<int, Position>
     */
    public function getPositionsByCompany(int $companyId): Collection
    {
        return $this->repository->getByCompany($companyId);
    }

    /**
     * Find a position by its primary key.
     */
    public function getPositionById(int $id): ?Position
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new position.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createPosition(array $data): Position
    {
        $validated = $this->validatePositionData($data);

        return $this->repository->create($validated);
    }

    /**
     * Update the given position.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updatePosition(Position $position, array $data): Position
    {
        $validated = $this->validatePositionData($data, $position->id, $position->department_id);

        return $this->repository->update($position, $validated);
    }

    /**
     * Soft delete the given position.
     */
    public function deletePosition(Position $position): bool
    {
        return $this->repository->delete($position);
    }

    /**
     * Validate position data. The position_code must be unique within the same department.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validatePositionData(array $data, ?int $ignoreId = null, ?int $departmentId = null): array
    {
        $targetDepartmentId = $departmentId ?? ($data['department_id'] ?? null);

        $rules = [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_code' => [
                'required', 'string', 'max:50',
                $this->uniquePositionCodeRule($ignoreId, $targetDepartmentId),
            ],
            'position_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'requirements' => ['nullable', 'string'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Build the unique rule for position_code scoped to a department.
     *
     * @return array<int, string>
     */
    protected function uniquePositionCodeRule(?int $ignoreId, ?int $departmentId): array
    {
        if (! $departmentId) {
            return ['string'];
        }

        $table = 'positions';
        $column = 'position_code';
        $ignore = $ignoreId ? (string) $ignoreId : 'NULL';

        $wheres = "department_id = {$departmentId}";

        return ["unique:{$table},{$column},{$ignore},id,{$wheres}"];
    }
}
