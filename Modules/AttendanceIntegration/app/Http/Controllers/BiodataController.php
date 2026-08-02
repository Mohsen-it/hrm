<?php

namespace Modules\AttendanceIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Models\DeviceAdapter;
use Modules\AttendanceIntegration\Parsers\BiodataParser;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\BiodataDebugPayloadService;
use Modules\AttendanceIntegration\Services\BiodataIngestionService;
use Modules\FingerprintDevices\Models\FingerprintDevice;

class BiodataController extends Controller
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
        private AuditLogger $auditLogger,
        private BiodataIngestionService $biodataIngestionService,
        private BiodataDebugPayloadService $debugPayloadService,
    ) {}

    /**
     * Handle POST /api/attendance-integration/push/biodata
     *
     * Receives BIODATA payloads from ZKTeco devices (face templates).
     * The device sends this when a new face is enrolled on the device.
     */
    public function handle(Request $request): JsonResponse
    {
        $correlationId = $request->attributes->get('_correlation_id', uniqid('biodata-', true));

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
                'message' => 'No BIODATA content received',
                'received' => 0,
                'saved' => 0,
                'duplicates' => 0,
                'skipped' => 0,
            ]);
        }

        try {
            $records = BiodataParser::parse($body);
        } catch (\Throwable $exception) {
            return $this->parsingFailure($body, (string) ($serialNumber ?? 'unknown'), $correlationId, $exception->getMessage());
        }

        if (empty($records)) {
            return $this->parsingFailure($body, (string) ($serialNumber ?? 'unknown'), $correlationId, 'No record contained a valid Pin field.');
        }

        Log::channel('biodata')->info('BIODATA_BATCH_RECEIVED', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'record_count' => count($records),
        ]);

        $this->auditLogger->log('biodata_received', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'status' => 'received',
            'context' => ['record_count' => count($records)],
        ]);

        $deviceId = null;
        if ($device instanceof FingerprintDevice) {
            $deviceId = $device->id;
        }

        $stats = $this->biodataIngestionService->ingest($device, $records, $correlationId);

        return response()->json([
            'success' => true,
            'message' => 'BIODATA received',
            'correlation_id' => $correlationId,
            'received' => count($records),
            'saved' => $stats['saved'],
            'duplicates' => $stats['duplicates'],
            'skipped' => $stats['skipped'],
        ]);
    }

    /** Handle malformed BIODATA without exposing the biometric payload in logs. */
    private function parsingFailure(string $body, string $serialNumber, string $correlationId, string $reason): JsonResponse
    {
        $debugPath = $this->debugPayloadService->save($body, $serialNumber, $correlationId, $reason);

        Log::channel('biodata')->error('BIODATA_PARSING_FAILED', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber,
            'reason' => $reason,
            'debug_file' => $debugPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'BIODATA could not be parsed; payload retained for diagnosis',
            'correlation_id' => $correlationId,
            'received' => 0,
            'saved' => 0,
            'duplicates' => 0,
            'skipped' => 0,
        ]);
    }
}
