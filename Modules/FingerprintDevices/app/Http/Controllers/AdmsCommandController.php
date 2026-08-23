<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Repositories\DeviceCommandRepository;
use Modules\FingerprintDevices\Services\DeviceCommandService;

class AdmsCommandController extends Controller
{
    public function __construct(
        protected DeviceCommandService $commandService,
        protected DeviceCommandRepository $commandRepo,
    ) {}

    /**
     * GET /api/adms/commands?SN=xxx
     *
     * Called by the Python ADMS server when a device polls for pending commands.
     * Returns the next batch of pending commands (claimed as 'sending').
     */
    public function fetchCommands(Request $request): JsonResponse
    {
        $serial = $request->input('SN');
        if (! $serial) {
            return response()->json(['success' => false, 'error' => 'SN required'], 400);
        }

        $device = $this->findDeviceBySerial($serial);
        if (! $device) {
            Log::warning('ADMS_COMMANDS_UNKNOWN_DEVICE', ['SN' => $serial]);

            return response()->json(['success' => false, 'error' => 'Device not found'], 404);
        }

        $commands = $this->commandService->fetchPendingCommands($device->id, 5);

        return response()->json([
            'success' => true,
            'commands' => $commands,
            'count' => count($commands),
        ]);
    }

    /**
     * POST /api/adms/commands/result
     *
     * Called by the Python ADMS server to report command execution results.
     */
    /**
     * POST /api/adms/commands/sending
     *
     * Called by the Python ADMS server when commands are actually served to a device.
     */
    public function markSending(Request $request): JsonResponse
    {
        $request->validate([
            'SN' => 'required|string',
            'command_ids' => 'required|array',
            'command_ids.*' => 'integer',
        ]);

        $serial = $request->input('SN');
        $ids = $request->input('command_ids', []);

        $device = $this->findDeviceBySerial($serial);
        if (! $device) {
            return response()->json(['success' => false, 'error' => 'Device not found'], 404);
        }

        $marked = $this->commandRepo->markSendingForDevice($device->id, $ids);

        return response()->json(['success' => true, 'marked' => $marked]);
    }

    public function reportResult(Request $request): JsonResponse
    {
        $request->validate([
            'SN' => 'required|string',
            'command_id' => 'required|integer',
            'status' => ['required', 'string', Rule::in(['completed', 'success', 'failed'])],
            'result' => 'nullable|array',
            'error_message' => 'nullable|string',
        ]);

        $serial = $request->input('SN');
        $commandId = $request->integer('command_id');
        $status = $request->input('status');
        $errorMessage = $request->input('error_message');

        $device = $this->findDeviceBySerial($serial);
        if (! $device) {
            return response()->json(['success' => false, 'error' => 'Device not found'], 404);
        }

        $reported = $this->commandService->reportResult(
            $commandId,
            $device->id,
            $status,
            $errorMessage,
        );

        // Unknown/stale command results (e.g. commands deleted after a queue
        // reset, or acknowledgements for a previous table lifecycle) must be
        // ACKed positively: the ADMS outbox retries every non-2xx response
        // forever, so a 404 here would poison the result pipeline.
        if (! $reported) {
            Log::warning('ADMS_COMMAND_RESULT_IGNORED', [
                'SN' => $serial,
                'command_id' => $commandId,
                'status' => $status,
            ]);

            return response()->json([
                'success' => true,
                'ignored' => true,
                'reason' => 'Command not found for device',
            ]);
        }

        Log::info('ADMS_COMMAND_RESULT', [
            'SN' => $serial,
            'command_id' => $commandId,
            'status' => $status,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/adms/heartbeat
     *
     * Called by the Python ADMS server when a device sends a heartbeat/registration.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $serial = $request->input('SN');
        $ip = $request->input('ip', '');
        $info = $request->input('info', []);

        if (! $serial) {
            return response()->json(['success' => false, 'error' => 'SN required'], 400);
        }

        $device = $this->findDeviceBySerial($serial);
        if (! $device) {
            Log::info('ADMS_HEARTBEAT_UNKNOWN_DEVICE', ['SN' => $serial, 'ip' => $ip]);

            return response()->json([
                'success' => true,
                'message' => 'Device not registered in system',
            ]);
        }

        $this->commandService->handleHeartbeat($device->id, $ip, $info);

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/adms/commands/{deviceId}/stats
     *
     * Get queue statistics for a device.
     */
    public function stats(int $deviceId): JsonResponse
    {
        $device = FingerprintDevice::find($deviceId);
        if (! $device) {
            return response()->json(['success' => false, 'error' => 'Device not found'], 404);
        }

        $stats = $this->commandService->getQueueStats($deviceId);

        return response()->json([
            'success' => true,
            'device' => ['id' => $device->id, 'name' => $device->name, 'serial_number' => $device->serial_number],
            'queue' => $stats,
        ]);
    }

    /**
     * DELETE /api/adms/commands/{deviceId}/pending
     *
     * Cancel all pending commands for a device.
     */
    public function cancelPending(int $deviceId): JsonResponse
    {
        $device = FingerprintDevice::find($deviceId);
        if (! $device) {
            return response()->json(['success' => false, 'error' => 'Device not found'], 404);
        }

        $cancelled = $this->commandRepo->cancelPendingForDevice($deviceId);

        return response()->json([
            'success' => true,
            'cancelled' => $cancelled,
        ]);
    }

    /** Find a device by serial number. */
    private function findDeviceBySerial(string $serial): ?FingerprintDevice
    {
        // The database cache store adds an extra query (and can contend with
        // device polls), so production polling resolves through the indexed
        // device query. Other stores may safely retain only the scalar ID.
        if (config('cache.default') !== 'database') {
            $cacheKey = 'adms:device:serial:'.$serial;
            $cachedDeviceId = Cache::get($cacheKey);

            if (is_int($cachedDeviceId)) {
                return FingerprintDevice::find($cachedDeviceId);
            }

            $device = FingerprintDevice::query()
                ->where('serial_number', $serial)
                ->first();

            if ($device) {
                Cache::put($cacheKey, $device->id, now()->addMinutes(10));
            }

            return $device;
        }

        return FingerprintDevice::query()
            ->where('serial_number', $serial)
            ->first();
    }
}
