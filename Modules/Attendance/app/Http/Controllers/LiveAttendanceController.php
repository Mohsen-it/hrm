<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Exports\LiveAttendanceExport;
use Modules\Attendance\Http\Resources\AttendanceSessionResource;
use Modules\Attendance\Http\Resources\RawAttendanceLogResource;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\AttendanceMonitoringService;
use Modules\Attendance\Services\AttendanceNotificationService;

/**
 * LiveAttendanceController — real-time monitoring screen.
 *
 * Renders the Vue page on `GET /attendance/live` and exposes a small JSON
 * endpoint that the page polls every few seconds to refresh the live
 * sessions, missing check-outs, anomalies, and overall health snapshot.
 */
class LiveAttendanceController extends Controller
{
    use ExcelExportable;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private AttendanceMonitoringService $monitoring,
        private AttendanceNotificationService $notifier,
    ) {}

    /**
     * Render the live monitoring page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-attendance');

        $date = (string) $request->input('date', now()->toDateString());

        $live = $this->monitoring->getLiveSessions($date);
        $missing = $this->monitoring->getMissingCheckouts($date);
        $anomalies = $this->monitoring->getAnomalies($date);
        $health = $this->monitoring->getHealthSnapshot($date);

        return Inertia::render('Attendance/Live/Index', [
            'filters' => fn () => $this->cleanFilters($request->only(['date'])),
            'live' => fn () => AttendanceSessionResource::collection($live)->resolve(),
            'missing' => fn () => AttendanceSessionResource::collection($missing)->resolve(),
            'anomalies' => fn () => $anomalies->map(fn ($s) => [
                'id' => $s->id,
                'user' => $s->user ? [
                    'id' => $s->user->id,
                    'name' => $s->user->name,
                    'employee_code' => $s->user->employee_code,
                ] : null,
                'status' => $s->status,
                'late_minutes' => (int) $s->late_minutes,
                'summary_date' => $s->summary_date?->format('Y-m-d'),
            ])->values(),
            'health' => fn () => $health,
        ]);
    }

    /**
     * Lightweight JSON endpoint used by the live page polling.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $this->authorize('view-attendance');

        $date = (string) $request->input('date', now()->toDateString());

        $live = $this->monitoring->getLiveSessions($date);
        $missing = $this->monitoring->getMissingCheckouts($date);
        $anomalies = $this->monitoring->getAnomalies($date);
        $health = $this->monitoring->getHealthSnapshot($date);

        return response()->json([
            'date' => $date,
            'live' => AttendanceSessionResource::collection($live)->resolve(),
            'missing' => AttendanceSessionResource::collection($missing)->resolve(),
            'anomalies' => $anomalies->map(fn ($s) => [
                'id' => $s->id,
                'user' => $s->user ? [
                    'id' => $s->user->id,
                    'name' => $s->user->name,
                    'employee_code' => $s->user->employee_code,
                ] : null,
                'status' => $s->status,
                'late_minutes' => (int) $s->late_minutes,
                'summary_date' => $s->summary_date?->format('Y-m-d'),
            ])->values(),
            'health' => $health,
        ]);
    }

    /**
     * Run the daily notification scan for the supplied date.
     */
    public function runDailyScan(Request $request): RedirectResponse
    {
        $this->authorize('edit-attendance');

        $date = (string) $request->input('date', now()->toDateString());
        $stats = $this->notifier->runDailyScan($date);

        return redirect()->back()->with(
            'success',
            __('attendance.daily_scan_completed', $stats),
        );
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

    /**
     * Live punch feed — all recent raw attendance logs ordered newest to oldest.
     *
     * Used by the live monitoring page to show a real-time stream of every
     * fingerprint event as it arrives from devices.
     */
    public function punchFeed(Request $request): JsonResponse
    {
        $this->authorize('view-attendance');

        $limit = min((int) $request->input('limit', 100), 500);
        $date = $request->input('date');

        $query = RawAttendanceLog::with(['user', 'device'])
            ->orderByDesc('punch_time');

        if ($date) {
            $query->whereDate('punch_time', $date);
        }

        $logs = $query->limit($limit)->get();

        return response()->json([
            'punches' => RawAttendanceLogResource::collection($logs)->resolve(),
        ]);
    }

    /**
     * Export the current live attendance snapshot to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-attendance');

        $date = (string) $request->input('date', now()->toDateString());

        $live = $this->monitoring->getLiveSessions($date);
        $missing = $this->monitoring->getMissingCheckouts($date);
        $anomalies = $this->monitoring->getAnomalies($date);
        $health = $this->monitoring->getHealthSnapshot($date);

        $export = new LiveAttendanceExport($date, $live, $missing, $anomalies, $health);

        return $this->downloadExcel($export->build(), 'attendance-live-'.$date);
    }
}
