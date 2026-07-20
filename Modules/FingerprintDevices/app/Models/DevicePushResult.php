<?php

namespace Modules\FingerprintDevices\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Users\Models\User;

/**
 * DevicePushResult — per-record outcome of a push attempt (user, fingerprint, face_photo).
 */
class DevicePushResult extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'device_push_results';

    protected $fillable = [
        'sync_log_id', 'device_id', 'record_type', 'target_user_id',
        'target_finger_id', 'device_uid', 'status', 'error_message',
        'attempted_at', 'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'sync_log_id' => 'integer',
            'device_id' => 'integer',
            'target_user_id' => 'integer',
            'target_finger_id' => 'integer',
            'device_uid' => 'integer',
            'retry_count' => 'integer',
            'attempted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(DeviceSyncLog::class, 'sync_log_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeForDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('record_type', $type);
    }
}
