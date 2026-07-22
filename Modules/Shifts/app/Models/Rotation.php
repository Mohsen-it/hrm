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
        'time_schedule_id',
        'overtime_enabled',
        'work_on_holidays',
        'grace_minutes',
        'color',
        'in_ahead_margin',
        'in_above_margin',
        'out_ahead_margin',
        'out_above_margin',
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
        'in_ahead_margin' => 'datetime:H:i',
        'in_above_margin' => 'datetime:H:i',
        'out_ahead_margin' => 'datetime:H:i',
        'out_above_margin' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function timeSchedule(): BelongsTo
    {
        return $this->belongsTo(TimeSchedule::class, 'time_schedule_id');
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
     *
     * Offset is `group_index * work_days_count` so each group works a contiguous
     * block of work days and the groups tile the cycle without overlap.
     */
    public function resolveForDate(Carbon $date, int $groupIndex): bool
    {
        $daysSinceAnchor = (int) $date->startOfDay()->diffInDays($this->anchor_start_date->startOfDay());
        $workDaysCount = $this->work_days_count
            ?: count(array_filter(is_array($this->pattern) ? $this->pattern : [], fn ($v) => $v == 1));
        $offset = $groupIndex * (int) $workDaysCount;
        $positionInCycle = ($daysSinceAnchor + $offset) % $this->cycle_length;

        if ($positionInCycle < 0) {
            $positionInCycle += $this->cycle_length;
        }

        return $this->isWorkDayAtPosition($positionInCycle);
    }
}
