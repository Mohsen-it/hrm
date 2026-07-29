<?php

namespace Modules\AttendanceIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Http\Requests\StoreDevicePunchRequest;
use Modules\AttendanceIntegration\Jobs\AttendanceIngestionJob;
use Modules\AttendanceIntegration\Jobs\BiodataIngestionJob;
use Modules\AttendanceIntegration\Models\DeviceAdapter;
use Modules\AttendanceIntegration\Parsers\BiodataParser;
use Modules\AttendanceIntegration\Parsers\OperlogParser;
use Modules\AttendanceIntegration\Parsers\OptionsParser;
use Modules\AttendanceIntegration\Parsers\UserinfoParser;
use Modules\AttendanceIntegration\Parsers\UserpicParser;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\AttendanceIntegration\Services\UserpicIngestionService;
use Modules\FingerprintDevices\Models\FingerprintDevice;

class DevicePushController extends Controller
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
        private DeviceAdapterResolver $adapterResolver,
        private AuditLogger $auditLogger,
    ) {}

    public function handle(StoreDevicePunchRequest $request): JsonResponse
    {
        $correlationId = $request->input('_correlation_id', $request->attributes->get('_correlation_id', 'N/A'));

        $serialNumber = $request->input('SN')
            ?? $request->header('X-Device-Serial')
            ?? $request->header('SN')
            ?? $request->input('serial_number');

        $device = null;
        if ($serialNumber) {
            $device = $this->deviceRepository->findBySerial((string) $serialNumber);
        }

        $body = $request->input('Body', '');

        // Route by explicit _table parameter (from Python ADMS server)
        $tableParam = $request->input('_table', $request->query('table', ''));
        $tableUpper = strtoupper((string) $tableParam);

        if ($tableUpper === 'OPTIONS') {
            return $this->handleOptions($device, $serialNumber, $body, $correlationId);
        }

        if ($tableUpper === 'OPERLOG') {
            return $this->handleOperlog($device, $serialNumber, $body, $correlationId);
        }

        if ($tableUpper === 'USERINFO') {
            return $this->handleUserinfo($device, $serialNumber, $body, $correlationId);
        }

        // Route by content detection
        if (is_string($body) && BiodataParser::isBiodata($body)) {
            return $this->handleBiodata($request, $device, $serialNumber, $correlationId);
        }

        if (is_string($body) && UserpicParser::isUserpic($body)) {
            return $this->handleUserpic($request, $device, $serialNumber, $correlationId);
        }

        if (is_string($body) && OptionsParser::isOptions($body)) {
            return $this->handleOptions($device, $serialNumber, $body, $correlationId);
        }

        if (strtolower((string) $tableUpper) === 'BIODATA') {
            return $this->handleBiodata($request, $device, $serialNumber, $correlationId);
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

        $normalizedPunches = [];
        foreach ($rows as $index => $row) {
            try {
                $row['_driver'] = $driver;
                $normalized = $normalizer->normalize($row);
                $normalizedPunches[] = $normalized->toArray();
            } catch (\Throwable $e) {
                Log::channel('attendance_push')->warning('device_push_normalize_failed', [
                    'row' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($normalizedPunches)) {
            return response()->json([
                'success' => true,
                'message' => 'No valid attendance records',
                'received' => count($rows),
                'processed' => 0,
                'skipped' => 0,
                'duplicates' => 0,
            ]);
        }

        $deviceId = $device instanceof AttendanceDeviceInterface ? $device->getId() : null;

        AttendanceIngestionJob::dispatch(
            $deviceId,
            (string) ($serialNumber ?? 'unknown'),
            $normalizedPunches,
            $correlationId,
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance data received',
            'correlation_id' => $correlationId,
            'received' => count($rows),
            'processed' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'dead_lettered' => 0,
            'queued' => true,
        ]);
    }

    private function handleBiodata(
        Request $request,
        ?AttendanceDeviceInterface $device,
        ?string $serialNumber,
        string $correlationId,
    ): JsonResponse {
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

        Log::channel('biodata')->info('BIODATA_BATCH_RECEIVED_VIA_PUSH', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'record_count' => count($records),
        ]);

        $fingerprintDevice = null;
        if ($device instanceof DeviceAdapter) {
            $fingerprintDevice = $device->getRawModel();
        } elseif ($device instanceof FingerprintDevice) {
            $fingerprintDevice = $device;
        } elseif ($serialNumber) {
            $fingerprintDevice = FingerprintDevice::where('serial_number', $serialNumber)->first();
        }

        BiodataIngestionJob::dispatch(
            $fingerprintDevice?->id,
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

    private function handleUserpic(
        Request $request,
        ?AttendanceDeviceInterface $device,
        ?string $serialNumber,
        string $correlationId,
    ): JsonResponse {
        $startTime = microtime(true);

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

        Log::channel('biodata')->info('USERPIC_BATCH_RECEIVED_VIA_PUSH', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'record_count' => count($records),
        ]);

        $fingerprintDevice = null;
        if ($device instanceof DeviceAdapter) {
            $fingerprintDevice = $device->getRawModel();
        } elseif ($device instanceof FingerprintDevice) {
            $fingerprintDevice = $device;
        } elseif ($serialNumber) {
            $fingerprintDevice = FingerprintDevice::where('serial_number', $serialNumber)->first();
        }

        $ingestionService = app(UserpicIngestionService::class);
        $stats = $ingestionService->ingest($fingerprintDevice, $records, $correlationId);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel('biodata')->info('USERPIC_BATCH_COMPLETED_VIA_PUSH', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'saved' => $stats['saved'],
            'skipped' => $stats['skipped'],
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

    private function handleOptions(
        ?AttendanceDeviceInterface $device,
        ?string $serialNumber,
        string $body,
        string $correlationId,
    ): JsonResponse {
        if (! is_string($body) || trim($body) === '') {
            return response()->json([
                'success' => true,
                'message' => 'No OPTIONS content received',
            ]);
        }

        $parsed = OptionsParser::parse($body);

        Log::channel('biodata')->info('OPTIONS_RECEIVED', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'device_name' => $parsed['device_name'],
            'firmware_version' => $parsed['firmware_version'],
        ]);

        // Update device capabilities in DB
        $fingerprintDevice = $this->resolveFingerprintDevice($device, $serialNumber);
        if ($fingerprintDevice) {
            $caps = $fingerprintDevice->capabilities ?? [];
            if ($parsed['firmware_version']) {
                $caps['firmware'] = $parsed['firmware_version'];
            }
            if ($parsed['push_version']) {
                $caps['push_version'] = $parsed['push_version'];
            }
            if ($parsed['platform']) {
                $caps['platform'] = $parsed['platform'];
            }
            if ($parsed['user_count'] !== null) {
                $caps['user_count'] = $parsed['user_count'];
            }
            if ($parsed['fingerprint_count'] !== null) {
                $caps['fp_count'] = $parsed['fingerprint_count'];
            }
            if ($parsed['face_count'] !== null) {
                $caps['face_count'] = $parsed['face_count'];
            }
            if ($parsed['attendance_count'] !== null) {
                $caps['att_count'] = $parsed['attendance_count'];
            }
            if ($parsed['photo_count'] !== null) {
                $caps['photo_count'] = $parsed['photo_count'];
            }
            if ($parsed['bio_version']) {
                $caps['bio_version'] = $parsed['bio_version'];
            }
            $caps = array_merge($caps, $parsed['capabilities']);

            $fingerprintDevice->update([
                'capabilities' => $caps,
                'last_seen_at' => now(),
                'status' => 'online',
            ]);

            if ($parsed['device_name'] && ! $fingerprintDevice->name) {
                $fingerprintDevice->update(['name' => $parsed['device_name']]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'OPTIONS received',
            'correlation_id' => $correlationId,
            'parsed' => $parsed,
        ]);
    }

    private function handleOperlog(
        ?AttendanceDeviceInterface $device,
        ?string $serialNumber,
        string $body,
        string $correlationId,
    ): JsonResponse {
        if (! is_string($body) || trim($body) === '') {
            return response()->json([
                'success' => true,
                'message' => 'No OPERLOG content received',
                'received' => 0,
            ]);
        }

        $records = OperlogParser::parse($body);

        Log::info('OPERLOG_RECEIVED', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'record_count' => count($records),
        ]);

        $this->auditLogger->log('operlog_received', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'status' => 'received',
            'context' => ['record_count' => count($records)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OPERLOG received',
            'correlation_id' => $correlationId,
            'received' => count($records),
        ]);
    }

    private function handleUserinfo(
        ?AttendanceDeviceInterface $device,
        ?string $serialNumber,
        string $body,
        string $correlationId,
    ): JsonResponse {
        if (! is_string($body) || trim($body) === '') {
            return response()->json([
                'success' => true,
                'message' => 'No USERINFO content received',
                'received' => 0,
            ]);
        }

        $records = UserinfoParser::parse($body);

        Log::info('USERINFO_RECEIVED', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'record_count' => count($records),
        ]);

        $this->auditLogger->log('userinfo_received', [
            'correlation_id' => $correlationId,
            'device_serial' => $serialNumber ?? 'unknown',
            'status' => 'received',
            'context' => ['record_count' => count($records)],
        ]);

        // Update device user count
        $fingerprintDevice = $this->resolveFingerprintDevice($device, $serialNumber);
        if ($fingerprintDevice && count($records) > 0) {
            $caps = $fingerprintDevice->capabilities ?? [];
            $caps['device_user_count'] = count($records);
            $fingerprintDevice->update([
                'capabilities' => $caps,
                'user_count' => count($records),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'USERINFO received',
            'correlation_id' => $correlationId,
            'received' => count($records),
        ]);
    }

    private function resolveFingerprintDevice(?AttendanceDeviceInterface $device, ?string $serialNumber): ?FingerprintDevice
    {
        if ($device instanceof DeviceAdapter) {
            return $device->getRawModel();
        }
        if ($device instanceof FingerprintDevice) {
            return $device;
        }
        if ($serialNumber) {
            return FingerprintDevice::where('serial_number', $serialNumber)->first();
        }

        return null;
    }
}
