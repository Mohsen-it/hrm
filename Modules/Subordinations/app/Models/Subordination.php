<?php

namespace Modules\Subordinations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Users\Models\User;

/**
 * Subordination — administrative or geographic subordination (e.g., an
 * airport) that an employee can be attached to.
 *
 * Soft-deleted rows are kept for audit purposes; deleting a record
 * nullifies the `users.subordination_id` column for any attached
 * employees (via the FK's `ON DELETE SET NULL`).
 */
class Subordination extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'subordinations';

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'description',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Employees attached to this subordination.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'subordination_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope a query to only active subordinations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query ordered by `sort_order` then `name_ar`.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name_ar');
    }

    // ------------------------------------------------------------------
    // Accessors / helpers
    // ------------------------------------------------------------------

    /**
     * Human-friendly display name (Arabic preferred, English fallback, code last).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name_ar ?: ($this->name_en ?: $this->code);
    }

    /**
     * Find a subordination by its logical code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
