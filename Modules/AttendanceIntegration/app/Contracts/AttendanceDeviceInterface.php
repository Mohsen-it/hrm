<?php

namespace Modules\AttendanceIntegration\Contracts;

interface AttendanceDeviceInterface
{
    public function getId(): int;

    public function getName(): string;

    public function getSerialNumber(): ?string;

    public function getIpAddress(): string;

    public function getPort(): int;

    public function getCommKey(): string;

    public function getTimeout(): int;

    public function getDriverName(): string;

    public function getStatus(): string;

    public function isPushEnabled(): bool;

    public function getLastSyncAt(): ?string;

    public function getApiToken(): ?string;

    public function toArray(): array;
}
