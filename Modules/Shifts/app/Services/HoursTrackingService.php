<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Shifts\Enums\TrackingStatus;
use Modules\Shifts\Models\HoursTracking;
use Modules\Shifts\Repositories\EmployeeShiftCategoryRepository;
use Modules\Shifts\Repositories\HoursTrackingRepository;

class HoursTrackingService
{
    public function __construct(
        private HoursTrackingRepository $trackingRepository,
        private EmployeeShiftCategoryRepository $assignmentRepository
    ) {}

    /**
     * Calculate period hours for an employee and upsert tracking record.
     */
    public function calculatePeriodHours(int $employeeId, Carbon $periodStart, Carbon $periodEnd): void
    {
        $actual = DB::table('iclock_transaction')
            ->where('emp_id', $employeeId)
            ->whereBetween('punch_time', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
            ->sum('duration');

        $actualHours = round($actual / 3600, 2);

        $assignment = $this->assignmentRepository->getActiveAssignment($employeeId);
        $category = $assignment?->shiftCategory;
        $requiredHours = $category ? (float) ($category->required_hours ?? 0) : 0;

        $surplus = 0;
        $deficit = 0;
        $status = TrackingStatus::OnTrack->value;

        if ($actualHours > $requiredHours) {
            $surplus = round($actualHours - $requiredHours, 2);
            $status = TrackingStatus::Surplus->value;
        } elseif ($actualHours < $requiredHours) {
            $deficit = round($requiredHours - $actualHours, 2);
            $status = TrackingStatus::Deficit->value;
        }

        $this->trackingRepository->upsertTracking($employeeId, [
            'shift_category_id' => $category?->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'period_type' => $category->period_type ?? 'monthly',
            'required_hours' => $requiredHours,
            'actual_hours' => $actualHours,
            'surplus_hours' => $surplus,
            'deficit_hours' => $deficit,
            'status' => $status,
        ]);
    }

    /**
     * Get deficit report for the given period, optionally filtered by department.
     *
     * @return Collection<int, HoursTracking>
     */
    public function getDeficitReport(?int $departmentId, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $query = $this->trackingRepository->query()
            ->with($this->trackingRepository->defaultWith ?? ['employee', 'shiftCategory'])
            ->whereBetween('period_start', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('deficit_hours', '>', 0);

        if ($departmentId !== null) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        return $query->get();
    }
}
