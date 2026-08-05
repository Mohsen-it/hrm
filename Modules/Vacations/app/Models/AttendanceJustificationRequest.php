<?php

namespace Modules\Vacations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Users\Models\User;

/** Immutable attendance exception request with the schedule values used to calculate it. */
class AttendanceJustificationRequest extends Model
{
    use SoftDeletes;

    protected $table = 'attendance_justification_requests';

    protected $fillable = ['user_id', 'attendance_date', 'arrival_time', 'missing_check_in', 'missing_check_out', 'late_arrival', 'late_minutes', 'expected_check_in', 'check_in_deadline', 'grace_minutes', 'rotation_id', 'rotation_group_id', 'reason', 'schedule_snapshot', 'requested_at'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'missing_check_in' => 'boolean', 'missing_check_out' => 'boolean', 'late_arrival' => 'boolean', 'late_minutes' => 'integer', 'grace_minutes' => 'integer', 'schedule_snapshot' => 'array', 'requested_at' => 'datetime'];
    }

    /** Employee submitting the explanation. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
