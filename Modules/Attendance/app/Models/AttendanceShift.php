<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Companies\Models\Company;

/**
 * AttendanceShift — defines a shift pattern with cycle info and company scope.
 */
class AttendanceShift extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_attshift';

    protected $fillable = [
        'alias',
        'cycle_unit',
        'shift_cycle',
        'work_weekend',
        'weekend_type',
        'work_day_off',
        'day_off_type',
        'auto_shift',
        'enable_ot_rule',
        'ot_rule',
        'frequency',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'cycle_unit' => 'integer',
            'shift_cycle' => 'integer',
            'work_weekend' => 'boolean',
            'weekend_type' => 'integer',
            'work_day_off' => 'boolean',
            'day_off_type' => 'integer',
            'auto_shift' => 'integer',
            'enable_ot_rule' => 'boolean',
            'frequency' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ShiftDetail::class, 'shift_id');
    }

    public function scopeByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
