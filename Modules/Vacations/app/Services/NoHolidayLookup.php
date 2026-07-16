<?php

namespace Modules\Vacations\Services;

/**
 * NoHolidayLookup — safe fallback used when the Holidays module is not
 * enabled or when callers don't want to bring the holiday calendar into
 * the day-counting loop (e.g. tests, dry-runs).
 *
 * Always answers `false`, so the day count uses the default
 * `counts_weekends` rule only.
 */
class NoHolidayLookup implements HolidayLookup
{
    public function isHoliday(string $date): bool
    {
        return false;
    }
}
