<?php

namespace Modules\FingerprintDevices\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FingerprintDeviceType — catalog entry for a fingerprint device model.
 */
class FingerprintDeviceType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fingerprint_device_types';

    protected $fillable = [
        'name', 'manufacturer', 'protocol', 'sdk_version',
        'default_port', 'supports_fingerprint', 'supports_face',
        'max_fingerprints', 'max_users', 'connection_params',
        'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_port' => 'integer',
            'supports_fingerprint' => 'boolean',
            'supports_face' => 'boolean',
            'max_fingerprints' => 'integer',
            'max_users' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(FingerprintDevice::class, 'device_type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByManufacturer(Builder $query, string $manufacturer): Builder
    {
        return $query->where('manufacturer', $manufacturer);
    }
}
