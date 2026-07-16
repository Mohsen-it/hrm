<?php

namespace Modules\AttendanceIntegration\Parsers;

class AdmsTextParser
{
    public static function parse(string $body): array
    {
        $rows = [];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'ATT') !== 0) {
                continue;
            }

            $cols = preg_split('/\t/', $line);
            $rows[] = [
                'user_id' => $cols[2] ?? null,
                'timestamp' => $cols[3] ?? null,
                'status' => isset($cols[4]) ? (int) $cols[4] : null,
            ];
        }

        return $rows;
    }
}
