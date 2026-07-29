<?php

namespace Modules\AttendanceIntegration\Parsers;

/**
 * Parses USERINFO payloads from ZKTeco ADMS push.
 *
 * Format (one record per line, tab-separated):
 *   USERINFO\tpin\tname\tpassword\tprivilege\tcard
 *
 * Or multi-line key=value format:
 *   USERINFO
 *   Pin=20079
 *   Name=John Doe
 *   Password=
 *   Privilege=0
 *   Card=0
 *   ...
 */
class UserinfoParser
{
    /**
     * Parse a USERINFO text body into structured records.
     *
     * @return array<int, array{pin: string, name: string, password: string, privilege: int, card: string, department: string, raw: string}>
     */
    public static function parse(string $body): array
    {
        $records = [];
        $lines = preg_split('/\r?\n/', $body) ?: [];
        $current = null;

        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($current !== null && self::isValidRecord($current)) {
                    $records[] = self::finalizeRecord($current);
                }
                $current = null;

                continue;
            }

            $isUserinfoStart = strtoupper(substr($trimmed, 0, 8)) === 'USERINFO';

            if ($isUserinfoStart) {
                if ($current !== null && self::isValidRecord($current)) {
                    $records[] = self::finalizeRecord($current);
                }

                $current = [
                    'pin' => '',
                    'name' => '',
                    'password' => '',
                    'privilege' => 0,
                    'card' => '',
                    'department' => '',
                    '_raw_lines' => [],
                ];

                $afterKeyword = trim(substr($trimmed, 8));
                if ($afterKeyword !== '' && str_contains($afterKeyword, "\t")) {
                    self::parseTabDelimited($current, $afterKeyword);
                }

                continue;
            }

            // Tab-delimited format
            if (str_contains($trimmed, "\t")) {
                if ($current === null) {
                    $current = [
                        'pin' => '',
                        'name' => '',
                        'password' => '',
                        'privilege' => 0,
                        'card' => '',
                        'department' => '',
                        '_raw_lines' => [],
                    ];
                }
                self::parseTabDelimited($current, $trimmed);

                continue;
            }

            // Key=value format
            if ($current !== null && str_contains($trimmed, '=')) {
                $current['_raw_lines'][] = $trimmed;
                self::parseKeyValue($current, $trimmed);
            }
        }

        if ($current !== null && self::isValidRecord($current)) {
            $records[] = self::finalizeRecord($current);
        }

        return $records;
    }

    private static function parseTabDelimited(array &$record, string $line): void
    {
        $parts = explode("\t", $line);
        $record['_raw_lines'][] = $line;

        if (count($parts) >= 5) {
            $record['pin'] = $parts[0];
            $record['name'] = $parts[1];
            $record['password'] = $parts[2] ?? '';
            $record['privilege'] = (int) ($parts[3] ?? 0);
            $record['card'] = $parts[4] ?? '';
            $record['department'] = $parts[5] ?? '';
        } elseif (count($parts) >= 4) {
            $record['pin'] = $parts[0];
            $record['name'] = $parts[1];
            $record['password'] = $parts[2] ?? '';
            $record['privilege'] = (int) ($parts[3] ?? 0);
        } elseif (count($parts) >= 2) {
            $record['pin'] = $parts[0];
            $record['name'] = $parts[1];
        }
    }

    private static function parseKeyValue(array &$record, string $line): void
    {
        if (preg_match('/(\w+)=(.+)/', $line, $m)) {
            match (strtolower($m[1])) {
                'pin' => $record['pin'] = trim($m[2]),
                'name' => $record['name'] = trim($m[2]),
                'password', 'pwd' => $record['password'] = trim($m[2]),
                'privilege', 'priv' => $record['privilege'] = (int) trim($m[2]),
                'card', 'cardno' => $record['card'] = trim($m[2]),
                'dept', 'department' => $record['department'] = trim($m[2]),
                default => null,
            };
        }
    }

    private static function isValidRecord(array $record): bool
    {
        return $record['pin'] !== '';
    }

    private static function finalizeRecord(array $record): array
    {
        $record['raw'] = implode("\n", $record['_raw_lines']);
        unset($record['_raw_lines']);

        return $record;
    }
}
