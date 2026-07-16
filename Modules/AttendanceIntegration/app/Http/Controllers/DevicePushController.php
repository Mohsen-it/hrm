<?php

namespace Modules\AttendanceIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Exceptions\DuplicatePunchException;
use Modules\AttendanceIntegration\Http\Requests\StoreDevicePunchRequest;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\AttendanceIntegration\Services\PunchIngestionService;

class DevicePushController extends Controller
{
    private const MAX_RETRY_ATTEMPTS = 3;

    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
        private PunchIngestionService $ingestionService,
        private DeviceAdapterResolver $adapterResolver,
        private AuditLogger $auditLogger,
    ) {}

    public function handle(StoreDevicePunchRequest $request): JsonResponse
    {
        $startTime = microtime(true);
        $correlationId = $request->input('_correlation_id', $request->attributes->get('_correlation_id', 'N/A'));

        $serialNumber = $request->input('SN')
            ?? $request->header('X-Device-Serial')
            ?? $request->header('SN')
            ?? $request->input('serial_number');

        $device = null;
        if ($serialNumber) {
            $device = $this->deviceRepository->findBySerial((string) $serialNumber);
        }

        if (! $device) {
            $devices = $this->deviceRepository->getOnline();
            foreach ($devices as $d) {
                $device = $d;
                break;
            }
        }

        $driver = $device ? $device->getDriverName() : config('attendanceintegration.default_driver', 'zkteco');
        $parser = $this->adapterResolver->getParser($driver);
        $normalizer = $this->adapterResolver->getNormalizer($driver);
        $rows = $parser->parse($request->validated(), $request->headers->all());

        if (empty($rows)) {
            return response()->json([
                'success' => true,
                'message' => 'No attendance records found',
                'received' => 0, 'processed' => 0, 'skipped' => 0, 'duplicates' => 0,
            ]);
        }

        $this->auditLogger->logPushReceived($correlationId, (string) ($serialNumber ?? 'unknown'), count($rows));

        $processed = 0;
        $skipped = 0;
        $duplicates = 0;
        $deadLettered = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $row['_driver'] = $driver;
                $normalized = $normalizer->normalize($row);
                $result = $this->ingestWithRetry($device, $normalized, $correlationId);

                if ($result === 'duplicate') {
                    $duplicates++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } elseif ($result === 'dead_letter') {
                    $deadLettered++;
                } elseif ($result === 'error') {
                    $skipped++;
                    $errors[] = "Row {$index}: failed";
                } else {
                    $processed++;
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row {$index}: {$e->getMessage()}";
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->auditLogger->logPushCompleted($correlationId, (string) ($serialNumber ?? 'unknown'), $processed, $skipped, $duplicates, count($rows), $durationMs);

        return response()->json([
            'success' => true,
            'message' => 'Attendance data received',
            'correlation_id' => $correlationId,
            'received' => count($rows), 'processed' => $processed,
            'skipped' => $skipped, 'duplicates' => $duplicates,
            'dead_lettered' => $deadLettered, 'duration_ms' => $durationMs,
            'errors' => $errors,
        ]);
    }

    private function ingestWithRetry(?AttendanceDeviceInterface $device, $normalized, string $correlationId): string
    {
        $attempt = 0;
        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            try {
                $session = $this->ingestionService->ingest($device, $normalized, $correlationId);
                if ($session === null) {
                    return 'skipped';
                }

                return 'processed';
            } catch (DuplicatePunchException $e) {
                return 'duplicate';
            } catch (\Throwable $e) {
                $attempt++;
                Log::channel('attendance_push')->warning('device_push_retry', ['attempt' => $attempt, 'error' => $e->getMessage()]);
                if ($attempt >= self::MAX_RETRY_ATTEMPTS) {
                    return 'dead_letter';
                }
                usleep(100_000 * $attempt);
            }
        }

        return 'error';
    }
}
