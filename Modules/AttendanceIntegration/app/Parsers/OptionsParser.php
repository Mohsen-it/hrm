<?php

namespace Modules\AttendanceIntegration\Parsers;

/**
 * Parses OPTIONS payloads from ZKTeco ADMS push.
 *
 * Device OPTIONS format (one key=value per line):
 *   OPTIONS
 *   DeviceName=XXXXX
 *   FWVersion=v6.62 build 2023-10-11
 *   PushVersion=v1.1.1
 *   Platform=ZKTeco iFace880
 *   UserCount=150
 *   FPCount=300
 *   FaceCount=150
 *   ATTLogCount=50000
 *   PhotoCount=0
 *   BioVer=10
 *   ...
 */
class OptionsParser
{
    /**
     * Parse an OPTIONS text body into structured device info.
     *
     * @return array<string, mixed>
     */
    public static function parse(string $body): array
    {
        $result = [
            'raw' => $body,
            'device_name' => null,
            'firmware_version' => null,
            'push_version' => null,
            'platform' => null,
            'user_count' => null,
            'fingerprint_count' => null,
            'face_count' => null,
            'attendance_count' => null,
            'photo_count' => null,
            'bio_version' => null,
            'transaction_count' => null,
            'timezone' => null,
            'capabilities' => [],
        ];

        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = rtrim($line, "\r\n");
            $trimmed = trim($line);

            if ($trimmed === '' || strtoupper($trimmed) === 'OPTIONS') {
                continue;
            }

            if (str_contains($trimmed, '=')) {
                [$key, , $value] = explode('=', $trimmed, 2);
                $key = trim($key);
                $value = trim($value);

                match (strtolower($key)) {
                    'devicename' => $result['device_name'] = $value,
                    'fwversion', 'firmwareversion' => $result['firmware_version'] = $value,
                    'pushversion' => $result['push_version'] = $value,
                    'platform' => $result['platform'] = $value,
                    'usercount' => $result['user_count'] = (int) $value,
                    'fpcount' => $result['fingerprint_count'] = (int) $value,
                    'facecount' => $result['face_count'] = (int) $value,
                    'attlogcount' => $result['attendance_count'] = (int) $value,
                    'photocount' => $result['photo_count'] = (int) $value,
                    'biover' => $result['bio_version'] = $value,
                    'transactioncount' => $result['transaction_count'] = (int) $value,
                    'timezone' => $result['timezone'] = $value,
                    default => $result['capabilities'][strtolower($key)] = $value,
                };
            }
        }

        return $result;
    }

    /**
     * Check if the body looks like OPTIONS content.
     */
    public static function isOptions(string $body): bool
    {
        $preview = strtoupper(trim(substr($body, 0, 200)));

        return str_contains($preview, 'OPTIONS') && str_contains($preview, '=');
    }
}
