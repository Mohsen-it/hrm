<?php

namespace Modules\AttendanceIntegration\Parsers;

/**
 * Parses USERPIC payloads from ZKTeco ADMS push.
 *
 * Format:
 *   USERPIC PIN=20079 FileName=20079.jpg Size=9376 Content=/9j/4AAQ...
 *
 * The Content field contains the base64-encoded JPEG face photo.
 */
class UserpicParser
{
    /**
     * Parse USERPIC lines from the body.
     *
     * @return array<int, array{
     *     pin: string,
     *     filename: string,
     *     size: int,
     *     content_base64: string,
     * }>
     */
    public static function parse(string $body): array
    {
        $records = [];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || ! str_starts_with(strtolower($line), 'userpic')) {
                continue;
            }

            $record = self::parseLine($line);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    public static function isUserpic(string $body): bool
    {
        $preview = strtolower(substr($body, 0, 300));

        return str_contains($preview, 'userpic') && str_contains($preview, 'content=');
    }

    private static function parseLine(string $line): ?array
    {
        preg_match('/PIN=(\S+)/i', $line, $pinMatch);
        preg_match('/FileName=(\S+)/i', $line, $fileMatch);
        preg_match('/Size=(\d+)/i', $line, $sizeMatch);
        preg_match('/Content=(.+)/i', $line, $contentMatch);

        if (empty($pinMatch[1]) || empty($contentMatch[1])) {
            return null;
        }

        return [
            'pin' => $pinMatch[1],
            'filename' => $fileMatch[1] ?? ($pinMatch[1].'.jpg'),
            'size' => isset($sizeMatch[1]) ? (int) $sizeMatch[1] : 0,
            'content_base64' => trim($contentMatch[1]),
        ];
    }
}
