<?php

namespace Modules\AttendanceIntegration\Exceptions;

class DeviceNotFoundException extends \RuntimeException
{
    public function __construct(string $identifier, ?\Throwable $previous = null)
    {
        parent::__construct("Device not found: {$identifier}", 0, $previous);
    }
}
