<?php

namespace Modules\Attendance\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\ShiftDetail;
use Modules\Attendance\Repositories\AttendanceShiftRepository;

/**
 * AttendanceShiftService — manages attendance shifts and their daily details.
 */
class AttendanceShiftService
{
    public function __construct(
        private AttendanceShiftRepository $shiftRepository,
    ) {}

    public function createShift(array $data): AttendanceShift
    {
        return DB::transaction(function () use ($data) {
            $shift = $this->shiftRepository->create($data);

            if (! empty($data['details'])) {
                foreach ($data['details'] as $detail) {
                    $shift->details()->create([
                        'time_interval_id' => $detail['time_interval_id'],
                        'day_index' => $detail['day_index'],
                        'in_time' => $detail['in_time'],
                        'out_time' => $detail['out_time'],
                    ]);
                }
            }

            return $shift->load('details');
        });
    }

    public function updateShift(AttendanceShift $shift, array $data): AttendanceShift
    {
        $shift = $this->shiftRepository->update($shift, $data);

        if (isset($data['details'])) {
            $shift->details()->delete();

            foreach ($data['details'] as $detail) {
                $shift->details()->create([
                    'time_interval_id' => $detail['time_interval_id'],
                    'day_index' => $detail['day_index'],
                    'in_time' => $detail['in_time'],
                    'out_time' => $detail['out_time'],
                ]);
            }
        }

        return $shift->load('details');
    }

    public function deleteShift(AttendanceShift $shift): bool
    {
        $hasActiveSchedules = $shift->schedules()
            ->where('end_date', '>=', now()->toDateString())
            ->exists();

        if ($hasActiveSchedules) {
            throw new InvalidArgumentException('لا يمكن حذف المناوبة لارتباطها بجداول نشطة.');
        }

        return $this->shiftRepository->delete($shift);
    }

    public function getShiftsByCompany(int $companyId): Collection
    {
        return $this->shiftRepository->getByCompany($companyId);
    }

    public function getAllShifts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->shiftRepository->getAll($filters, $perPage);
    }

    public function getShiftWithDetails(int $shiftId): AttendanceShift
    {
        $shift = $this->shiftRepository->findById($shiftId);

        if (! $shift) {
            throw new InvalidArgumentException('المناوبة غير موجودة.');
        }

        return $shift;
    }

    public function createShiftDetail(array $data): ShiftDetail
    {
        return ShiftDetail::create($data);
    }
}
