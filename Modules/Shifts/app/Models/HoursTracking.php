<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

class HoursTracking extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'att_hours_tracking';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'shift_category_id',
        'period_start',
        'period_end',
        'period_type',
        'required_hours',
        'actual_hours',
        'surplus_hours',
        'deficit_hours',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'required_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'surplus_hours' => 'decimal:2',
        'deficit_hours' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the employee (user) for this tracking record.
     *
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Get the shift category for this tracking record.
     *
     * @return BelongsTo<ShiftCategory, $this>
     */
    public function shiftCategory(): BelongsTo
    {
        return $this->belongsTo(ShiftCategory::class, 'shift_category_id');
    }

    /**
     * Scope a query to filter by period range.
     *
     * @param  Builder  $query
     * @param  string  $start
     * @param  string  $end
     * @return Builder
     */
    public function scopeForPeriod($query, $start, $end)
    {
        return $query->where('period_start', '>=', $start)
            ->where('period_end', '<=', $end);
    }
}
