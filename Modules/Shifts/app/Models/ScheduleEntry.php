<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

class ScheduleEntry extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'schedule_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'schedule_period_id',
        'employee_id',
        'duty_category_id',
        'date',
        'day_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Get the schedule period for this entry.
     *
     * @return BelongsTo<SchedulePeriod, $this>
     */
    public function schedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class);
    }

    /**
     * Get the employee (user) for this entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Get the shift category for this entry.
     *
     * @return BelongsTo<ShiftCategory, $this>
     */
    public function dutyCategory(): BelongsTo
    {
        return $this->belongsTo(ShiftCategory::class, 'duty_category_id');
    }

    /**
     * Check if this is a work day.
     */
    public function isWorkDay(): bool
    {
        return $this->day_status === 'WORK';
    }

    /**
     * Check if this is a rest day.
     */
    public function isRestDay(): bool
    {
        return $this->day_status === 'REST';
    }
}
