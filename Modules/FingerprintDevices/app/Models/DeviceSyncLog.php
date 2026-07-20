<?php

namespace Modules\FingerprintDevices\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Users\Models\User;

/**
 * DeviceSyncLog — record of a single sync operation (pull / push / bidirectional).
 */
class DeviceSyncLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'device_sync_logs';

    protected $fillable = [
        'device_id', 'user_id', 'direction', 'steps', 'totals',
        'errors', 'started_at', 'finished_at', 'duration_seconds', 'status',
    ];

    protected function casts(): array
    {
        return [
            'device_id' => 'integer',
            'user_id' => 'integer',
            'steps' => 'array',
            'totals' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_seconds' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pushResults(): HasMany
    {
        return $this->hasMany(DevicePushResult::class, 'sync_log_id');
    }

    public function scopeForDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', ['failed', 'partial']);
    }

    public function scopePushed(Builder $query): Builder
    {
        return $query->whereIn('direction', ['push', 'bidirectional']);
    }

    public function getDurationHumanAttribute(): string
    {
        $seconds = (float) $this->duration_seconds;
        if ($seconds <= 0) {
            return '—';
        }
        if ($seconds < 60) {
            return round($seconds).'s';
        }
        $m = (int) floor($seconds / 60);
        $s = (int) round($seconds - $m * 60);

        return "{$m}m {$s}s";
    }
}
