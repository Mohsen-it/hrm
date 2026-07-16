<?php

namespace Modules\Shifts\Enums;

/**
 * TrackingStatus — indicates whether a shift's tracked time is on target.
 *
 * Stored as a string column on tracking-related models; the backed
 * values are the source of truth consumed by repositories and form
 * requests when validating user input.
 */
enum TrackingStatus: string
{
    case OnTrack = 'on_track';
    case Deficit = 'deficit';
    case Surplus = 'surplus';

    /**
     * Return the available values, suitable for `<select>` options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::OnTrack->value, 'label' => __('shifts.tracking_status_on_track')],
            ['value' => self::Deficit->value, 'label' => __('shifts.tracking_status_deficit')],
            ['value' => self::Surplus->value, 'label' => __('shifts.tracking_status_surplus')],
        ];
    }
}
