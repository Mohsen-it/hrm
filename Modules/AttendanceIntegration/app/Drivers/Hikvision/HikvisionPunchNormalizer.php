<?php

namespace Modules\AttendanceIntegration\Drivers\Hikvision;

use Modules\AttendanceIntegration\Contracts\PunchNormalizerInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\DTOs\VerifyMethod;

class HikvisionPunchNormalizer implements PunchNormalizerInterface
{
    public function normalize(array $rawPunch): NormalizedPunch
    {
        return self::normalizeStatic($rawPunch);
    }

    public static function normalizeStatic(array $rawPunch): NormalizedPunch
    {
        $deviceUserId = (string) ($rawPunch['user_id'] ?? $rawPunch['employeeNoString'] ?? '');
        $timestamp = self::parseTimestamp($rawPunch);

        $punchType = self::resolvePunchType($rawPunch);
        $verifyMethod = self::resolveVerifyMethod($rawPunch);

        return new NormalizedPunch(
            deviceUserId: $deviceUserId,
            timestamp: $timestamp,
            punchType: $punchType,
            verifyMethod: $verifyMethod,
            deviceSerial: $rawPunch['device_serial'] ?? $rawPunch['serial_number'] ?? null,
            uid: isset($rawPunch['uid']) ? (int) $rawPunch['uid'] : null,
            workCode: (int) ($rawPunch['work_code'] ?? 0),
            rawStatus: $rawPunch['attendance_status'] ?? $rawPunch['raw_status'] ?? null,
            rawData: $rawPunch,
        );
    }

    public function getDriverName(): string
    {
        return 'hikvision';
    }

    private static function resolvePunchType(array $raw): PunchType
    {
        $attendanceStatus = strtolower((string) ($raw['attendance_status'] ?? ''));
        if (str_contains($attendanceStatus, 'checkin') || str_contains($attendanceStatus, 'check_in')) {
            return PunchType::CheckIn;
        }
        if (str_contains($attendanceStatus, 'checkout') || str_contains($attendanceStatus, 'check_out')) {
            return PunchType::CheckOut;
        }

        $explicit = strtolower((string) ($raw['punch_type'] ?? ''));
        if ($explicit === 'check_in') {
            return PunchType::CheckIn;
        }
        if ($explicit === 'check_out') {
            return PunchType::CheckOut;
        }
        if ($explicit === 'break_in') {
            return PunchType::BreakIn;
        }
        if ($explicit === 'break_out') {
            return PunchType::BreakOut;
        }

        $major = (int) ($raw['major'] ?? 0);
        $minor = (int) ($raw['minor'] ?? 0);

        // Hikvision major=5 is access control event
        // minor codes: 75=face, 11=fingerprint, 1=card, etc.
        // The attendanceStatus field is the primary indicator
        $status = $raw['status'] ?? null;
        if ($status !== null) {
            return match ((int) $status) {
                0 => PunchType::CheckIn,
                1 => PunchType::CheckOut,
                2, 4 => PunchType::BreakOut,
                3 => PunchType::BreakIn,
                default => PunchType::Unknown,
            };
        }

        return PunchType::Unknown;
    }

    private static function resolveVerifyMethod(array $raw): VerifyMethod
    {
        $verifyMode = strtolower((string) ($raw['verify_mode'] ?? $raw['currentVerifyMode'] ?? ''));

        if (str_contains($verifyMode, 'face')) {
            return VerifyMethod::Face;
        }
        if (str_contains($verifyMode, 'fp') || str_contains($verifyMode, 'fingerprint')) {
            return VerifyMethod::Fingerprint;
        }
        if (str_contains($verifyMode, 'card')) {
            return VerifyMethod::Card;
        }
        if (str_contains($verifyMode, 'password') || str_contains($verifyMode, 'pw')) {
            return VerifyMethod::Password;
        }

        $punch = $raw['punch'] ?? null;
        if ($punch !== null) {
            return match ((int) $punch) {
                0, 1 => VerifyMethod::Fingerprint,
                2, 3 => VerifyMethod::Card,
                4 => VerifyMethod::Password,
                default => VerifyMethod::Fingerprint,
            };
        }

        return VerifyMethod::Fingerprint;
    }

    private static function parseTimestamp(array $raw): \DateTimeImmutable
    {
        $rawTs = $raw['timestamp'] ?? $raw['time'] ?? $raw['punch_time'] ?? $raw['record_time'] ?? null;

        if (is_string($rawTs) && $rawTs !== '') {
            try {
                return new \DateTimeImmutable($rawTs);
            } catch (\Throwable) {
                // fall through
            }
        }

        return new \DateTimeImmutable;
    }
}
