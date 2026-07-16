<?php

namespace Modules\FingerprintDevices\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Users\Models\User;

/**
 * FingerprintTemplate — the canonical template row stored in `user_fingerprints`.
 *
 * The class is a thin alias of {@see UserFingerprint} that targets the same
 * table. It exists so the admin module can refer to "templates" without
 * conflating the row with the per-user view exposed by the rest of the app.
 */
class FingerprintTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'user_fingerprints';

    protected $fillable = [
        'user_id', 'device_id', 'finger_id', 'template_data',
        'template_format', 'template_version', 'quality',
        'is_master', 'captured_at', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'device_id' => 'integer',
            'finger_id' => 'integer',
            'template_version' => 'integer',
            'quality' => 'integer',
            'is_master' => 'boolean',
            'captured_at' => 'datetime',
            'synced_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }

    public function scopeMaster(Builder $query): Builder
    {
        return $query->where('is_master', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }
}
