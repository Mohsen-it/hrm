<?php

namespace Modules\Vacations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Users\Models\User;

/**
 * UserVacationBalance — entitlement ledger for one (user, type, year) triple.
 *
 * The unique index `{user_id, vacation_type_id, year}` is enforced at the
 * DB level so the service can call `updateOrCreate` safely when the
 * year-end carry job is running.
 */
class UserVacationBalance extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_vacation_balances';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'vacation_type_id', 'year',
        'days_entitled', 'days_used', 'days_pending', 'days_carried_over', 'days_adjustment',
        'period_start', 'period_end', 'last_recalculated_at', 'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'days_entitled' => 'integer',
            'days_used' => 'integer',
            'days_pending' => 'integer',
            'days_carried_over' => 'integer',
            'days_adjustment' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'last_recalculated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this balance row.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the vacation type this balance tracks.
     *
     * @return BelongsTo<VacationType, $this>
     */
    public function vacationType(): BelongsTo
    {
        return $this->belongsTo(VacationType::class, 'vacation_type_id');
    }

    /**
     * Get the per-transaction audit rows for this balance.
     *
     * @return HasMany<UserVacationBalanceTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(UserVacationBalanceTransaction::class, 'balance_id');
    }

    /**
     * Vacation requests that referenced this balance.
     *
     * @return HasMany<UserVacationRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(UserVacationRequest::class, 'balance_id');
    }

    // ------------------------------------------------------------------
    // Computed helpers
    // ------------------------------------------------------------------

    /**
     * Compute the remaining days for this balance.
     */
    public function daysRemaining(): int
    {
        return (int) $this->days_entitled
            + (int) $this->days_carried_over
            + (int) $this->days_adjustment
            - (int) $this->days_used
            - (int) $this->days_pending;
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope to a specific year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to balances of a specific type.
     */
    public function scopeForType(Builder $query, int $typeId): Builder
    {
        return $query->where('vacation_type_id', $typeId);
    }

    /**
     * Scope to balances for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
