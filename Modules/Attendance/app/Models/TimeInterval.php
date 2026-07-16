<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Companies\Models\Company;

/**
 * TimeInterval — defines a time window with margins for attendance calculation.
 */
class TimeInterval extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_timeinterval';

    protected $fillable = [
        'alias',
        'use_mode',
        'in_time',
        'in_ahead_margin',
        'in_above_margin',
        'out_ahead_margin',
        'out_above_margin',
        'duration',
        'in_required',
        'out_required',
        'allow_late',
        'allow_leave_early',
        'work_day',
        'early_in',
        'min_early_in',
        'late_out',
        'min_late_out',
        'overtime_lv',
        'overtime_lv1',
        'overtime_lv2',
        'overtime_lv3',
        'multiple_punch',
        'available_interval_type',
        'available_interval',
        'work_time_duration',
        'func_key',
        'work_type',
        'day_change',
        'enable_early_in',
        'enable_late_out',
        'enable_overtime',
        'ot_rule',
        'color_setting',
        'enable_max_ot_limit',
        'max_ot_limit',
        'count_early_in_interval',
        'count_late_out_interval',
        'ot_pay_code_id',
        'overtime_policy',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'in_ahead_margin' => 'integer',
            'in_above_margin' => 'integer',
            'out_ahead_margin' => 'integer',
            'out_above_margin' => 'integer',
            'work_day' => 'double',
            'enable_early_in' => 'boolean',
            'enable_late_out' => 'boolean',
            'enable_overtime' => 'boolean',
            'enable_max_ot_limit' => 'boolean',
            'max_ot_limit' => 'integer',
            'count_early_in_interval' => 'boolean',
            'count_late_out_interval' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function breakTimes(): BelongsToMany
    {
        return $this->belongsToMany(BreakTime::class, 'att_timeinterval_break_time', 'timeinterval_id', 'breaktime_id');
    }

    public function otPayCode(): BelongsTo
    {
        return $this->belongsTo(PayCode::class, 'ot_pay_code_id');
    }

    public function scopeByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
