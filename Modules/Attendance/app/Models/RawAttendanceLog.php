<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\Users\Models\User;

/**
 * RawAttendanceLog — a single raw punch as received from a device / queue.
 *
 * Raw logs are the source of truth feeding the reconciliation pipeline: they
 * are eventually correlated into `AttendanceSession` records. The `processed`
 * flag allows incremental jobs to skip rows that have already been handled.
 */
class RawAttendanceLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'raw_attendance_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'device_id', 'device_user_id',
        'punch_time', 'punch_type', 'verify_type',
        'work_code', 'source',
        'processed', 'processed_at',
        'ip_address', 'raw_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'punch_time' => 'datetime',
            'work_code' => 'integer',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
            'raw_data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Get the user this raw log was matched to (if any).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the device this raw log came from.
     *
     * @return BelongsTo<FingerprintDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }

    /**
     * Get the sessions derived from this raw log.
     *
     * @return HasMany<AttendanceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'raw_log_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope a query to logs that have not been processed yet.
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('processed', false);
    }

    /**
     * Scope a query to logs that have been processed.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('processed', true);
    }

    /**
     * Scope a query to logs for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to logs for a specific device.
     */
    public function scopeForDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope a query to logs within a date-time range.
     */
    public function scopePunchedBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('punch_time', [$from, $to]);
    }

    // ------------------------------------------------------------------
    // Convenience helpers
    // ------------------------------------------------------------------

    /**
     * Mark the raw log as processed at the given time (defaults to now).
     */
    public function markProcessed(?\DateTimeInterface $at = null): bool
    {
        return $this->forceFill([
            'processed' => true,
            'processed_at' => $at ?? now(),
        ])->save();
    }
}
