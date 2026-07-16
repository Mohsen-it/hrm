<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Companies\Models\Company;

/**
 * BreakTime — defines a break period within a shift.
 */
class BreakTime extends Model
{
    use HasFactory;

    protected $table = 'att_breaktime';

    protected $fillable = [
        'alias',
        'period_start',
        'duration',
        'end_margin',
        'func_key',
        'available_interval_type',
        'available_interval',
        'multiple_punch',
        'calc_type',
        'minimum_duration',
        'early_in',
        'late_in',
        'profit_rule',
        'min_early_in',
        'loss_rule',
        'min_late_in',
        'loss_code_id',
        'profit_code_id',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'minimum_duration' => 'integer',
            'profit_rule' => 'boolean',
            'loss_rule' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function lossCode(): BelongsTo
    {
        return $this->belongsTo(PayCode::class, 'loss_code_id');
    }

    public function profitCode(): BelongsTo
    {
        return $this->belongsTo(PayCode::class, 'profit_code_id');
    }

    public function timeIntervals(): BelongsToMany
    {
        return $this->belongsToMany(TimeInterval::class, 'att_timeinterval_break_time', 'breaktime_id', 'timeinterval_id');
    }
}
