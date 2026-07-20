<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\FingerprintDevices\Http\Requests\PushToDeviceRequest;
use Modules\FingerprintDevices\Jobs\PushFingerprintsToDeviceJob;
use Modules\FingerprintDevices\Jobs\PushUsersToDeviceJob;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Repositories\DeviceSyncLogRepository;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;
use Modules\FingerprintDevices\Services\DeviceFullSyncService;
use Modules\FingerprintDevices\Services\DevicePushService;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;
use Modules\Users\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DeviceFullSyncController — operator-facing end-to-end sync page.
 *
 * Renders the Vue page that lets an admin pick a device, choose which
 * artefacts to pull, and stream a structured result back. Also exposes a
 * JSON endpoint for callers that want to wire the same flow to a script.
 *
 * Since the bidirectional-sync feature, this controller also exposes
 * `push*` and `bidirectional*` methods that share the same SSE plumbing.
 */
class DeviceFullSyncController extends Controller
{
    public const PUSH_QUEUE_THRESHOLD = 200;

    public function __construct(
        private FingerprintDeviceService $deviceService,
        private FingerprintDeviceRepository $deviceRepository,
        private DeviceFullSyncService $syncService,
        private DevicePushService $pushService,
        private DeviceSyncLogRepository $syncLogRepository,
    ) {}

    /**
     * Render the full sync page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-fingerprint-devices');

        $devices = $this->deviceRepository
            ->query()
            ->with('deviceType')
            ->orderBy('name')
            ->get(['id', 'name', 'serial_number', 'ip_address', 'port', 'status', 'branch_id', 'device_type_id', 'last_pushed_at'])
            ->map(fn (FingerprintDevice $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'serial_number' => $d->serial_number,
                'ip_address' => $d->ip_address,
                'port' => $d->port,
                'status' => $d->status,
                'last_pushed_at' => $d->last_pushed_at?->toDateTimeString(),
                'can_push_users' => $d->can_push_users,
                'can_push_fingerprints' => $d->can_push_fingerprints,
                'can_push_face_photos' => $d->can_push_face_photos,
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
            'options.face_photos' => ['nullable', 'boolean'],
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
            'options.face_photos' => ['nullable', 'boolean'],
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

    // ============================================================
    // ============== PUSH (app → device) ==========================
    // ============================================================

    /**
     * Push users and/or fingerprints to a single device.
     *
     * - ≤ 200 users → synchronous, returns summary.
     * - > 200 users → dispatched to a Queue job, returns 202 + job_id.
     */
    public function push(PushToDeviceRequest $request): JsonResponse
    {
        $deviceId = $request->integer('device_id');
        $options = $request->pushOptions();

        // Estimate user count to decide between sync/queued.
        $userCount = $this->estimateUserCount($options);

        if ($userCount > self::PUSH_QUEUE_THRESHOLD) {
            return $this->dispatchPushJobs($deviceId, $options, $userCount);
        }

        $result = $this->pushService->push(
            deviceId: $deviceId,
            options: $options,
            userId: auth()->id(),
        );

        return response()->json([
            'success' => $result['success'],
            'queued' => false,
            'sync_log_id' => $result['sync_log_id'],
            'summary' => $result['summary'],
            'errors' => $result['errors'],
            'duration_seconds' => $result['duration_seconds'],
            'status' => $result['status'],
        ]);
    }

    /**
     * SSE stream of a push operation.
     */
    public function pushStream(PushToDeviceRequest $request): StreamedResponse
    {
        set_time_limit(0);

        $deviceId = $request->integer('device_id');
        $options = $request->pushOptions();

        $device = $this->deviceService->getDeviceById($deviceId);
        if (! $device) {
            return response()->stream(function () {
                $this->sendSse('error', ['message' => 'Device not found']);
            }, 404, ['Content-Type' => 'text/event-stream']);
        }

        // Estimate user count and warn if too large
        $userCount = $this->estimateUserCount($options);

        return response()->stream(function () use ($device, $options, $userCount) {
            if ($userCount > self::PUSH_QUEUE_THRESHOLD) {
                $this->sendSse('progress', [
                    'step' => 'push_users',
                    'status' => 'running',
                    'message' => __('fingerprint_devices.push_large_batch', ['count' => $userCount]),
                    'percent' => 5,
                    'data' => [],
                ]);
            }

            $this->sendSse('start', [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'message' => 'جاري بدء الدفع...',
            ]);

            $result = $this->pushService->push(
                deviceId: $device->id,
                options: $options,
                userId: auth()->id(),
                onProgress: function (string $step, string $status, string $message, int $percent, array $data) {
                    $this->sendSse('progress', [
                        'step' => $step,
                        'status' => $status,
                        'message' => $message,
                        'percent' => $percent,
                        'data' => $data,
                    ]);
                },
            );

            $this->sendSse('done', $result);

            @ob_end_flush();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }, 200, $this->sseHeaders());
    }

