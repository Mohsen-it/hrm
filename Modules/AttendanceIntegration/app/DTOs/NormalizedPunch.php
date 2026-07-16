<?php

namespace Modules\AttendanceIntegration\DTOs;

use Modules\AttendanceIntegration\Contracts\PunchNormalizerInterface;

final class NormalizedPunch
{
    public function __construct(
        public readonly string $deviceUserId,
        public readonly \DateTimeImmutable $timestamp,
        public readonly PunchType $punchType,
        public readonly VerifyMethod $verifyMethod,
        public readonly ?string $deviceSerial = null,
        public readonly ?int $uid = null,
        public readonly int $workCode = 0,
        public readonly ?string $rawStatus = null,
        public readonly array $rawData = [],
    ) {}

    public static function fromRaw(array $raw, PunchNormalizerInterface $normalizer): self
    {
        return $normalizer->normalize($raw);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            deviceUserId: $data['device_user_id'] ?? '',
            timestamp: isset($data['timestamp']) ? new \DateTimeImmutable($data['timestamp']) : new \DateTimeImmutable,
            punchType: PunchType::from($data['punch_type'] ?? 'unknown'),
            verifyMethod: VerifyMethod::from($data['verify_method'] ?? 'fingerprint'),
            deviceSerial: $data['device_serial'] ?? null,
            uid: $data['uid'] ?? null,
            workCode: $data['work_code'] ?? 0,
            rawStatus: $data['raw_status'] ?? null,
            rawData: $data['raw_data'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'device_user_id' => $this->deviceUserId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'punch_type' => $this->punchType->value,
            'verify_method' => $this->verifyMethod->value,
            'device_serial' => $this->deviceSerial,
            'uid' => $this->uid,
            'work_code' => $this->workCode,
            'raw_status' => $this->rawStatus,
        ];
    }
}
