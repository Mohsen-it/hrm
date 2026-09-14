<?php

namespace Modules\AttendanceIntegration\Drivers\ZKTeco;

use Modules\AttendanceIntegration\Contracts\PunchNormalizerInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\DTOs\VerifyMethod;

class ZKTecoPunchNormalizer implements PunchNormalizerInterface
{
    public function normalize(array $rawPunch): NormalizedPunch
    {
        return self::normalizeStatic($rawPunch);
    }

    public static function normalizeStatic(array $rawPunch): NormalizedPunch
    {
        $deviceUserId = (string) ($rawPunch['user_id'] ?? '');
        $timestamp = self::parseTimestamp($rawPunch);

        $punchType = self::resolvePunchType($rawPunch);
        $verifyMethod = self::resolveVerifyMethod($rawPunch);

        return new NormalizedPunch(
            deviceUserId: $deviceUserId,
            timestamp: $timestamp,
            punchType: $punchType,
            verifyMethod: $verifyMethod,
            deviceSerial: $rawPunch['SN'] ?? $rawPunch['serial_number'] ?? null,
            uid: isset($rawPunch['uid']) ? (int) $rawPunch['uid'] : null,
            workCode: (int) ($rawPunch['work_code'] ?? 0),
            rawStatus: isset($rawPunch['status']) ? (string) $rawPunch['status'] : null,
            rawData: $rawPunch,
        );
    }

    public function getDriverName(): string
    {
        return 'zkteco';
    }

    private static function resolvePunchType(array $raw): PunchType
    {
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
        if ($explicit === 'extra') {
            return PunchType::Extra;
        }

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
        // NOTE: only the device `punch` column carries the verify method.
        // The `status` column is the IN/OUT state and must never be read as
        // a verify method (status 2/3 would falsely report 'card').
        // Observed fleet values: 0/1 = fingerprint, 2/3 = card, 4 = password,
        // 5/6/15 = face (iFace devices — absent on non-face terminals).
        $punch = $raw['punch'] ?? null;
        if ($punch === null) {
            return VerifyMethod::Fingerprint;
        }

        return match ((int) $punch) {
            0, 1 => VerifyMethod::Fingerprint,
            2, 3 => VerifyMethod::Card,
            4 => VerifyMethod::Password,
            5, 6, 15 => VerifyMethod::Face,
            default => VerifyMethod::Fingerprint,
        };
    }

    private static function parseTimestamp(array $raw): \DateTimeImmutable
    {
        $rawTs = $raw['timestamp'] ?? $raw['punch_time'] ?? $raw['record_time'] ?? null;

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
