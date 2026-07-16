<?php

namespace Modules\Shifts\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Modules\Companies\Models\Company;

class ShiftCategory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'att_shift_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'type',
        'work_days',
        'rest_days',
        'work_days_json',
        'weekend_days_json',
        'required_hours',
        'period_type',
        'overtime_enabled',
        'fingerprint_enabled',
        'work_on_holidays',
        'work_on_weekends',
        'color',
        'anchor_start_date',
        'cycle_length',
        'is_dynamic',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'work_days_json' => 'array',
        'weekend_days_json' => 'array',
        'overtime_enabled' => 'boolean',
        'fingerprint_enabled' => 'boolean',
        'work_on_holidays' => 'boolean',
        'work_on_weekends' => 'boolean',
        'anchor_start_date' => 'date',
        'cycle_length' => 'integer',
        'is_dynamic' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the company that owns the shift category.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the category-time-schedule pivot relation.
     *
     * @return HasOne<CategoryTimeSchedule, $this>
     */
    public function categoryTimeSchedule(): HasOne
    {
        return $this->hasOne(CategoryTimeSchedule::class, 'shift_category_id');
    }

    /**
     * Get the time schedule through the category-time-schedule pivot.
     *
     * @return HasOneThrough<TimeSchedule, CategoryTimeSchedule, $this>
     */
    public function timeSchedule(): HasOneThrough
    {
        return $this->hasOneThrough(
            TimeSchedule::class,
            CategoryTimeSchedule::class,
            'shift_category_id',
            'id',
            'id',
            'time_schedule_id'
        );
    }

    /**
     * Get the employees assigned to this shift category.
     *
     * @return HasMany<EmployeeShiftCategory, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(EmployeeShiftCategory::class, 'shift_category_id');
    }

    /**
     * Scope a query to filter by company.
     *
     * @param  Builder  $query
     * @param  int  $companyId
     * @return Builder
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to filter by type.
     *
     * @param  Builder  $query
     * @param  string  $type
     * @return Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include cyclic shift categories.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeCyclic($query)
    {
        return $query->where('type', 'cyclic');
    }

    /**
     * Scope a query to only include dynamic-engine shift categories.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeDynamic($query)
    {
        return $query->where('is_dynamic', true);
    }

    /**
     * Resolve the cycle length for the dynamic engine.
     *
     * Uses the denormalised column when present, otherwise derives it from
     * the work/rest day counts. Always returns an int (0 when undefined).
     */
    public function cycleLength(): int
    {
        if (! is_null($this->cycle_length) && $this->cycle_length > 0) {
            return (int) $this->cycle_length;
        }

        $work = (int) ($this->work_days ?? 0);
        $rest = (int) ($this->rest_days ?? 0);

        return $work + $rest;
    }

    /**
     * Resolve the cycle anchor date for the dynamic engine.
     *
     * Falls back to the earliest active assignment start date when the
     * category has no explicit anchor configured.
     */
    public function cycleAnchor(): ?Carbon
    {
        if ($this->anchor_start_date) {
            return Carbon::parse($this->anchor_start_date)->startOfDay();
        }

        return null;
    }

    /**
     * Scope a query to only include weekly shift categories.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeWeekly($query)
    {
        return $query->where('type', 'weekly');
    }

    /**
     * Scope a query to only include hours shift categories.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeHours($query)
    {
        return $query->where('type', 'hours');
    }
}
