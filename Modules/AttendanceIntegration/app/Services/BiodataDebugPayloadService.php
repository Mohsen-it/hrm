<?php

namespace Modules\AttendanceIntegration\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Stores an unmodified BIODATA payload only when it cannot be parsed.
 *
 * Biometric templates are sensitive data, so successful payloads are never
 * copied to the debug directory.
 */
class BiodataDebugPayloadService
{
    /**
     * Persist a failed BIODATA payload and return its absolute path.
     */
    public function save(string $payload, string $deviceSerial, string $correlationId, string $reason): string
    {
        $directory = storage_path('app/debug/biodata');
        File::ensureDirectoryExists($directory, 0750, true);

        $filename = sprintf(
            '%s_%s_%s.txt',
            now()->format('Ymd_His_u'),
            Str::of($deviceSerial)->replaceMatches('/[^A-Za-z0-9_-]/', '_')->limit(50, ''),
            Str::of($correlationId)->replaceMatches('/[^A-Za-z0-9_-]/', '_')->limit(50, ''),
        );

        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        File::put($path, "Reason: {$reason}\nDevice: {$deviceSerial}\nCorrelation ID: {$correlationId}\n\n{$payload}");

        return $path;
    }
}
