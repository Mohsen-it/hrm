<?php

namespace Modules\Shifts\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Companies\Models\Company;

class Rotation extends Model
{
    use HasFactory;

    protected $table = 'att_rotations';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'anchor_start_date',
        'pattern',
        'cycle_length',
        'work_days_count',
        'rest_days_count',
        'number_of_groups',
        'overtime_enabled',
        'work_on_holidays',
        'grace_minutes',
        'color',
    ];

    protected $casts = [
        'pattern' => 'array',
        'anchor_start_date' => 'date',
        'cycle_length' => 'integer',
        'work_days_count' => 'integer',
        'rest_days_count' => 'integer',
        'number_of_groups' => 'integer',
        'overtime_enabled' => 'boolean',
        'work_on_holidays' => 'boolean',
        'grace_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(RotationGroup::class, 'rotation_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RotationAssignment::class, 'rotation_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('end_date');
    }

    public function scopeByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Determine if a specific day position in the cycle is a work day.
     */
    public function isWorkDayAtPosition(int $positionInCycle): bool
    {
        $pattern = $this->pattern;

        if (! is_array($pattern) || $this->cycle_length <= 0) {
            return false;
        }

        $normalizedPosition = $positionInCycle % $this->cycle_length;

        return ($pattern[$normalizedPosition] ?? 0) == 1;
    }

    /**
     * Get the work pattern for a specific group on a given date.
     */
    public function resolveForDate(Carbon $date, int $groupIndex): bool
    {
        $daysSinceAnchor = (int) $date->startOfDay()->diffInDays($this->anchor_start_date->startOfDay());
        $positionInCycle = ($daysSinceAnchor + $groupIndex) % $this->cycle_length;

        return $this->isWorkDayAtPosition($positionInCycle);
    }
}
