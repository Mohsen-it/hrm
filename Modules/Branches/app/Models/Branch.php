<?php

namespace Modules\Branches\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Companies\Models\Company;
use Modules\Departments\Models\Department;
use Modules\Zones\Models\Zone;

class Branch extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'branches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'branch_code',
        'branch_name',
        'email',
        'phone',
        'address',
        'address2',
        'city',
        'country',
        'state',
        'postal_code',
        'manager_name',
        'manager_phone',
        'description',
        'is_main',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_main' => 'boolean',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the company that owns the branch.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the departments for the branch.
     *
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'branch_id');
    }

    /**
     * Get the zones assigned to the branch.
     *
     * @return BelongsToMany<Zone, $this>
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'zone_branches', 'branch_id', 'zone_id')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active branches.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include main branches.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    /**
     * Determine if the branch is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
