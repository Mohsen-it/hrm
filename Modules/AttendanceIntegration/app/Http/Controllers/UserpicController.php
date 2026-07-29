<?php

namespace Modules\AttendanceIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Models\DeviceAdapter;
use Modules\AttendanceIntegration\Parsers\UserpicParser;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\UserpicIngestionService;
use Modules\FingerprintDevices\Models\FingerprintDevice;

class UserpicController extends Controller
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
        private UserpicIngestionService $ingestionService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Handle POST /api/attendance-integration/push/userpic
     *
     * Receives USERPIC payloads (face photos) from ZKTeco devices.
     */
    public function handle(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $correlationId = $request->attributes->get('_correlation_id', uniqid('userpic-', true));

        $serialNumber = $request->input('SN')
            ?? $request->header('X-Device-Serial')
            ?? $request->header('SN')
            ?? $request->input('serial_number');

        $device = null;
        if ($serialNumber) {
            $adapter = $this->deviceRepository->findBySerial((string) $serialNumber);
            if ($adapter instanceof DeviceAdapter) {
                $device = $adapter->getRawModel();
            } elseif ($adapter instanceof FingerprintDevice) {
                $device = $adapter;
            }
        }

        $body = $request->input('Body', '');

        if (! is_string($body) || $body === '') {
            return response()->json([
                'success' => true,
                'message' => 'No USERPIC content received',
                'received' => 0,
                'saved' => 0,
                'skipped' => 0,
            ]);
        }

        $records = UserpicParser::parse($body);

        if (empty($records)) {
            return response()->json([
                'success' => true,
                'message' => 'No valid USERPIC records found in payload',
                'received' => 0,
                'saved' => 0,
                'skipped' => 0,
            ]);
        }

        Log::channel('biodata')->info('USERPIC_BATCH_RECEIVED', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'record_count' => count($records),
        ]);

        $this->auditLogger->log('userpic_received', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'status' => 'received',
            'context' => ['record_count' => count($records)],
        ]);

        $stats = $this->ingestionService->ingest($device, $records, $correlationId);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->auditLogger->log('userpic_completed', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'status' => 'completed',
            'context' => $stats,
            'duration_ms' => $durationMs,
        ]);

        Log::channel('biodata')->info('USERPIC_BATCH_COMPLETED', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'saved' => $stats['saved'],
            'skipped' => $stats['skipped'],
            'errors' => count($stats['errors']),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'USERPIC received',
            'correlation_id' => $correlationId,
            'received' => count($records),
            'saved' => $stats['saved'],
            'skipped' => $stats['skipped'],
            'errors' => $stats['errors'],
            'duration_ms' => $durationMs,
        ]);
    }
}
