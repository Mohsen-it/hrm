<?php

namespace Modules\Shifts\Enums;

/**
 * PeriodType — defines the recurrence interval for shift tracking periods.
 *
 * Stored as a string column on shift-related models; the backed values
 * are the source of truth consumed by repositories and form requests
 * when validating user input.
 */
enum PeriodType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Return the available values, suitable for `<select>` options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::Daily->value, 'label' => __('shifts.period_type_daily')],
            ['value' => self::Weekly->value, 'label' => __('shifts.period_type_weekly')],
            ['value' => self::Monthly->value, 'label' => __('shifts.period_type_monthly')],
        ];
    }
}
