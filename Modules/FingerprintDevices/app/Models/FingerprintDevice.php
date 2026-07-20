<?php

namespace Modules\FingerprintDevices\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Modules\Subordinations\Models\Subordination;

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
        'timeout', 'status', 'last_seen_at', 'last_synced_at', 'last_pushed_at',
        'sync_log_count', 'capabilities', 'user_count', 'fingerprint_count',
        'attendance_log_count', 'notes', 'is_push_enabled', 'push_url',
        'api_token',
        'default_company_id', 'default_branch_id', 'default_subordination_id',
    ];

    protected function casts(): array
    {
        return [
            'device_type_id' => 'integer',
            'branch_id' => 'integer',
            'default_company_id' => 'integer',
            'default_branch_id' => 'integer',
            'default_subordination_id' => 'integer',
            'port' => 'integer',
            'comm_key' => 'string',
            'timeout' => 'integer',
            'last_seen_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_pushed_at' => 'datetime',
            'sync_log_count' => 'integer',
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

    public function defaultCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'default_company_id');
    }

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function defaultSubordination(): BelongsTo
    {
        return $this->belongsTo(Subordination::class, 'default_subordination_id');
    }

    public function fingerprints(): HasMany
    {
        return $this->hasMany(UserFingerprint::class, 'device_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(DeviceSyncLog::class, 'device_id');
    }

    public function pushResults(): HasMany
    {
        return $this->hasMany(DevicePushResult::class, 'device_id');
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

    public function getCanPushUsersAttribute(): bool
    {
        $manufacturer = strtolower($this->deviceType?->manufacturer ?? '');

        return str_contains($manufacturer, 'zkteco')
            || str_contains($manufacturer, 'zk')
            || str_contains($manufacturer, 'hikvision')
            || str_contains($manufacturer, 'hik');
    }

    public function getCanPushFingerprintsAttribute(): bool
    {
        return $this->can_push_users;
    }

    public function getCanPushFacePhotosAttribute(): bool
    {
        $manufacturer = strtolower($this->deviceType?->manufacturer ?? '');

        return str_contains($manufacturer, 'hikvision') || str_contains($manufacturer, 'hik');
    }

    public function getLastPushedAtHumanAttribute(): ?string
    {
        return $this->last_pushed_at?->diffForHumans();
    }

    public function getLastSyncLogAttribute(): ?DeviceSyncLog
    {
        if (! array_key_exists('lastSyncLogCached', $this->attributes)) {
            $this->attributes['lastSyncLogCached'] = $this->syncLogs()
                ->latest('started_at')
                ->first();
        }

        return $this->attributes['lastSyncLogCached'] ?? null;
    }
}
