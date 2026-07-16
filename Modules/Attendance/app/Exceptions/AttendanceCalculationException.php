<?php

namespace Modules\Attendance\Exceptions;

use Throwable;

/**
 * AttendanceCalculationException — raised by the calculation / auto-roll-up
 * services when an invariant that prevents a safe recalculation is violated
 * (missing user, missing shift, malformed date range, etc.).
 *
 * Catching this exception lets the controllers convert the failure into a
 * localised flash message instead of bubbling a generic 500 to the user.
 */
class AttendanceCalculationException extends AttendanceException
{
    /**
     * Create a new calculation exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message = '', array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        if ($message === '') {
            $message = 'attendance.errors.calculation_failed';
        }

        parent::__construct($message, $context, $code, $previous);
    }
}
