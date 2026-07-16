<?php

namespace Modules\AttendanceIntegration\Contracts;

interface DriverProviderInterface
{
    public function driverName(): string;

    public function providesAdapter(): bool;

    public function providesNormalizer(): bool;

    public function providesPushParser(): bool;

    public function adapterClass(): string;

    public function normalizerClass(): string;

    public function pushParserClass(): string;
}
