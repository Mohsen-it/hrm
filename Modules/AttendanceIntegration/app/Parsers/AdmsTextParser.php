<?php

namespace Modules\AttendanceIntegration\Parsers;

class AdmsTextParser
{
    /**
     * Parse ADMS ATTLOG text body into structured punch records.
     *
     * Supports three wire shapes (all tab-separated):
     *
     *   Shape T (tagged — sent by the ADMS relay with the table name first):
     *     ATT\t\t20035\t2026-07-29 18:11:11\t0\t1\t...
     *     cols: [0]=table tag 'ATT', [1]=empty, [2]=user_id, [3]=timestamp,
     *           [4]=status, [5]=verify_method, ..., [11]=work_code
     *
     *   Shape G (glued prefix):
     *     ATT20035\t2026-07-29 18:11:11\t0\t1\t...
     *     cols: [0]='ATT'+user_id, [1]=timestamp, [2]=status,
     *           [3]=verify_method, ..., [9]=work_code
     *
     *   Shape P (plain device record, no prefix):
     *     20035\t2026-07-29 18:11:11\t0\t1\t0\t0\t0\t0\t0\t0\t3165
     *     cols: [0]=user_id, [1]=timestamp, [2]=status (0=check_in,
     *           1=check_out, 2=break_out, 3=break_in), [3]=verify_method
     *           (0/1=fingerprint, 2/3=card, 4=password); the work_code is the
     *           last column of full-length records.
     *
     * Lines with an empty user_id or timestamp are skipped: a punch without
     * an owner can never be matched to an employee and only pollutes the
     * ingestion pipeline.
     */
    public static function parse(string $body): array
    {
        $rows = [];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $record = self::parseLine($line);
            if ($record !== null) {
                $rows[] = $record;
            }
        }

        return $rows;
    }

    /**
     * Parse one tab-separated line. Returns null when the line carries no
     * usable punch (headers, footers, or ownerless rows).
     *
     * @return array{user_id: string, timestamp: string, status: ?int, punch: ?int, work_code: ?int}|null
     */
    private static function parseLine(string $line): ?array
    {
        $cols = preg_split('/\t/', $line);
        if ($cols === false || count($cols) < 3) {
            return null;
        }

        $first = trim((string) $cols[0]);

        if (strcasecmp($first, 'ATT') === 0) {
            // Shape T: table tag + (possibly empty) filler columns, then the
            // device record starting at the first non-empty column.
            $rest = array_values(array_filter(
                array_slice($cols, 1),
                fn ($c) => trim((string) $c) !== ''
            ));

            return self::buildRecord($rest, 0, self::trailingWorkCode($cols));
        }

        if (preg_match('/^ATT(.+)$/i', $first, $m) === 1 && trim($m[1]) !== '') {
            // Shape G: 'ATT' glued to the PIN — re-anchor on the captured PIN.
            $rest = array_merge([trim($m[1])], array_slice($cols, 1));

            return self::buildRecord($rest, 0, self::trailingWorkCode($cols));
        }

        // Shape P: plain positional record (also tolerates a leading empty
        // column some relays emit).
        $rest = array_values(array_filter(
            $cols,
            fn ($c) => trim((string) $c) !== ''
        ));

        if ($rest === []) {
            return null;
        }

        // A header/footer line has no datetime in the second position.
        if (! isset($rest[1]) || self::toTimestamp($rest[1]) === null) {
            return null;
        }

        return self::buildRecord($rest, 0, self::trailingWorkCode($cols));
    }

    /**
     * Build a record from columns anchored at the user_id position.
     *
     * Layout from the anchor: [+0]=user_id, [+1]=timestamp, [+2]=status,
     * [+3]=verify_method. The work_code travels in the last column of the
     * full-length device record (see trailingWorkCode()).
     *
     * @param  array<int, string>  $cols
     * @return array{user_id: string, timestamp: string, status: ?int, punch: ?int, work_code: ?int}|null
     */
    private static function buildRecord(array $cols, int $anchor, ?int $workCode): ?array
    {
        $userId = trim((string) ($cols[$anchor] ?? ''));
        $timestamp = trim((string) ($cols[$anchor + 1] ?? ''));

        if ($userId === '' || $timestamp === '' || self::toTimestamp($timestamp) === null) {
            return null;
        }

        return [
            'user_id' => $userId,
            'timestamp' => $timestamp,
            'status' => self::toIntOrNull($cols[$anchor + 2] ?? null),
            'punch' => self::toIntOrNull($cols[$anchor + 3] ?? null),
            'work_code' => $workCode,
        ];
    }

    /**
     * Extract the work_code from the last column of a full-length device
     * record (10+ tab columns). Short lines carry no work_code — their tail
     * columns are status/verify fields, never a work code.
     *
     * @param  array<int, string>  $originalCols  raw split line, unfiltered
     */
    private static function trailingWorkCode(array $originalCols): ?int
    {
        if (count($originalCols) < 10) {
            return null;
        }

        return self::toIntOrNull(end($originalCols));
    }

    private static function toIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    private static function toTimestamp(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable(trim($value)) ? trim($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
