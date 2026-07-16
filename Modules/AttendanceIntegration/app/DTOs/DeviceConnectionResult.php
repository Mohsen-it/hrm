<?php

namespace Modules\AttendanceIntegration\DTOs;

final class DeviceConnectionResult
{
    public function __construct(
        public readonly bool $connected,
        public readonly ?string $error = null,
    ) {}

    public static function success(): self
    {
        return new self(connected: true);
    }

    public static function failure(string $error): self
    {
        return new self(connected: false, error: $error);
    }
}
