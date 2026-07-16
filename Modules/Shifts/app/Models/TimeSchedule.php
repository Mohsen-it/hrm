<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Modules\Companies\Models\Company;

class TimeSchedule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'att_time_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'in_time',
        'out_time',
        'is_multi_day',
        'late_margin',
        'early_margin',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_multi_day' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the company that owns the time schedule.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the breaks for this time schedule.
     *
     * @return HasMany<TimeScheduleBreak, $this>
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(TimeScheduleBreak::class, 'schedule_id');
    }

    /**
     * Get the category-time-schedule pivot relation.
     *
     * @return HasOne<CategoryTimeSchedule, $this>
     */
    public function categoryTimeSchedule(): HasOne
    {
        return $this->hasOne(CategoryTimeSchedule::class, 'time_schedule_id');
    }

    /**
     * Get the shift category through the category-time-schedule pivot.
     *
     * @return HasOneThrough<ShiftCategory, CategoryTimeSchedule, $this>
     */
    public function category(): HasOneThrough
    {
        return $this->hasOneThrough(
            ShiftCategory::class,
            CategoryTimeSchedule::class,
            'time_schedule_id',
            'id',
            'id',
            'shift_category_id'
        );
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
}
