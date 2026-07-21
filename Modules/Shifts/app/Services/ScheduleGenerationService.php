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

            // Get all active rotation assignments for the period
            $assignments = $this->rotationAssignmentRepository->getAssignmentsForDate($periodStart->toDateString());

            $entries = [];

            foreach ($assignments as $assignment) {
                $rotation = $assignment->rotation;
                $group = $assignment->rotationGroup;

                $dutyCategoryId = $group->timeSchedule?->category?->id
                    ?? $group->timeSchedule?->categoryTimeSchedule?->shift_category_id;

                $current = $periodStart->copy();
                while ($current->lte($periodEnd)) {
                    $isWork = $this->rotationEngine->isWorkDay($rotation, $group->group_index, $current);

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
