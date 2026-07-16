<?php

namespace Modules\Shifts\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Shifts\Models\Shift;
use Modules\Shifts\Repositories\ShiftRepository;

class ShiftService
{
    public function __construct(
        private ShiftRepository $repository
    ) {}

    /**
     * Get all shifts with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllShifts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get all active shifts.
     *
     * @return Collection<int, Shift>
     */
    public function getActiveShifts(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Get all shifts for a specific company.
     *
     * @return Collection<int, Shift>
     */
    public function getShiftsByCompany(int $companyId): Collection
    {
        return $this->repository->getByCompany($companyId);
    }

    /**
     * Get all shifts for a specific branch.
     *
     * @return Collection<int, Shift>
     */
    public function getShiftsByBranch(int $branchId): Collection
    {
        return $this->repository->getByBranch($branchId);
    }

    /**
     * Find a shift by its primary key.
     */
    public function getShiftById(int $id): ?Shift
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new shift.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createShift(array $data): Shift
    {
        $validated = $this->validateShiftData($data);

        return $this->repository->create($validated);
    }

    /**
     * Update the given shift.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateShift(Shift $shift, array $data): Shift
    {
        $validated = $this->validateShiftData($data, $shift->id, $shift->branch_id);

        return $this->repository->update($shift, $validated);
    }

    /**
     * Soft delete the given shift.
     */
    public function deleteShift(Shift $shift): bool
    {
        return $this->repository->delete($shift);
    }

    /**
     * Validate shift data. The shift_code must be unique within the same branch.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateShiftData(array $data, ?int $ignoreId = null, ?int $branchId = null): array
    {
        $targetBranchId = $branchId ?? ($data['branch_id'] ?? null);

        $rules = [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'shift_code' => [
                'required', 'string', 'max:50',
                $this->uniqueShiftCodeRule($ignoreId, $targetBranchId),
            ],
            'shift_name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'gte:start_time'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'working_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['integer', 'between:0,6'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Build the unique rule for shift_code scoped to a branch.
     *
     * @return array<int, string>
     */
    protected function uniqueShiftCodeRule(?int $ignoreId, ?int $branchId): array
    {
        if (! $branchId) {
            return ['string'];
        }

        $table = 'shifts';
        $column = 'shift_code';
        $ignore = $ignoreId ? (string) $ignoreId : 'NULL';

        $wheres = "branch_id = {$branchId}";

        return ["unique:{$table},{$column},{$ignore},id,{$wheres}"];
    }
}
