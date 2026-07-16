<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Users\Models\User;

class SchedulePeriod extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'schedule_periods';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'year',
        'month',
        'schedule_period_start',
        'schedule_period_end',
        'status',
        'generated_by',
        'generated_at',
        'published_by',
        'published_at',
        'schedule_version',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'schedule_period_start' => 'date',
        'schedule_period_end' => 'date',
        'generated_at' => 'timestamp',
        'published_at' => 'timestamp',
        'schedule_version' => 'integer',
    ];

    /**
     * Get the schedule entries for this period.
     *
     * @return HasMany<ScheduleEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    /**
     * Get the user who generated this schedule.
     *
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the user who published this schedule.
     *
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Check if this schedule is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if this schedule is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
