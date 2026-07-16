<?php

namespace Modules\AttendanceIntegration\Drivers\ZKTeco;

use Modules\AttendanceIntegration\Contracts\DriverProviderInterface;

class ZKTecoProvider implements DriverProviderInterface
{
    public function driverName(): string
    {
        return 'zkteco';
    }

    public function providesAdapter(): bool
    {
        return true;
    }

    public function providesNormalizer(): bool
    {
        return true;
    }

    public function providesPushParser(): bool
    {
        return true;
    }

    public function adapterClass(): string
    {
        return ZKTecoAdapter::class;
    }

    public function normalizerClass(): string
    {
        return ZKTecoPunchNormalizer::class;
    }

    public function pushParserClass(): string
    {
        return ZKTecoAdmsParser::class;
    }
}
