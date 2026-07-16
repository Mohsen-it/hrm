<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;
use Modules\FingerprintDevices\Services\DeviceFullSyncService;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DeviceFullSyncController — operator-facing end-to-end sync page.
 *
 * Renders the Vue page that lets an admin pick a device, choose which
 * artefacts to pull, and stream a structured result back. Also exposes a
 * JSON endpoint for callers that want to wire the same flow to a script.
 */
class DeviceFullSyncController extends Controller
{
    public function __construct(
        private FingerprintDeviceService $deviceService,
        private FingerprintDeviceRepository $deviceRepository,
        private DeviceFullSyncService $syncService,
    ) {}

    /**
     * Render the full sync page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-fingerprint-devices');

        $devices = $this->deviceRepository
            ->query()
            ->orderBy('name')
            ->get(['id', 'name', 'serial_number', 'ip_address', 'port', 'status', 'branch_id'])
            ->map(fn (FingerprintDevice $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'serial_number' => $d->serial_number,
                'ip_address' => $d->ip_address,
                'port' => $d->port,
                'status' => $d->status,
            ])
            ->values();

        $selectedId = (int) $request->query('device_id', $devices->first()['id'] ?? 0);

        $selected = $selectedId > 0
            ? $devices->firstWhere('id', $selectedId)
            : null;

        return Inertia::render('FingerprintDevices/Sync', [
            'devices' => fn () => $devices,
            'selectedDeviceId' => fn () => $selectedId,
            'selectedDevice' => fn () => $selected,
            'lastResult' => fn () => session('sync_result'),
        ]);
    }

    /**
     * Perform a sync run and return a JSON payload.
     */
    public function sync(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:fingerprint_devices,id'],
            'options' => ['array'],
            'options.info' => ['nullable', 'boolean'],
            'options.users' => ['nullable', 'boolean'],
            'options.fingerprints' => ['nullable', 'boolean'],
            'options.attendance' => ['nullable', 'boolean'],
            'options.clear_local_cache' => ['nullable', 'boolean'],
        ]);

        $device = $this->deviceService->getDeviceById((int) $data['device_id']);

        if (! $device) {
            return response()->json(['success' => false, 'error' => 'Device not found'], 404);
        }

        $result = $this->syncService->run($device, $data['options'] ?? []);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        }

        return redirect()
            ->route('fingerprint-devices.sync', ['device_id' => $device->id])
            ->with('sync_result', $result);
    }

    /**
     * Stream sync progress via Server-Sent Events (SSE).
     */
    public function syncStream(Request $request): StreamedResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:fingerprint_devices,id'],
            'options' => ['array'],
            'options.info' => ['nullable', 'boolean'],
            'options.users' => ['nullable', 'boolean'],
            'options.fingerprints' => ['nullable', 'boolean'],
            'options.attendance' => ['nullable', 'boolean'],
            'options.clear_local_cache' => ['nullable', 'boolean'],
        ]);

        $device = $this->deviceService->getDeviceById((int) $data['device_id']);

        if (! $device) {
            return response()->stream(function () {
                $this->sendSse('error', ['message' => 'Device not found']);
            }, 404, ['Content-Type' => 'text/event-stream']);
        }

        return response()->stream(function () use ($device, $data) {
            $this->sendSse('start', [
                'device_name' => $device->name,
                'message' => 'جاري بدء المزامنة...',
            ]);

            $result = $this->syncService->run($device, $data['options'] ?? [], function (string $step, string $status, string $message, int $percent, array $stepData) {
                $this->sendSse('progress', [
                    'step' => $step,
                    'status' => $status,
                    'message' => $message,
                    'percent' => $percent,
                    'data' => $stepData,
                ]);
            });

            $this->sendSse('done', $result);

            @ob_end_flush();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send a single SSE event to the client.
     */
    private function sendSse(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
        @ob_flush();
        @flush();
    }

    /**
     * Sync all active devices at once.
     */
    public function syncAll(): JsonResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $devices = FingerprintDevice::query()
            ->where('status', '!=', 'deactivated')
            ->get();

        $results = [];
        $totalUsers = 0;
        $totalAttendance = 0;
        $totalFingerprints = 0;
        $errors = [];

        foreach ($devices as $device) {
            try {
                $result = $this->syncService->run($device, [
                    'info' => false,
                    'users' => true,
                    'fingerprints' => false,
                    'attendance' => true,
                    'clear_local_cache' => false,
                ]);

                $results[] = [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'success' => true,
                    'users_matched' => $result['totals']['users_matched'] ?? 0,
                    'attendance_pulled' => $result['totals']['attendance_pulled'] ?? 0,
                ];

                $totalUsers += $result['totals']['users_matched'] ?? 0;
                $totalAttendance += $result['totals']['attendance_pulled'] ?? 0;
            } catch (\Throwable $e) {
                $errors[] = [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'error' => $e->getMessage(),
                ];

                Log::warning('syncAll: failed for device', [
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'devices_synced' => $devices->count(),
            'total_users_matched' => $totalUsers,
            'total_attendance_pulled' => $totalAttendance,
            'results' => $results,
            'errors' => $errors,
        ]);
    }
}
