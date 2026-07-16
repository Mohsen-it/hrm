<?php

namespace Modules\Holidays\Services;

use Modules\Vacations\Services\HolidayLookup;

/**
 * HolidayLookupAdapter — bridges the Holidays service into the Vacations
 * module's `HolidayLookup` contract so the balance service can ask
 * "is this date a holiday?" without taking a direct module dependency.
 *
 * The adapter is registered as the `HolidayLookup` binding inside the
 * Holidays service provider (Task 80).
 */
class HolidayLookupAdapter implements HolidayLookup
{
    /**
     * Create a new adapter instance.
     */
    public function __construct(
        private HolidayService $holidayService,
    ) {}

    public function isHoliday(string $date): bool
    {
        return $this->holidayService->isHoliday($date);
    }
}
