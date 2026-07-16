<?php

namespace Modules\Zones\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Modules\Users\Models\User;
use Modules\Zones\Enums\ZoneType;

/**
 * Zone — a named grouping of branches that share a geographic, operational
 * or security footprint.
 *
 * Zones own the `zone_branches` pivot (managed through {@see self::branches()})
 * and the `user_zone` pivot (managed through {@see self::users()}).
 */
class Zone extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'zones';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id', 'code', 'name_ar', 'name_en', 'zone_type',
        'city', 'region', 'country', 'latitude', 'longitude',
        'radius_meters', 'description', 'is_active',
        'branches_count', 'employees_count', 'devices_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'zone_type' => ZoneType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'is_active' => 'boolean',
            'branches_count' => 'integer',
            'employees_count' => 'integer',
            'devices_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Get the company the zone belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the branches assigned to the zone through the `zone_branches` pivot.
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'zone_branches', 'zone_id', 'branch_id')
            ->withPivot(['is_primary', 'priority', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get the users assigned to the zone.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_zone', 'zone_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope a query to only active zones.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to zones of the supplied type.
     */
    public function scopeOfType(Builder $query, ZoneType|string $type): Builder
    {
        $value = $type instanceof ZoneType ? $type->value : $type;

        return $query->where('zone_type', $value);
    }

    /**
     * Scope a query to zones belonging to the supplied company.
     */
    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        if (! $companyId) {
            return $query;
        }

        return $query->where('company_id', $companyId);
    }

    // ------------------------------------------------------------------
    // Accessors / helpers
    // ------------------------------------------------------------------

    /**
     * Localised display name (English when available, fallback to Arabic).
     */
    public function getDisplayNameAttribute(): string
    {
        if (app()->getLocale() === 'en' && $this->name_en) {
            return $this->name_en;
        }

        return $this->name_ar;
    }
}
