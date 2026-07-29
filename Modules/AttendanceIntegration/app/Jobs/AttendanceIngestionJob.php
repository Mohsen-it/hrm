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
use Modules\AttendanceIntegration\Exceptions\DuplicatePunchException;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\PunchIngestionService;

class AttendanceIngestionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 600;

    public function __construct(
        private ?int $deviceId,
        private string $deviceSerial,
        private array $normalizedPunches,
        private string $correlationId,
    ) {}

    public function handle(
        DeviceRepositoryInterface $deviceRepository,
        PunchIngestionService $ingestionService,
        AuditLogger $auditLogger,
    ): void {
        $startTime = microtime(true);

        $device = $this->deviceId ? $deviceRepository->findById($this->deviceId) : null;

        $processed = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($this->normalizedPunches as $index => $punchData) {
            try {
                $punch = NormalizedPunch::fromArray($punchData);
                $session = $ingestionService->ingest($device, $punch, $this->correlationId);

                if ($session === null) {
                    $skipped++;
                } else {
                    $processed++;
                }
            } catch (DuplicatePunchException) {
                $duplicates++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row {$index}: {$e->getMessage()}";
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $auditLogger->logPushCompleted(
            $this->correlationId,
            $this->deviceSerial,
            $processed,
            $skipped,
            $duplicates,
            count($this->normalizedPunches),
            $durationMs,
        );

        Log::channel('attendance_push')->info('attendance_job_completed', [
            'correlation_id' => $this->correlationId,
            'device_serial' => $this->deviceSerial,
            'received' => count($this->normalizedPunches),
            'processed' => $processed,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'errors' => count($errors),
            'duration_ms' => $durationMs,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $auditLogger = app(AuditLogger::class);
        $auditLogger->logPushFailed(
            $this->correlationId,
            $this->deviceSerial,
            $exception->getMessage(),
        );

        Log::channel('attendance_push')->error('attendance_job_failed', [
            'correlation_id' => $this->correlationId,
            'device_serial' => $this->deviceSerial,
            'error' => $exception->getMessage(),
        ]);
    }
}
