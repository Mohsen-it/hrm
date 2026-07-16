<?php

namespace Modules\Vacations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

/**
 * UserVacationBalanceTransaction — audit row for a single change to a balance.
 *
 * Inserted by the `VacationBalanceService` whenever a grant, request
 * approval, carry-over, manual adjustment or year-end reset modifies a
 * balance. The `days_delta` is signed: positive when the balance grew,
 * negative when it shrank.
 */
class UserVacationBalanceTransaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_vacation_balance_transactions';

    /**
     * Disable updated_at — these rows are append-only.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'balance_id', 'user_id', 'vacation_type_id',
        'type', 'days_delta', 'balance_after',
        'reference_type', 'reference_id', 'notes', 'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_delta' => 'integer',
            'balance_after' => 'integer',
            'reference_id' => 'integer',
            'created_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model — stamp the creation timestamp.
     */
    protected static function booted(): void
    {
        static::creating(function (self $tx): void {
            $tx->created_at = $tx->created_at ?? now();
        });
    }

    /**
     * Get the balance row this transaction belongs to.
     *
     * @return BelongsTo<UserVacationBalance, $this>
     */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(UserVacationBalance::class, 'balance_id');
    }

    /**
     * Get the user this transaction was logged for.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the vacation type the transaction applies to.
     *
     * @return BelongsTo<VacationType, $this>
     */
    public function vacationType(): BelongsTo
    {
        return $this->belongsTo(VacationType::class, 'vacation_type_id');
    }

    /**
     * Get the user that recorded the transaction (operator).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
