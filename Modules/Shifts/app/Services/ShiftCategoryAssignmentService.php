<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Repositories\EmployeeShiftCategoryRepository;
use Modules\Shifts\Repositories\ShiftCategoryRepository;

class ShiftCategoryAssignmentService
{
    public function __construct(
        private EmployeeShiftCategoryRepository $assignmentRepository,
        private AssignmentValidationService $validationService,
        private ShiftCategoryRepository $categoryRepository
    ) {}

    /**
     * Assign a single employee to a shift category.
     *
     * @throws ValidationException
     */
    public function assignEmployee(int $employeeId, int $categoryId, string $startDate, ?string $endDate): EmployeeShiftCategory
    {
        $this->validationService->validateAssign($employeeId, $categoryId, $startDate, $endDate);

        $this->closePreviousActive($employeeId, $startDate);

        return $this->assignmentRepository->create([
            'employee_id' => $employeeId,
            'shift_category_id' => $categoryId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Bulk assign multiple employees to a shift category.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, EmployeeShiftCategory>
     *
     * @throws ValidationException
     */
    public function bulkAssign(array $employeeIds, int $categoryId, string $startDate): array
    {
        $this->validationService->validateBulkAssign($employeeIds, $categoryId, $startDate);

        $assignments = [];

        foreach ($employeeIds as $employeeId) {
            $assignments[] = $this->assignEmployee($employeeId, $categoryId, $startDate, null);
        }

        return $assignments;
    }

    /**
     * Transfer an employee from their current category to a new one.
     *
     * @throws ValidationException
     */
    public function transferEmployee(int $employeeId, int $newCategoryId, string $effectiveDate): EmployeeShiftCategory
    {
        $previousDay = Carbon::parse($effectiveDate)->subDay()->toDateString();

        $this->closeAssignment($employeeId, $previousDay);

        return $this->assignEmployee($employeeId, $newCategoryId, $effectiveDate, null);
    }

    /**
     * Unassign an employee from their current shift category.
     */
    public function unassignEmployee(int $employeeId, string $endDate): ?EmployeeShiftCategory
    {
        $active = $this->assignmentRepository->getActiveAssignment($employeeId);

        if (! $active) {
            return null;
        }

        return $this->assignmentRepository->closeAssignment($active, $endDate);
    }

    /**
     * Get the currently active assignment for the given employee.
     */
    public function getActiveAssignment(int $employeeId): ?EmployeeShiftCategory
    {
        return $this->assignmentRepository->getActiveAssignment($employeeId);
    }

    /**
     * Get all assignments with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllAssignments(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->assignmentRepository->getAll($filters, $perPage);
    }

    /**
     * Close the currently active assignment for the given employee
     * by setting its end date to the day before the new start date.
     */
    private function closePreviousActive(int $employeeId, string $startDate): void
    {
        $active = $this->assignmentRepository->getActiveAssignment($employeeId);

        if ($active) {
            $previousDay = Carbon::parse($startDate)->subDay()->toDateString();

            $this->assignmentRepository->closeAssignment($active, $previousDay);
        }
    }

    /**
     * Close the currently active assignment for the given employee
     * by setting its end date to the provided date.
     */
    private function closeAssignment(int $employeeId, string $endDate): void
    {
        $active = $this->assignmentRepository->getActiveAssignment($employeeId);

        if ($active) {
            $this->assignmentRepository->closeAssignment($active, $endDate);
        }
    }
}