    /**
     * Push to all active devices.
     */
    public function pushAll(Request $request): JsonResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $options = $request->validate([
            'options' => ['required', 'array'],
            'options.push_users' => ['nullable', 'boolean'],
            'options.push_fingerprints' => ['nullable', 'boolean'],
            'options.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ])['options'];

        $devices = FingerprintDevice::query()
            ->where('status', '!=', 'deactivated')
            ->get();

        $results = [];
        $errors = [];
        $totals = ['pushed_users' => 0, 'pushed_fingerprints' => 0, 'failed_users' => 0, 'failed_fingerprints' => 0];

        foreach ($devices as $device) {
            try {
                $result = $this->pushService->push(
                    deviceId: $device->id,
                    options: $options,
                    userId: auth()->id(),
                );

                $results[] = [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'success' => $result['success'],
                    'sync_log_id' => $result['sync_log_id'],
                    'pushed_users' => $result['summary']['pushed_users'] ?? 0,
                    'pushed_fingerprints' => $result['summary']['pushed_fingerprints'] ?? 0,
                ];

                $totals['pushed_users'] += $result['summary']['pushed_users'] ?? 0;
                $totals['pushed_fingerprints'] += $result['summary']['pushed_fingerprints'] ?? 0;
                $totals['failed_users'] += $result['summary']['failed_users'] ?? 0;
                $totals['failed_fingerprints'] += $result['summary']['failed_fingerprints'] ?? 0;
            } catch (\Throwable $e) {
                $errors[] = [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'total_devices' => $devices->count(),
            'totals' => $totals,
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    /**
     * Bidirectional: pull then push in a single SSE stream.
     */
    public function bidirectional(Request $request): StreamedResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:fingerprint_devices,id'],
            'options' => ['array'],
            'options.pull' => ['array'],
            'options.pull.info' => ['nullable', 'boolean'],
            'options.pull.users' => ['nullable', 'boolean'],
            'options.pull.fingerprints' => ['nullable', 'boolean'],
            'options.pull.attendance' => ['nullable', 'boolean'],
            'options.push' => ['array'],
            'options.push.push_users' => ['nullable', 'boolean'],
            'options.push.push_fingerprints' => ['nullable', 'boolean'],
        ]);

        $device = $this->deviceService->getDeviceById((int) $data['device_id']);
        if (! $device) {
            return response()->stream(function () {
                $this->sendSse('error', ['message' => 'Device not found']);
            }, 404, ['Content-Type' => 'text/event-stream']);
        }

        return response()->stream(function () use ($device, $data) {
            $this->sendSse('start', [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'direction' => 'bidirectional',
            ]);

            $options = $data['options'] ?? [];
            $pullOptions = $options['pull'] ?? [];
            $pushOptions = $options['push'] ?? [];

            // 1. Pull
            if (! empty($pullOptions)) {
                $this->sendSse('progress', [
                    'step' => 'pull',
                    'status' => 'running',
                    'message' => 'جاري السحب...',
                    'percent' => 5,
                ]);
                $pullResult = $this->syncService->run($device, $pullOptions, function (string $step, string $status, string $message, int $percent, array $stepData) {
                    $this->sendSse('progress', [
                        'step' => 'pull_'.$step,
                        'status' => $status,
                        'message' => $message,
                        'percent' => (int) ($percent * 0.5), // scale to 0-50
                        'data' => $stepData,
                    ]);
                });
            }

            // 2. Push
            if (! empty($pushOptions)) {
                $this->sendSse('progress', [
                    'step' => 'push',
                    'status' => 'running',
                    'message' => 'جاري الدفع...',
                    'percent' => 55,
                ]);
                $pushResult = $this->pushService->push(
                    deviceId: $device->id,
                    options: $pushOptions,
                    userId: auth()->id(),
                    onProgress: function (string $step, string $status, string $message, int $percent, array $data) {
                        $this->sendSse('progress', [
                            'step' => $step,
                            'status' => $status,
                            'message' => $message,
                            'percent' => 50 + (int) ($percent * 0.5), // scale to 50-100
                            'data' => $data,
                        ]);
                    },
                );
            }

            $this->sendSse('done', [
                'success' => true,
                'pull' => $pullResult ?? null,
                'push' => $pushResult ?? null,
            ]);

            @ob_end_flush();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }, 200, $this->sseHeaders());
    }

    /**
     * Retry the failed records of a previous sync log.
     */
    public function retryFailed(Request $request, int $logId): JsonResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $maxRetries = (int) $request->input('max_retries', 1);

        $result = $this->pushService->retryFailed($logId, $maxRetries, auth()->id());

        return response()->json($result);
    }

    /**
     * Get the current status of a sync log (for polling from the UI when
     * a Queue job is in flight).
     */
    public function logStatus(int $logId): JsonResponse
    {
        $log = $this->syncLogRepository->findById($logId);
        if (! $log) {
            return response()->json(['error' => 'Log not found'], 404);
        }

        return response()->json([
            'id' => $log->id,
            'device_id' => $log->device_id,
            'direction' => $log->direction,
            'status' => $log->status,
            'started_at' => $log->started_at?->toDateTimeString(),
            'finished_at' => $log->finished_at?->toDateTimeString(),
            'duration_seconds' => $log->duration_seconds,
            'totals' => $log->totals,
            'errors' => $log->errors,
        ]);
    }

    // ============================================================
    // ============== helpers ======================================
    // ============================================================

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
     * @return array<string, string>
     */
    private function sseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    /**
     * Estimate the number of users that will be pushed, so we can decide
     * between a synchronous response and a queued job.
     */
    private function estimateUserCount(array $options): int
    {
        if (! empty($options['user_ids']) && is_array($options['user_ids'])) {
            return count($options['user_ids']);
        }

        if (! empty($options['branch_id'])) {
            return User::query()
                ->where('branch_id', (int) $options['branch_id'])
                ->where('is_active_employee', true)
                ->count();
        }

        // Default: all active employees
        return User::query()
            ->where('is_active_employee', true)
            ->count();
    }

    /**
     * Dispatch queue jobs for a large push operation.
     */
    private function dispatchPushJobs(int $deviceId, array $options, int $userCount): JsonResponse
    {
        $userIds = $this->resolveUserIdsForQueue($options);

        $syncLog = $this->syncLogRepository->create([
            'device_id' => $deviceId,
            'user_id' => auth()->id(),
            'direction' => 'push',
            'status' => 'running',
            'started_at' => now(),
            'totals' => ['queued' => true, 'user_count' => $userCount],
        ]);

        if (! empty($options['push_users'])) {
            PushUsersToDeviceJob::dispatch($deviceId, $userIds, $syncLog->id);
        }

        if (! empty($options['push_fingerprints'])) {
            PushFingerprintsToDeviceJob::dispatch($deviceId, $userIds, $syncLog->id);
        }

        return response()->json([
            'success' => true,
            'queued' => true,
            'sync_log_id' => $syncLog->id,
            'estimated_count' => $userCount,
            'message' => __('fingerprint_devices.queued_success'),
        ], 202);
    }

    /**
     * @return array<int, int>
     */
    private function resolveUserIdsForQueue(array $options): array
    {
        if (! empty($options['user_ids']) && is_array($options['user_ids'])) {
            return array_map('intval', $options['user_ids']);
        }

        if (! empty($options['branch_id'])) {
            return User::query()
                ->where('branch_id', (int) $options['branch_id'])
                ->where('is_active_employee', true)
                ->pluck('id')
                ->toArray();
        }

        return User::query()
            ->where('is_active_employee', true)
            ->pluck('id')
            ->toArray();
    }
}
