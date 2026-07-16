<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * GroupSchedule — assigns a shift to an attendance group for a date range.
 */
class GroupSchedule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_groupschedule';

    protected $fillable = [
        'group_id',
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
            'create_time' => 'datetime',
            'change_time' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AttendanceGroup::class, 'group_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'shift_id');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
