<?php

namespace Modules\AttendanceIntegration\Parsers;

/**
 * Parses BIODATA payloads from ZKTeco ADMS push.
 *
 * Supports two formats:
 *   Format A (multi-line, older devices):
 *     BIODATA
 *     Pin=20079
 *     Type=2
 *     MajorVer=12
 *     MinorVer=0
 *     Format=0
 *     Tmp=...
 *
 *   Format B (inline, iFace880 Plus):
 *     BIODATA Pin=20079  No=0  Index=0  Valid=1  Duress=0  Type=2  MajorVer=12  MinorVer=0  Format=0
 *             Tmp=...
 *
 * Records may be separated by blank lines or a new BIODATA keyword.
 */
class BiodataParser
{
    public const TYPE_FINGERPRINT = 0;

    public const TYPE_FACE = 2;

    /**
     * Parse a BIODATA text body into structured records.
     *
     * @return array<int, array{
     *     pin: string,
     *     type: int,
     *     major_ver: int,
     *     minor_ver: int,
     *     format: int,
     *     tmp: string,
     *     raw: string,
     * }>
     */
    public static function parse(string $body): array
    {
        $records = [];
        $current = null;

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = rtrim($line, "\r\n");
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($current !== null && self::isValidRecord($current)) {
                    $records[] = self::finalizeRecord($current);
                }
                $current = null;

                continue;
            }

            // Check if this line starts a new BIODATA record
            $isBiodataStart = strtoupper(substr($trimmed, 0, 7)) === 'BIODATA';

            if ($isBiodataStart) {
                // Save previous record if one was in progress (no blank line separator)
                if ($current !== null && self::isValidRecord($current)) {
                    $records[] = self::finalizeRecord($current);
                }

                $current = [
                    'pin' => '',
                    'type' => 0,
                    'major_ver' => 0,
                    'minor_ver' => 0,
                    'format' => 0,
                    'tmp' => '',
                    '_raw_lines' => [],
                ];

                $afterKeyword = trim(substr($trimmed, 7));
                if ($afterKeyword === '') {
                    // Multi-line format: "BIODATA" alone on its own line
                    continue;
                }

                // Inline format: "BIODATA Pin=20079 No=0 ..." — extract all key=value pairs
                self::extractFields($current, $afterKeyword, $line);

                continue;
            }

            // Continuation line (key=value pairs or Tmp= data)
            if ($current !== null) {
                self::extractFields($current, ltrim($trimmed), $line);
            }
        }

        if ($current !== null && self::isValidRecord($current)) {
            $records[] = self::finalizeRecord($current);
        }

        return $records;
    }

    /**
     * Extract key=value pairs from text and populate the record.
     */
    private static function extractFields(array &$current, string $text, string $rawLine): void
    {
        $current['_raw_lines'][] = $rawLine;

        if (preg_match_all('/(\w+)=(\S+)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower($match[1]);
                $value = $match[2];
                match ($key) {
                    'pin' => $current['pin'] = $value,
                    'type' => $current['type'] = (int) $value,
                    'majorver' => $current['major_ver'] = (int) $value,
                    'minorver' => $current['minor_ver'] = (int) $value,
                    'format' => $current['format'] = (int) $value,
                    'tmp' => $current['tmp'] = $value,
                    default => null,
                };
            }
        }
    }

    /**
     * Check if the body looks like BIODATA content.
     */
    public static function isBiodata(string $body): bool
    {
        $preview = strtolower(trim(substr($body, 0, 200)));

        return str_contains($preview, 'biodata') || (str_contains($preview, 'pin=') && str_contains($preview, 'tmp='));
    }

    private static function isValidRecord(array $record): bool
    {
        return $record['pin'] !== '';
    }

    private static function finalizeRecord(array $record): array
    {
        $raw = implode("\n", $record['_raw_lines']);

        unset($record['_raw_lines']);

        $record['raw'] = $raw;

        return $record;
    }

    /**
     * Get a human-readable label for a BIODATA type.
     */
    public static function typeLabel(int $type): string
    {
        return match ($type) {
            self::TYPE_FINGERPRINT => 'fingerprint',
            self::TYPE_FACE => 'face',
            default => "unknown_type_{$type}",
        };
    }
}
