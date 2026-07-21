<?php

namespace Modules\Shifts\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Shifts\Models\TimeSchedule;
use Modules\Shifts\Models\TimeScheduleBreak;
use Modules\Shifts\Repositories\TimeScheduleRepository;
use Modules\Shifts\Services\Traits\ResolvesCompanyId;

class TimeScheduleService
{
    use ResolvesCompanyId;

    public function __construct(
        private TimeScheduleRepository $repository,
        private TimeScheduleValidationService $validationService
    ) {}

    /**
     * Get all time schedules with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get all time schedules as a simple collection (no pagination).
     * Intended for dropdowns and selects.
     *
     * @return Collection<int, TimeSchedule>
     */
    public function getList(): Collection
    {
        return $this->repository->getList();
    }

    /**
     * Find a time schedule by its primary key.
     */
    public function getById(int $id): ?TimeSchedule
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new time schedule.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): TimeSchedule
    {
        return DB::transaction(function () use ($data) {
            $validated = $this->validationService->validateCreate($data);

            $breaks = $data['breaks'] ?? [];
            unset($data['breaks']);

            if (empty($validated['company_id'])) {
                $validated['company_id'] = $this->resolveCompanyId();
            }

            $schedule = $this->repository->create($validated);

            foreach ($breaks as $break) {
                TimeScheduleBreak::create([
                    'schedule_id' => $schedule->id,
                    'break_start' => $break['break_start'] ?? null,
                    'break_end' => $break['break_end'] ?? null,
                    'duration' => $break['duration'] ?? null,
                ]);
            }

            return $schedule;
        });
    }

    /**
     * Update the given time schedule.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(int $id, array $data): TimeSchedule
    {
        $schedule = $this->repository->findById($id);

        $validated = $this->validationService->validateUpdate($schedule, $data);

        $breaks = $data['breaks'] ?? null;

        $schedule = $this->repository->update($schedule, $validated);

        if ($breaks !== null) {
            $schedule->breaks()->delete();

            foreach ($breaks as $break) {
                TimeScheduleBreak::create([
                    'schedule_id' => $schedule->id,
                    'break_start' => $break['break_start'] ?? null,
                    'break_end' => $break['break_end'] ?? null,
                    'duration' => $break['duration'] ?? null,
                ]);
            }
        }

        return $this->repository->findById($schedule->id);
    }

    /**
     * Delete the given time schedule.
     *
     * @throws ValidationException
     */
    public function delete(int $id): bool
    {
        if ($this->repository->isLinkedToCategory($id)) {
            throw ValidationException::withMessages([
                'id' => [__('shifts.schedule_linked_to_category')],
            ]);
        }

        $schedule = $this->repository->findById($id);

        return $this->repository->delete($schedule);
    }

    /**
     * Copy the given time schedule with a new name.
     */
    public function copy(int $id, ?string $newName = null): TimeSchedule
    {
        $original = $this->repository->findById($id);

        $scheduleName = $newName ?: ($original->name.' (نسخة)');

        $newSchedule = $this->repository->create([
            'company_id' => $original->company_id,
            'name' => $scheduleName,
            'in_time' => $original->in_time,
            'out_time' => $original->out_time,
            'is_multi_day' => $original->is_multi_day,
            'late_margin' => $original->late_margin,
            'early_margin' => $original->early_margin,
            'in_ahead_margin' => $original->in_ahead_margin,
            'in_above_margin' => $original->in_above_margin,
            'out_ahead_margin' => $original->out_ahead_margin,
            'out_above_margin' => $original->out_above_margin,
        ]);

        foreach ($original->breaks as $break) {
            TimeScheduleBreak::create([
                'schedule_id' => $newSchedule->id,
                'break_start' => $break->break_start,
                'break_end' => $break->break_end,
                'duration' => $break->duration,
            ]);
        }

        return $newSchedule;
    }
}
