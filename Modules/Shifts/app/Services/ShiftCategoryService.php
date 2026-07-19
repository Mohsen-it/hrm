<?php

namespace Modules\Shifts\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Shifts\Models\CategoryTimeSchedule;
use Modules\Shifts\Models\ShiftCategory;
use Modules\Shifts\Repositories\ShiftCategoryRepository;
use Modules\Shifts\Services\Traits\ResolvesCompanyId;

class ShiftCategoryService
{
    use ResolvesCompanyId;

    public function __construct(
        private ShiftCategoryRepository $repository,
        private ShiftCategoryValidationService $validationService
    ) {}

    /**
     * Get all shift categories with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Find a shift category by its primary key.
     */
    public function getById(int $id): ?ShiftCategory
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new shift category.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): ShiftCategory
    {
        return DB::transaction(function () use ($data) {
            $validated = $this->validationService->validateCreate($data);

            if (empty($validated['company_id'])) {
                $validated['company_id'] = $this->resolveCompanyId();
            }

            $this->hydrateDynamicEngine($validated);

            $category = $this->repository->create($validated);

            if (! empty($data['time_schedule_id'])) {
                CategoryTimeSchedule::create([
                    'shift_category_id' => $category->id,
                    'time_schedule_id' => $data['time_schedule_id'],
                ]);
            }

            return $category;
        });
    }

    /**
     * Hydrate the dynamic-engine columns from the validated payload.
     *
     * When the category is flagged dynamic (cyclic), denormalise the cycle
     * length so date-difference math and reporting can index it directly.
     *
     * @param  array<string, mixed>  $validated
     */
    private function hydrateDynamicEngine(array &$validated): void
    {
        $isDynamic = ! empty($validated['is_dynamic']);

        if ($isDynamic && ($validated['type'] ?? null) === 'cyclic') {
            $validated['is_dynamic'] = true;
            $validated['cycle_length'] = (int) ($validated['cycle_length']
                ?? ((int) ($validated['work_days'] ?? 0) + (int) ($validated['rest_days'] ?? 0)));
        } else {
            $validated['is_dynamic'] = false;
            $validated['cycle_length'] = null;
        }
    }

    /**
     * Update the given shift category.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(int $id, array $data): ShiftCategory
    {
        $category = $this->repository->findById($id);

        $validated = $this->validationService->validateUpdate($category, $data);

        $this->hydrateDynamicEngine($validated);

        $category = $this->repository->update($category, $validated);

        if (array_key_exists('time_schedule_id', $data)) {
            $scheduleId = $data['time_schedule_id'] ?: null;

            if ($scheduleId) {
                CategoryTimeSchedule::updateOrCreate(
                    ['shift_category_id' => $category->id],
                    ['time_schedule_id' => $scheduleId]
                );
            } else {
                CategoryTimeSchedule::where('shift_category_id', $category->id)->delete();
            }
        }

        return $category->fresh([...$this->repository->defaultWith ?? [], 'employees']);
    }

    /**
     * Delete the given shift category.
     *
     * @throws ValidationException
     */
    public function delete(int $id): bool
    {
        if ($this->repository->hasActiveEmployees($id)) {
            throw ValidationException::withMessages([
                'id' => [__('shifts.category_has_active_employees')],
            ]);
        }

        $category = $this->repository->findById($id);

        return $this->repository->delete($category);
    }
}
