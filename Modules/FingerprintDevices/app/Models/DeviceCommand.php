<?php

namespace Modules\FingerprintDevices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommand extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const TYPE_USER_CREATE = 'user_create';

    public const TYPE_USER_UPDATE = 'user_update';

    public const TYPE_USER_DELETE = 'user_delete';

    public const TYPE_BIODATA = 'biodata';

    public const TYPE_FP_TEMPLATE = 'fp_template';

    public const TYPE_FACE_TEMPLATE = 'face_template';

    public const TYPE_PHOTO = 'photo';

    public const TYPE_CARD = 'card';

    public const TYPE_TIME_SYNC = 'time_sync';

    public const TYPE_RESTART = 'restart';

    public const TYPE_CLEAR_LOGS = 'clear_logs';

    public const TYPE_CLEAR_USERS = 'clear_users';

    public const TYPE_REFRESH_CONFIG = 'refresh_config';

    protected $table = 'device_commands';

    protected $fillable = [
        'device_id',
        'command_type',
        'command_body',
        'status',
        'priority',
        'retry_count',
        'max_retries',
        'error_message',
        'correlation_id',
        'sent_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'priority' => 'integer',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForDevice($query, int $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function markSending(): bool
    {
        return $this->update([
            'status' => self::STATUS_SENDING,
            'sent_at' => now(),
        ]);
    }

    public function markCompleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $message = ''): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    public function incrementRetry(string $errorMessage = ''): bool
    {
        $this->increment('retry_count');
        $this->update([
            'error_message' => $errorMessage,
        ]);

        return $this->retry_count < $this->max_retries;
    }

    public function isRetryable(): bool
    {
        return $this->status === self::STATUS_FAILED
            && $this->retry_count < $this->max_retries;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
