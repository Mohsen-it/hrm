<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $device = FingerprintDevice::where('serial_number', $serial)->first();
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
    public function reportResult(Request $request): JsonResponse
    {
        $request->validate([
            'SN' => 'required|string',
            'command_id' => 'required|integer',
            'status' => 'required|string',
            'result' => 'nullable|array',
            'error_message' => 'nullable|string',
        ]);

        $serial = $request->input('SN');
        $commandId = $request->integer('command_id');
        $status = $request->input('status');
        $errorMessage = $request->input('error_message');

        $this->commandService->reportResult($commandId, $status, $errorMessage);

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

        $device = FingerprintDevice::where('serial_number', $serial)->first();
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
}
