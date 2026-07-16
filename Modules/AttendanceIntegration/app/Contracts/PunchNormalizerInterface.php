<?php

namespace Modules\AttendanceIntegration\Contracts;

use Modules\AttendanceIntegration\DTOs\NormalizedPunch;

interface PunchNormalizerInterface
{
    public function normalize(array $rawPunch): NormalizedPunch;

    public function getDriverName(): string;
}
