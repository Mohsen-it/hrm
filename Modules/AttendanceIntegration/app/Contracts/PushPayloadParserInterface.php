<?php

namespace Modules\AttendanceIntegration\Contracts;

interface PushPayloadParserInterface
{
    public function parse(array $requestBody, array $requestHeaders): array;

    public function getDriverName(): string;
}
