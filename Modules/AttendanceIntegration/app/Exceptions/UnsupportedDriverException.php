<?php

namespace Modules\AttendanceIntegration\Exceptions;

class UnsupportedDriverException extends \RuntimeException
{
    public function __construct(string $driver, ?\Throwable $previous = null)
    {
        parent::__construct("Unsupported device driver: {$driver}", 0, $previous);
    }
}
