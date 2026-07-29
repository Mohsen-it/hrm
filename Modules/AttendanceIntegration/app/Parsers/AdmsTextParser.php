<?php

namespace Modules\AttendanceIntegration\Parsers;

class AdmsTextParser
{
    /**
     * Parse ADMS ATTLOG text body into structured punch records.
     *
     * Supports two formats:
     *   Format A (with ATT prefix):
     *     ATT20035\t2026-07-29 18:11:11\t0\t1\t...
     *
     *   Format B (raw tab-separated, no ATT prefix):
     *     20035\t2026-07-29 18:11:11\t0\t1\t0\t0\t0\t0\t0\t0\t3165
     *
     * Columns (tab-separated):
     *   [0] user_id (or ATT+user_id)
     *   [1] timestamp
     *   [2] status (0=check_in, 1=check_out, 2=break_out, 3=break_in)
     *   [3] verify_method (0/1=fingerprint, 2/3=card, 4=password)
     *   [4-8] reserved
     *   [9] work_code
     */
    public static function parse(string $body): array
    {
        $rows = [];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = preg_split('/\t/', $line);

            // Format A: starts with "ATT" prefix
            if (count($cols) >= 3 && stripos($cols[0], 'ATT') === 0) {
                $userId = preg_replace('/^ATT/i', '', $cols[0]);
                $rows[] = [
                    'user_id' => $userId ?: null,
                    'timestamp' => $cols[1] ?? null,
                    'status' => isset($cols[2]) ? (int) $cols[2] : null,
                ];

                continue;
            }

            // Format B: raw tab-separated (user_id, timestamp, status, ...)
            if (count($cols) >= 3) {
                $userId = $cols[0];
                // Skip lines that look like headers or non-attendance data
                if (is_numeric($userId) || preg_match('/^\w{2,}/', $userId)) {
                    $rows[] = [
                        'user_id' => $userId,
                        'timestamp' => $cols[1] ?? null,
                        'status' => isset($cols[2]) ? (int) $cols[2] : null,
                    ];
                }
            }
        }

        return $rows;
    }
}
