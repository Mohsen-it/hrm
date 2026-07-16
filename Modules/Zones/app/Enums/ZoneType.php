<?php

namespace Modules\Zones\Enums;

/**
 * ZoneType — coarse classification for the operational role of a zone.
 *
 * Stored as a string column on `zones.zone_type`; the backed array is
 * the source of truth and is consumed by the zone repository / form
 * requests when validating user input.
 */
enum ZoneType: string
{
    case Geographic = 'geographic';
    case Operational = 'operational';
    case Security = 'security';
    case Sales = 'sales';
    case Logistics = 'logistics';

    /**
     * Return the available values, suitable for `<select>` options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::Geographic->value, 'label' => __('zones.zone_type_geographic')],
            ['value' => self::Operational->value, 'label' => __('zones.zone_type_operational')],
            ['value' => self::Security->value, 'label' => __('zones.zone_type_security')],
            ['value' => self::Sales->value, 'label' => __('zones.zone_type_sales')],
            ['value' => self::Logistics->value, 'label' => __('zones.zone_type_logistics')],
        ];
    }
}
