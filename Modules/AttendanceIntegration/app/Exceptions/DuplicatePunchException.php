<?php

namespace Modules\AttendanceIntegration\Exceptions;

class DuplicatePunchException extends \RuntimeException
{
    public function __construct(
        string $deviceUserId,
        string $punchTime,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Duplicate punch: user {$deviceUserId} at {$punchTime}",
            0,
            $previous
        );
    }
}
