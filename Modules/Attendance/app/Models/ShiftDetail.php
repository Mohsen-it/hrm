<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ShiftDetail — defines the daily time slots for a shift.
 */
class ShiftDetail extends Model
{
    use HasFactory;

    protected $table = 'att_shiftdetail';

    protected $fillable = [
        'shift_id',
        'time_interval_id',
        'day_index',
        'in_time',
        'out_time',
    ];

    protected function casts(): array
    {
        return [
            'day_index' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'shift_id');
    }

    public function timeInterval(): BelongsTo
    {
        return $this->belongsTo(TimeInterval::class, 'time_interval_id');
    }
}
