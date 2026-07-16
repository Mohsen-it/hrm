<?php

namespace Modules\Shifts\Services;

use Illuminate\Validation\ValidationException;
use Modules\Shifts\Repositories\EmployeeShiftCategoryRepository;
use Modules\Users\Models\User;

class AssignmentValidationService
{
    public function __construct(
        private EmployeeShiftCategoryRepository $employeeShiftCategoryRepository
    ) {}

    /**
     * Validate a single employee shift category assignment.
     *
     * @throws ValidationException
     */
    public function validateAssign(int $employeeId, int $categoryId, string $startDate, ?string $endDate): void
    {
        $errors = [];

        $user = User::where('id', $employeeId)->active()->first();

        if (! $user) {
            $errors['employee_id'][] = __('shifts.employee_not_found_or_inactive');
        }

        if ($this->employeeShiftCategoryRepository->hasOverlappingAssignment($employeeId, $startDate, $endDate)) {
            $errors['start_date'][] = __('shifts.overlapping_assignment_exists');
        }

        if ($endDate !== null && $endDate <= $startDate) {
            $errors['end_date'][] = __('shifts.end_date_must_be_after_start_date');
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate a bulk assignment of employees to a shift category.
     *
     * @param  array<int, int>  $employeeIds
     *
     * @throws ValidationException
     */
    public function validateBulkAssign(array $employeeIds, int $categoryId, string $startDate): void
    {
        $errors = [];

        foreach ($employeeIds as $employeeId) {
            $user = User::where('id', $employeeId)->active()->first();

            if (! $user) {
                $errors["employee_{$employeeId}"][] = __('shifts.employee_not_found_or_inactive', ['id' => $employeeId]);

                continue;
            }

            if ($this->employeeShiftCategoryRepository->hasOverlappingAssignment($employeeId, $startDate, null)) {
                $errors["employee_{$employeeId}"][] = __('shifts.overlapping_assignment_exists', ['id' => $employeeId]);
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
