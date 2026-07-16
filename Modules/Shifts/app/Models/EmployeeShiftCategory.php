<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

class EmployeeShiftCategory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'att_employee_shift_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'shift_category_id',
        'start_date',
        'end_date',
        'snapshot_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'snapshot_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the employee (user) for this assignment.
     *
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Get the shift category for this assignment.
     *
     * @return BelongsTo<ShiftCategory, $this>
     */
    public function shiftCategory(): BelongsTo
    {
        return $this->belongsTo(ShiftCategory::class, 'shift_category_id');
    }

    /**
     * Scope a query to only include active assignments (no end date).
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }

    /**
     * Scope a query to filter by employee.
     *
     * @param  Builder  $query
     * @param  int  $employeeId
     * @return Builder
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope a query to filter assignments valid for a given date.
     *
     * @param  Builder  $query
     * @param  string  $date
     * @return Builder
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }
}
