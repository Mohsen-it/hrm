<?php

namespace Modules\AttendanceIntegration\DTOs;

final class SyncResult
{
    public function __construct(
        public readonly int $device_id,
        public readonly string $device_name,
        public readonly string $serial_number,
        public readonly array $steps,
        public readonly array $totals,
        public readonly array $errors,
        public readonly float $durationSeconds,
        public readonly string $startedAt,
        public readonly string $finishedAt,
    ) {}

    public static function empty(int $deviceId, string $deviceName, string $serialNumber): self
    {
        return new self(
            device_id: $deviceId,
            device_name: $deviceName,
            serial_number: $serialNumber,
            steps: [],
            totals: [
                'users_on_device' => 0,
                'users_matched' => 0,
                'users_unmatched' => 0,
                'fingerprints_pulled' => 0,
                'fingerprints_saved' => 0,
                'attendance_pulled' => 0,
                'attendance_saved' => 0,
                'attendance_sessions' => 0,
            ],
            errors: [],
            durationSeconds: 0,
            startedAt: now()->toDateTimeString(),
            finishedAt: now()->toDateTimeString(),
        );
    }

    public function toArray(): array
    {
        return [
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'serial_number' => $this->serial_number,
            'steps' => $this->steps,
            'totals' => $this->totals,
            'errors' => $this->errors,
            'duration_seconds' => $this->durationSeconds,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
        ];
    }
}
