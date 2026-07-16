<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * GroupPolicy — attendance policy rules for an attendance group.
 */
class GroupPolicy extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_grouppolicy';

    protected $fillable = [
        'group_id',
        'use_ot',
        'weekend1',
        'weekend2',
        'start_of_week',
        'max_hrs',
        'day_change',
        'paring_rule',
        'punch_period',
        'daily_ot',
        'daily_ot_rule',
        'weekly_ot',
        'weekly_ot_rule',
        'weekend_ot',
        'weekend_ot_rule',
        'holiday_ot',
        'holiday_ot_rule',
        'late_in2absence',
        'early_out2absence',
        'miss_in',
        'late_in_hrs',
        'miss_out',
        'early_out_hrs',
        'require_capture',
        'require_work_code',
        'require_punch_state',
        'email_send_time',
        'group_frequency',
        'group_send_day',
        'max_absent',
        'max_early_out',
        'max_late_in',
        'sending_day',
        'weekend1_color_setting',
        'weekend2_color_setting',
        'ot_pay_code_id',
        'overtime_policy',
        'enable_compensatory',
        'bot_uid',
        'enable_workcode_calculation',
        'enable_workcode_punch_state',
        'create_time',
        'create_user',
        'change_time',
        'change_user',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'max_hrs' => 'decimal:1',
            'daily_ot' => 'boolean',
            'weekly_ot' => 'boolean',
            'weekend_ot' => 'boolean',
            'holiday_ot' => 'boolean',
            'require_capture' => 'boolean',
            'require_work_code' => 'boolean',
            'require_punch_state' => 'boolean',
            'enable_compensatory' => 'boolean',
            'enable_workcode_calculation' => 'boolean',
            'status' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AttendanceGroup::class, 'group_id');
    }

    public function otPayCode(): BelongsTo
    {
        return $this->belongsTo(PayCode::class, 'ot_pay_code_id');
    }
}
