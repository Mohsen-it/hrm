<?php

namespace Modules\AttendanceIntegration\Exceptions;

class PunchIngestionFailedException extends \RuntimeException
{
    public function __construct(string $message = 'Punch ingestion failed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
