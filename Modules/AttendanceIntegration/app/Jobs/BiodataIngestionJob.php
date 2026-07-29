<?php

namespace Modules\AttendanceIntegration\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\BiodataIngestionService;
use Modules\FingerprintDevices\Models\FingerprintDevice;

class BiodataIngestionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 300;

    public function __construct(
        private ?int $deviceId,
        private string $deviceSerial,
        private array $records,
        private string $correlationId,
    ) {}

    public function handle(
        BiodataIngestionService $ingestionService,
        AuditLogger $auditLogger,
    ): void {
        $startTime = microtime(true);

        $device = $this->deviceId ? FingerprintDevice::find($this->deviceId) : null;

        $stats = $ingestionService->ingest($device, $this->records, $this->correlationId);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $auditLogger->log('biodata_job_completed', [
            'correlation_id' => $this->correlationId,
            'device_serial' => $this->deviceSerial,
            'status' => 'completed',
            'context' => $stats,
            'duration_ms' => $durationMs,
        ]);

        Log::channel('biodata')->info('BIODATA_JOB_COMPLETED', [
            'correlation_id' => $this->correlationId,
            'device_serial' => $this->deviceSerial,
            'received' => count($this->records),
            'saved' => $stats['saved'],
            'duplicates' => $stats['duplicates'],
            'skipped' => $stats['skipped'],
            'errors' => count($stats['errors']),
            'duration_ms' => $durationMs,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $auditLogger = app(AuditLogger::class);
        $auditLogger->log('biodata_job_failed', [
            'correlation_id' => $this->correlationId,
            'device_serial' => $this->deviceSerial,
            'status' => 'failed',
            'context' => ['error' => $exception->getMessage()],
        ]);

        Log::channel('biodata')->error('BIODATA_JOB_FAILED', [
            'correlation_id' => $this->correlationId,
            'device_serial' => $this->deviceSerial,
            'error' => $exception->getMessage(),
        ]);
    }
}
