<?php

namespace Modules\AttendanceIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\Services\PunchIngestionService;

class DeadLetterPunchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        private int $deviceId,
        private string $deviceSerial,
        private array $normalizedPunch,
        private string $correlationId,
    ) {}

    public function handle(
        DeviceRepositoryInterface $deviceRepository,
        PunchIngestionService $ingestionService,
    ): void {
        $device = $deviceRepository->findById($this->deviceId);

        if (! $device) {
            Log::channel('attendance_push')->error('dead_letter_device_not_found', [
                'device_id' => $this->deviceId,
                'correlation_id' => $this->correlationId,
            ]);

            return;
        }

        $punch = NormalizedPunch::fromArray($this->normalizedPunch);

        try {
            $session = $ingestionService->ingest($device, $punch);

            if ($session) {
                Log::channel('attendance_push')->info('dead_letter_recovered', [
                    'device_id' => $this->deviceId,
                    'session_id' => $session->id,
                    'correlation_id' => $this->correlationId,
                ]);
            } else {
                Log::channel('attendance_push')->warning('dead_letter_skipped', [
                    'device_id' => $this->deviceId,
                    'correlation_id' => $this->correlationId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('attendance_push')->error('dead_letter_failed', [
                'device_id' => $this->deviceId,
                'correlation_id' => $this->correlationId,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->fail($e);
            } else {
                $this->release($this->backoff * $this->attempts());
            }
        }
    }
}
