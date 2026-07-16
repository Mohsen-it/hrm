<?php

namespace Modules\AttendanceIntegration\Drivers\ZKTeco;

use Modules\AttendanceIntegration\Contracts\PushPayloadParserInterface;
use Modules\AttendanceIntegration\Parsers\AdmsTextParser;

class ZKTecoAdmsParser implements PushPayloadParserInterface
{
    public function parse(array $requestBody, array $requestHeaders): array
    {
        $body = $requestBody['Body'] ?? null;

        if (is_string($body) && $body !== '') {
            return AdmsTextParser::parse($body);
        }

        if (is_array($body)) {
            return $body;
        }

        $rows = $requestBody['attendance'] ?? null;
        if (is_array($rows)) {
            return $rows;
        }

        if (isset($requestBody['user_id'])) {
            return [$requestBody];
        }

        return [];
    }

    public function getDriverName(): string
    {
        return 'zkteco';
    }
}
