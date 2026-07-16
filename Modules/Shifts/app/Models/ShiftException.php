<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Companies\Models\Company;
use Modules\Users\Models\User;

/**
 * ShiftException — isolated interceptor row for leaves / missions / swaps.
 *
 * A single active row overlapping a target date makes the ScheduleResolver
 * short-circuit with status `leave_excused` (or `swap`) so the employee is
 * never flagged absent. This table is intentionally decoupled from the
 * Vacations module; the Vacations lifecycle mirrors into it via a listener.
 */
class ShiftException extends Model
{
    use HasFactory;

    protected $table = 'att_shift_exceptions';

    protected $fillable = [
        'company_id',
        'employee_id',
        'exception_type',
        'source',
        'source_id',
        'from_date',
        'to_date',
        'status',
        'reason',
        'timezone',
        'created_by',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: only rows whose date range overlaps the supplied date.
     */
    public function scopeOverlapping(Builder $query, string $date): Builder
    {
        return $query->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * True when this exception should bypass absence for the target date.
     */
    public function intercepts(): bool
    {
        return $this->status === 'active'
            && in_array($this->exception_type, ['leave', 'mission', 'swap', 'training'], true);
    }
}
