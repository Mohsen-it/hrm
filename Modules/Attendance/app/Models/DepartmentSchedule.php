<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Departments\Models\Department;

/**
 * DepartmentSchedule — assigns a shift to a department for a date range.
 */
class DepartmentSchedule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_departmentschedule';

    protected $fillable = [
        'department_id',
        'shift_id',
        'start_date',
        'end_date',
        'status',
        'create_time',
        'create_user',
        'change_time',
        'change_user',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'shift_id');
    }
}
