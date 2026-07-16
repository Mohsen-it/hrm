<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\Shifts\Models\Shift;
use Modules\Users\Models\User;

/**
 * AttendanceSession — a single check-in/check-out pair for an employee.
 *
 * A user may produce several sessions on the same day (e.g. split shift,
 * make-up time); each session records the actual punches, expected slots
 * (derived from the assigned shift), and the computed timing penalties
 * (late / early leave / overtime).
 */
class AttendanceSession extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attendance_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'shift_id', 'device_id', 'raw_log_id', 'zone_id',
        'attendance_date', 'check_in_at', 'check_out_at',
        'expected_check_in', 'expected_check_out',
        'status', 'session_type', 'source',
        'work_minutes', 'break_minutes', 'late_minutes',
        'early_leave_minutes', 'overtime_minutes',
        'ip_address', 'metadata', 'notes', 'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'expected_check_in' => 'string',
            'expected_check_out' => 'string',
            'work_minutes' => 'integer',
            'break_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the user (employee) this session belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the shift that scheduled this session.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Get the user that recorded the session manually.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the originating raw attendance log when available.
     *
     * @return BelongsTo<RawAttendanceLog, $this>
     */
    public function rawLog(): BelongsTo
    {
        return $this->belongsTo(RawAttendanceLog::class, 'raw_log_id');
    }

    /**
     * Get the fingerprint device that recorded this session.
     *
     * @return BelongsTo<FingerprintDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope a query to sessions on a given date.
     */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->where('attendance_date', $date);
    }

    /**
     * Scope a query to sessions within a date range (inclusive).
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('attendance_date', [$from, $to]);
    }

    /**
     * Scope a query to sessions that are still open (no check-out yet).
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('check_out_at');
    }

    /**
     * Scope a query to sessions for a specific user.
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

    // ------------------------------------------------------------------
    // Convenience helpers
    // ------------------------------------------------------------------

    /**
     * Determine whether the session already has a check-out punch.
     */
    public function isComplete(): bool
    {
        return $this->check_in_at !== null && $this->check_out_at !== null;
    }

    /**
     * Determine whether the session is still open (awaiting check-out).
     */
    public function isOpen(): bool
    {
        return $this->check_in_at !== null && $this->check_out_at === null;
    }
}
