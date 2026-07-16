<?php

namespace Modules\FingerprintDevices\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Models\Branch;

/**
 * FingerprintDevice — a physical fingerprint attendance terminal.
 */
class FingerprintDevice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fingerprint_devices';

    protected $fillable = [
        'device_type_id', 'branch_id', 'name', 'serial_number',
        'ip_address', 'port', 'comm_key', 'timezone', 'connection_type',
        'timeout', 'status', 'last_seen_at', 'last_synced_at',
        'capabilities', 'user_count', 'fingerprint_count',
        'attendance_log_count', 'notes', 'is_push_enabled', 'push_url',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'device_type_id' => 'integer',
            'branch_id' => 'integer',
            'port' => 'integer',
            'comm_key' => 'integer',
            'timeout' => 'integer',
            'last_seen_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'capabilities' => 'array',
            'user_count' => 'integer',
            'fingerprint_count' => 'integer',
            'attendance_log_count' => 'integer',
            'is_push_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(FingerprintDeviceType::class, 'device_type_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function fingerprints(): HasMany
    {
        return $this->hasMany(UserFingerprint::class, 'device_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'deactivated');
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('status', 'online');
    }

    public function scopeOffline(Builder $query): Builder
    {
        return $query->where('status', 'offline');
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function isOffline(): bool
    {
        return $this->status === 'offline';
    }
}
