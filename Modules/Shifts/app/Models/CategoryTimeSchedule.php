<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTimeSchedule extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'att_category_time_schedule';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'shift_category_id',
        'time_schedule_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the shift category.
     *
     * @return BelongsTo<ShiftCategory, $this>
     */
    public function shiftCategory(): BelongsTo
    {
        return $this->belongsTo(ShiftCategory::class, 'shift_category_id');
    }

    /**
     * Get the time schedule.
     *
     * @return BelongsTo<TimeSchedule, $this>
     */
    public function timeSchedule(): BelongsTo
    {
        return $this->belongsTo(TimeSchedule::class, 'time_schedule_id');
    }
}
