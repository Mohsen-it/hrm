<?php

namespace Modules\Attendance\Exceptions;

use RuntimeException;
use Throwable;

/**
 * AttendanceException — base exception for the Attendance module.
 *
 * Other module-specific exceptions (e.g. AttendanceCalculationException) extend
 * this class so callers can catch every attendance-domain error in a single
 * `catch (AttendanceException $e)` block when needed.
 */
class AttendanceException extends RuntimeException
{
    /**
     * Optional structured context attached to the exception (e.g. user_id, date).
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new attendance exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message = '', array $context = [], int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the structured context attached to the exception.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
