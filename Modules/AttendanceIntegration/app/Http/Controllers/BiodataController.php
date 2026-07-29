<?php

namespace Modules\AttendanceIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Jobs\BiodataIngestionJob;
use Modules\AttendanceIntegration\Models\DeviceAdapter;
use Modules\AttendanceIntegration\Parsers\BiodataParser;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\FingerprintDevices\Models\FingerprintDevice;

class BiodataController extends Controller
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
        private AuditLogger $auditLogger,
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

        $records = BiodataParser::parse($body);

        if (empty($records)) {
            return response()->json([
                'success' => true,
                'message' => 'No valid BIODATA records found in payload',
                'received' => 0,
                'saved' => 0,
                'duplicates' => 0,
                'skipped' => 0,
            ]);
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

        BiodataIngestionJob::dispatch(
            $deviceId,
            (string) ($serialNumber ?? 'unknown'),
            $records,
            $correlationId,
        );

        return response()->json([
            'success' => true,
            'message' => 'BIODATA received',
            'correlation_id' => $correlationId,
            'received' => count($records),
            'saved' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'queued' => true,
        ]);
    }
}
