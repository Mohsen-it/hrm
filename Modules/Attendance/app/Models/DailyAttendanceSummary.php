<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shifts\Models\Shift;
use Modules\Users\Models\User;

/**
 * DailyAttendanceSummary — single roll-up row per employee per calendar date.
 *
 * Aggregates the timing totals (work / late / early-leave / overtime) of every
 * attendance session that the user produced on the given day. The
 * (user_id, summary_date) pair is enforced unique by the database schema so
 * the summary can be safely rebuilt with `updateOrInsert`.
 */
class DailyAttendanceSummary extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'daily_attendance_summaries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'shift_id', 'summary_date',
        'status', 'session_type',
        'first_check_in_at', 'last_check_out_at',
        'expected_check_in', 'expected_check_out',
        'sessions_count', 'is_first_punch', 'is_complete',
        'total_work_minutes', 'total_break_minutes',
        'total_overtime_minutes', 'late_minutes', 'early_leave_minutes',
        'notes', 'calculated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'first_check_in_at' => 'datetime',
            'last_check_out_at' => 'datetime',
            'expected_check_in' => 'string',
            'expected_check_out' => 'string',
            'sessions_count' => 'integer',
            'is_first_punch' => 'boolean',
            'is_complete' => 'boolean',
            'total_work_minutes' => 'integer',
            'total_break_minutes' => 'integer',
            'total_overtime_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'calculated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user this summary belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the shift that scheduled the day.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope a query to summaries on a given date.
     */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->where('summary_date', $date);
    }

    /**
     * Scope a query to summaries within a date range (inclusive).
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('summary_date', [$from, $to]);
    }

    /**
     * Scope a query to summaries for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
