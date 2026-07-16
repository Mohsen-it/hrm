<?php

namespace Modules\Vacations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * VacationType — catalog of vacation buckets the company offers.
 *
 * Each row governs the defaults (entitlement, approval, advance notice,
 * attachment) that apply when an employee opens a request of this type.
 * The model is read-mostly at runtime; the seeders / config are the
 * typical source of new rows.
 */
class VacationType extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vacation_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code', 'name_ar', 'name_en', 'color', 'icon',
        'default_days_per_year', 'max_days_per_request', 'max_carry_days',
        'advance_notice_days',
        'is_paid', 'requires_approval', 'requires_attachment',
        'deducts_from_balance', 'counts_weekends', 'counts_holidays',
        'is_active', 'sort_order', 'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_days_per_year' => 'integer',
            'max_days_per_request' => 'integer',
            'max_carry_days' => 'integer',
            'advance_notice_days' => 'integer',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'requires_attachment' => 'boolean',
            'deducts_from_balance' => 'boolean',
            'counts_weekends' => 'boolean',
            'counts_holidays' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get every request opened against this vacation type.
     *
     * @return HasMany<UserVacationRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(UserVacationRequest::class, 'vacation_type_id');
    }

    /**
     * Get the per-user balance rows associated with this vacation type.
     *
     * @return HasMany<UserVacationBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(UserVacationBalance::class, 'vacation_type_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope a query to active vacation types only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to paid vacation types only.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('is_paid', true);
    }

    /**
     * Scope a query to unpaid vacation types only.
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('is_paid', false);
    }

    /**
     * Order vacation types by their display order then name.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name_ar');
    }
}
