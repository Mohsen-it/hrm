<?php

namespace Modules\AttendanceIntegration\Parsers;

/**
 * Parses OPERLOG (Operation Log) payloads from ZKTeco ADMS push.
 *
 * Format (one record per line, tab-separated):
 *   OPLOG\ttimestamp\tpin\toperation_type\tdetail
 *
 * Or multi-line key=value format:
 *   OPERLOG
 *   Pin=20079
 *   Time=2024-01-15 10:30:00
 *   Op=ADD_USER
 *   ...
 */
class OperlogParser
{
    /**
     * Parse an OPERLOG text body into structured records.
     *
     * @return array<int, array{pin: string, timestamp: string, operation: string, detail: string, raw: string}>
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

            $isOperlogStart = strtoupper(substr($trimmed, 0, 7)) === 'OPERLOG' || strtoupper(substr($trimmed, 0, 5)) === 'OPLOG';

            if ($isOperlogStart) {
                if ($current !== null && self::isValidRecord($current)) {
                    $records[] = self::finalizeRecord($current);
                }

                $current = [
                    'pin' => '',
                    'timestamp' => '',
                    'operation' => '',
                    'detail' => '',
                    '_raw_lines' => [],
                ];

                $afterKeyword = trim(substr($trimmed, strpos($trimmed, "\t") !== false ? strpos($trimmed, "\t") : 7));
                if ($afterKeyword !== '' && str_contains($afterKeyword, "\t")) {
                    self::parseTabDelimited($current, $afterKeyword);
                }

                continue;
            }

            // Tab-delimited format: timestamp\tpin\toperation\tdetail
            if (str_contains($trimmed, "\t")) {
                if ($current === null) {
                    $current = [
                        'pin' => '',
                        'timestamp' => '',
                        'operation' => '',
                        'detail' => '',
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

        if (count($parts) >= 4) {
            $record['timestamp'] = $parts[0];
            $record['pin'] = $parts[1];
            $record['operation'] = $parts[2];
            $record['detail'] = $parts[3] ?? '';
        } elseif (count($parts) >= 3) {
            $record['timestamp'] = $parts[0];
            $record['pin'] = $parts[1];
            $record['operation'] = $parts[2];
        } elseif (count($parts) >= 2) {
            $record['timestamp'] = $parts[0];
            $record['pin'] = $parts[1];
        }
    }

    private static function parseKeyValue(array &$record, string $line): void
    {
        if (preg_match('/(\w+)=(.+)/', $line, $m)) {
            match (strtolower($m[1])) {
                'pin' => $record['pin'] = trim($m[2]),
                'time', 'timestamp' => $record['timestamp'] = trim($m[2]),
                'op', 'operation' => $record['operation'] = trim($m[2]),
                'detail', 'desc' => $record['detail'] = trim($m[2]),
                default => null,
            };
        }
    }

    private static function isValidRecord(array $record): bool
    {
        return $record['pin'] !== '' || $record['operation'] !== '';
    }

    private static function finalizeRecord(array $record): array
    {
        $record['raw'] = implode("\n", $record['_raw_lines']);
        unset($record['_raw_lines']);

        return $record;
    }
}
