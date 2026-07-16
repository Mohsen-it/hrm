<?php

namespace Modules\Companies\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Branches\Models\Branch;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'companies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_code',
        'company_name',
        'email',
        'phone',
        'address',
        'address2',
        'city',
        'country',
        'state',
        'postal_code',
        'website',
        'logo',
        'description',
        'established_date',
        'tax_number',
        'commercial_number',
        'is_default',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'established_date' => 'date',
        'is_default' => 'boolean',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the branches for the company.
     *
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'company_id');
    }

    /**
     * Scope a query to only include active companies.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include default companies.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the full URL for the company logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return Storage::disk('public')->url($this->logo);
    }

    /**
     * Determine if the company is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 1;
    }
}
