<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Users\Models\User;

/**
 * AttendanceEmployee — links an employee to an attendance group with permission flags.
 */
class AttendanceEmployee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_attemployee';

    protected $fillable = [
        'emp_id',
        'group_id',
        'enable_attendance',
        'enable_schedule',
        'enable_overtime',
        'enable_holiday',
        'enable_compensatory',
        'status',
        'ip_address',
        'create_time',
        'create_user',
        'change_time',
        'change_user',
    ];

    protected function casts(): array
    {
        return [
            'enable_attendance' => 'boolean',
            'enable_schedule' => 'boolean',
            'enable_overtime' => 'boolean',
            'enable_holiday' => 'boolean',
            'enable_compensatory' => 'boolean',
            'status' => 'integer',
            'create_time' => 'datetime',
            'change_time' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emp_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AttendanceGroup::class, 'group_id');
    }

    public function scopeInGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
