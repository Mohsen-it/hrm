<?php

namespace Modules\Vacations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Users\Models\User;

/**
 * UserVacationRequest — lifecycle row for a single employee vacation request.
 *
 * Statuses flow: pending -> approved | rejected | cancelled.
 * Only the owning employee can `cancel` a request that is still `pending`.
 * The integration with the attendance module is handled by
 * `VacationIntegrationService` via the `VacationApproved` / `VacationRejected`
 * events.
 */
class UserVacationRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_vacation_requests';

    /**
     * The possible status values for a request.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'vacation_type_id', 'manager_id', 'balance_id',
        'start_date', 'end_date', 'days_count', 'working_days_count',
        'status', 'reason', 'manager_note',
        'requested_at', 'decided_at', 'cancelled_at',
        'attachments', 'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days_count' => 'integer',
            'working_days_count' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attachments' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the employee this request belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the manager expected to approve the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get the vacation type that was requested.
     *
     * @return BelongsTo<VacationType, $this>
     */
    public function vacationType(): BelongsTo
    {
        return $this->belongsTo(VacationType::class, 'vacation_type_id');
    }

    /**
     * Get the balance row that was debited / reserved by this request.
     *
     * @return BelongsTo<UserVacationBalance, $this>
     */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(UserVacationBalance::class, 'balance_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope a query to requests in `pending` state.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to requests in `approved` state.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope a query to requests owned by the supplied user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to requests awaiting a decision by the supplied user.
     */
    public function scopeForApprover(Builder $query, int $approverId): Builder
    {
        return $query->where(function (Builder $q) use ($approverId): void {
            $q->where('manager_id', $approverId)
                ->orWhereIn('user_id', function ($sub) use ($approverId): void {
                    $sub->select('id')->from('users')->where('manager_id', $approverId);
                });
        });
    }

    /**
     * Scope to requests that overlap the supplied date range.
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from);
    }

    // ------------------------------------------------------------------
    // Status helpers
    // ------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
