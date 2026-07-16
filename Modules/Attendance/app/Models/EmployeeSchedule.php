<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

/**
 * EmployeeSchedule — assigns a shift to an individual employee for a date range.
 */
class EmployeeSchedule extends Model
{
    use HasFactory;

    protected $table = 'att_attschedule';

    protected $fillable = [
        'employee_id',
        'shift_id',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'shift_id');
    }
}
