<?php

namespace Modules\Vacations\Services;

/**
 * HolidayLookup — narrow contract used by `VacationBalanceService` to ask
 * "is this date a holiday?" without forcing the Vacations module to depend
 * on the Holidays module at runtime.
 *
 * The Holidays module ships a `HolidayLookup` adapter (in Task 80) that
 * implements this interface, but the balance service never imports the
 * Holidays module directly — the controller wires the adapter in.
 */
interface HolidayLookup
{
    /**
     * Determine whether the supplied `Y-m-d` date is a holiday.
     */
    public function isHoliday(string $date): bool;
}
