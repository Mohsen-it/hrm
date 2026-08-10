<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Shifts\Models\ScheduleEntry;
use Modules\Shifts\Models\SchedulePeriod;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Repositories\ScheduleEntryRepository;
use Modules\Shifts\Repositories\SchedulePeriodRepository;

class ScheduleGenerationService
{
    public function __construct(
        private RotationEngine $rotationEngine,
        private SchedulePeriodRepository $periodRepository,
        private ScheduleEntryRepository $entryRepository,
        private RotationAssignmentRepository $rotationAssignmentRepository,
        private AuditService $auditService,
    ) {}

    /**
     * Generate monthly schedule for all active rotation assignments.
     */
    public function generateMonthlySchedule(int $year, int $month): SchedulePeriod
    {
        return DB::transaction(function () use ($year, $month) {
            $periodStart = Carbon::create($year, $month, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $existingDraft = SchedulePeriod::where('year', $year)
                ->where('month', $month)
                ->where('status', 'draft')
                ->first();

            if ($existingDraft) {
                $existingDraft->entries()->delete();
                $period = $existingDraft;
            } else {
                $period = SchedulePeriod::create([
                    'year' => $year,
                    'month' => $month,
                    'schedule_period_start' => $periodStart,
                    'schedule_period_end' => $periodEnd,
                    'status' => 'draft',
                    'generated_by' => auth()->id(),
                    'generated_at' => now(),
                    'schedule_version' => 1,
                ]);
            }

            // Get every assignment overlapping the month so mid-month
            // assignments (start/end inside the period) are covered too.
            $assignments = $this->rotationAssignmentRepository
                ->getAssignmentsOverlapping($periodStart->toDateString(), $periodEnd->toDateString());

            $entries = [];
            $covered = [];

            foreach ($assignments as $assignment) {
                $rotation = $assignment->rotation;
                $group = $assignment->rotationGroup;

                $dutyCategoryId = $rotation->timeSchedule?->category?->id
                    ?? $rotation->timeSchedule?->categoryTimeSchedule?->shift_category_id;

                $assignmentStart = Carbon::parse($assignment->start_date)->startOfDay();
                $assignmentEnd = $assignment->end_date
                    ? Carbon::parse($assignment->end_date)->startOfDay()
                    : null;

                $current = $periodStart->copy();
                while ($current->lte($periodEnd)) {
                    // Skip days outside this assignment's own validity window.
                    if ($current->lt($assignmentStart)) {
                        $current->addDay();

                        continue;
                    }

                    if ($assignmentEnd && $current->gt($assignmentEnd)) {
                        $current->addDay();

                        continue;
                    }

                    // Assignments are ordered newest-first, so when two rows
                    // cover the same employee+date (e.g. legacy imports that
                    // left more than one open row), the most recent one wins
                    // and the duplicate is skipped. schedule_entries has a
                    // unique (schedule_period_id, employee_id, date) index, so
                    // this also prevents the whole month from failing on a
                    // duplicate key while preserving mid-month transfer
                    // coverage (non-overlapping windows keep their own days).
                    $entryKey = $assignment->employee_id.'|'.$current->format('Y-m-d');

                    if (isset($covered[$entryKey])) {
                        $current->addDay();

                        continue;
                    }

                    $covered[$entryKey] = true;

                    $isWork = $this->rotationEngine->isWorkDay($rotation, $group, $current);

                    $entries[] = [
                        'schedule_period_id' => $period->id,
                        'employee_id' => $assignment->employee_id,
                        'duty_category_id' => $dutyCategoryId,
                        'date' => $current->format('Y-m-d'),
                        'day_status' => $isWork ? 'WORK' : 'REST',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $current->addDay();
                }
            }

            foreach (array_chunk($entries, 500) as $chunk) {
                ScheduleEntry::insert($chunk);
            }

            $this->auditService->logCreated('SchedulePeriod', $period->id, [
                'year' => $year,
                'month' => $month,
                'entries_count' => count($entries),
            ]);

            return $period->fresh();
        });
    }

    /**
     * Publish a draft schedule.
     *
     * @throws \RuntimeException
     */
    public function publishSchedule(int $periodId): SchedulePeriod
    {
        $period = SchedulePeriod::findOrFail($periodId);

        if ($period->status !== 'draft') {
            throw new \RuntimeException(__('shifts.schedule_not_draft'));
        }

        $period->update([
            'status' => 'published',
            'published_by' => auth()->id(),
            'published_at' => now(),
        ]);

        $this->auditService->logPublished('SchedulePeriod', $period->id, [
            'published_by' => auth()->id(),
        ]);

        return $period->fresh();
    }

    /**
     * Regenerate a schedule (creates new version).
     *
     * @throws \RuntimeException
     */
    public function regenerateSchedule(int $periodId): SchedulePeriod
    {
        $oldPeriod = SchedulePeriod::findOrFail($periodId);

        if ($oldPeriod->status !== 'published') {
            throw new \RuntimeException(__('shifts.schedule_not_published'));
        }

        $oldPeriod->update(['status' => 'archived']);

        $this->auditService->logRegenerated('SchedulePeriod', $oldPeriod->id, [
            'old_version' => $oldPeriod->schedule_version,
        ], []);

        $newPeriod = $this->generateMonthlySchedule(
            $oldPeriod->year,
            $oldPeriod->month
        );

        $newPeriod->update([
            'schedule_version' => $oldPeriod->schedule_version + 1,
        ]);

        $this->auditService->logCreated('SchedulePeriod', $newPeriod->id, [
            'year' => $oldPeriod->year,
            'month' => $oldPeriod->month,
            'version' => $newPeriod->schedule_version,
        ]);

        return $newPeriod->fresh();
    }
}
