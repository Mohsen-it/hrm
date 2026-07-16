<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Http\Requests\StoreRawAttendanceLogRequest;
use Modules\Attendance\Http\Resources\RawAttendanceLogResource;
use Modules\Attendance\Jobs\ProcessRawLogsChunk;
use Modules\Attendance\Services\RawAttendanceLogService;
use Modules\Users\Services\UserService;

/**
 * RawLogsController — manage the unprocessed / processed device punches.
 *
 * The page is mostly read-only: operators browse the raw log feed and, when
 * needed, fire a queued chunk job that turns unprocessed rows into sessions.
 */
class RawLogsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private RawAttendanceLogService $rawLogService,
        private UserService $userService,
    ) {}

    /**
     * Display a listing of raw attendance logs.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-attendance');

        $filters = $this->cleanFilters($request->only([
            'search', 'user_id', 'device_id', 'device_user_id',
            'punch_type', 'verify_type', 'source', 'processed',
            'from', 'to',
        ]));

        return Inertia::render('Attendance/RawLogs/Index', [
            'filters' => fn () => $filters,
            'logs' => fn () => RawAttendanceLogResource::collection(
                $this->rawLogService->getAllLogs($filters, 25)
            )->response($request)->getData(true),
            'users' => fn () => $this->userService->getActiveUsers()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'employee_code' => $u->employee_code]),
        ]);
    }

    /**
     * Display the specified raw log.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-attendance');

        $log = $this->rawLogService->getLogById($id);
        if (! $log) {
            abort(404);
        }

        return Inertia::render('Attendance/RawLogs/Show', [
            'log' => fn () => (new RawAttendanceLogResource($log))->resolve(),
        ]);
    }

    /**
     * Store a single manually-entered raw log.
     */
    public function store(StoreRawAttendanceLogRequest $request): RedirectResponse
    {
        $this->authorize('create-attendance');

        $log = $this->rawLogService->createLog($request->validated());

        return redirect()->route('attendance.raw-logs.show', $log->id)
            ->with('success', __('attendance.raw_log_created_successfully'));
    }

    /**
     * Dispatch a queued job that processes unprocessed logs in chunks.
     */
    public function processAll(Request $request): RedirectResponse
    {
        $this->authorize('edit-attendance');

        $data = $request->validate([
            'chunk_size' => ['nullable', 'integer', 'min:50', 'max:2000'],
        ]);

        ProcessRawLogsChunk::dispatch((int) ($data['chunk_size'] ?? 200));

        return redirect()->back()
            ->with('success', __('attendance.process_raw_logs_dispatched'));
    }

    /**
     * Mark a single log as processed (without correlating it).
     */
    public function markProcessed(int $id): RedirectResponse
    {
        $this->authorize('edit-attendance');

        $log = $this->rawLogService->getLogById($id);
        if (! $log) {
            abort(404);
        }

        $this->rawLogService->markProcessed([$log->id]);

        return redirect()->back()
            ->with('success', __('attendance.raw_log_marked_processed'));
    }

    /**
     * Soft delete a raw log.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-attendance');

        $log = $this->rawLogService->getLogById($id);
        if (! $log) {
            abort(404);
        }

        $this->rawLogService->deleteLog($log);

        return redirect()->route('attendance.raw-logs.index')
            ->with('success', __('attendance.raw_log_deleted_successfully'));
    }

    /**
     * Drop empty / null entries from a filter bag so the URL stays clean.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter(
            $filters,
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}
