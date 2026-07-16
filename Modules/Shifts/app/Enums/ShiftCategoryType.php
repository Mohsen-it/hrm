<?php

namespace Modules\Shifts\Enums;

/**
 * ShiftCategoryType — classification for how shift cycles are organized.
 *
 * Stored as a string column on shift categories; the backed values are
 * the source of truth consumed by repositories and form requests when
 * validating user input.
 */
enum ShiftCategoryType: string
{
    case Cyclic = 'cyclic';
    case Weekly = 'weekly';
    case Hours = 'hours';

    /**
     * Return the available values, suitable for `<select>` options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::Cyclic->value, 'label' => __('shifts.shift_category_type_cyclic')],
            ['value' => self::Weekly->value, 'label' => __('shifts.shift_category_type_weekly')],
            ['value' => self::Hours->value, 'label' => __('shifts.shift_category_type_hours')],
        ];
    }
}
