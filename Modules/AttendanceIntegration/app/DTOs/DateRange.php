<?php

namespace Modules\AttendanceIntegration\DTOs;

final class DateRange
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        if ($from > $to) {
            throw new \InvalidArgumentException("from ({$from}) must not be after to ({$to})");
        }
    }
}
