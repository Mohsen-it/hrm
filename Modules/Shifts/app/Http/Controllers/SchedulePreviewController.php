<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shifts\Models\ShiftCategory;
use Modules\Shifts\Repositories\EmployeeShiftCategoryRepository;
use Modules\Shifts\Services\CyclicScheduleCalculator;

class SchedulePreviewController extends Controller
{
    public function __construct(
        private EmployeeShiftCategoryRepository $assignmentRepository,
        private CyclicScheduleCalculator $cyclicCalculator,
    ) {}

    /**
     * Show future schedule preview for a shift category.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $category = ShiftCategory::with('categoryTimeSchedule.timeSchedule')->findOrFail($id);

        $from = $request->query('from', now()->format('Y-m-d'));
        $to = $request->query('to', now()->addYears(5)->format('Y-m-d'));

        // Limit range to max 5 years
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        if ($fromDate->gt($toDate)) {
            return response()->json(['message' => 'Invalid date range'], 422);
        }

        // Max 5 years
        if ($fromDate->diffInYears($toDate) > 5) {
            $toDate = $fromDate->copy()->addYears(5)->endOfDay();
        }

        // Find an active assignment for this category to get the cycle start date
        $assignment = $this->assignmentRepository->query()
            ->where('shift_category_id', $id)
            ->whereNull('end_date')
            ->first();

        // If no active assignment, use the earliest assignment for this category
        if (! $assignment) {
            $assignment = $this->assignmentRepository->query()
                ->where('shift_category_id', $id)
                ->orderBy('start_date')
                ->first();
        }

        // If still no assignment, use current date as cycle start
        $cycleStart = $assignment
            ? Carbon::parse($assignment->start_date)->startOfDay()
            : now()->startOfDay();

        $schedule = [];
        $workDaysCount = 0;
        $restDaysCount = 0;

        if ($category->type === 'cyclic') {
            $workDays = (int) ($category->work_days ?? 0);
            $restDays = (int) ($category->rest_days ?? 0);

            $schedule = $this->cyclicCalculator->getScheduleInRange(
                $cycleStart,
                $workDays,
                $restDays,
                $fromDate,
                $toDate
            );

            $workDaysCount = count(array_filter($schedule, fn ($day) => $day['is_work_day']));
            $restDaysCount = count($schedule) - $workDaysCount;
        } elseif ($category->type === 'weekly') {
            $workDaysJson = $category->work_days_json;
            $workDayNumbers = is_array($workDaysJson) ? $workDaysJson : [];

            $current = $fromDate->copy();
            while ($current->lte($toDate)) {
                $isWorkDay = in_array($current->dayOfWeek, $workDayNumbers);
                $schedule[] = [
                    'date' => $current->format('Y-m-d'),
                    'is_work_day' => $isWorkDay,
                ];

                if ($isWorkDay) {
                    $workDaysCount++;
                } else {
                    $restDaysCount++;
                }
                $current->addDay();
            }
        } elseif ($category->type === 'hours') {
            // For hours type, all days are potential work days
            $current = $fromDate->copy();
            while ($current->lte($toDate)) {
                $schedule[] = [
                    'date' => $current->format('Y-m-d'),
                    'is_work_day' => true,
                ];
                $workDaysCount++;
                $current->addDay();
            }
        }

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'work_days' => $category->work_days,
                'rest_days' => $category->rest_days,
                'work_days_json' => $category->work_days_json,
                'color' => $category->color,
            ],
            'assignment_start' => $assignment?->start_date,
            'cycle_start' => $cycleStart->format('Y-m-d'),
            'range' => [
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
            ],
            'stats' => [
                'total_days' => count($schedule),
                'work_days' => $workDaysCount,
                'rest_days' => $restDaysCount,
            ],
            'schedule' => $schedule,
        ]);
    }
}
