<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

class RotationAssignment extends Model
{
    use HasFactory;

    protected $table = 'att_rotation_assignments';

    protected $fillable = [
        'employee_id',
        'rotation_id',
        'rotation_group_id',
        'start_date',
        'end_date',
        'snapshot_data',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'snapshot_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function rotation(): BelongsTo
    {
        return $this->belongsTo(Rotation::class, 'rotation_id');
    }

    public function rotationGroup(): BelongsTo
    {
        return $this->belongsTo(RotationGroup::class, 'rotation_group_id');
    }

    /**
     * Scope a query to only include active assignments (no end date).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }

    /**
     * Scope a query to filter by employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope a query to filter assignments valid for a given date.
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }
}
