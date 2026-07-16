<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Http\Requests\UpdateDailyAttendanceSummaryRequest;
use Modules\Attendance\Http\Resources\DailyAttendanceSummaryResource;
use Modules\Attendance\Jobs\RecalculateDailySummariesChunk;
use Modules\Attendance\Jobs\RecalculateDateRangeChunk;
use Modules\Attendance\Services\DailyAttendanceSummaryService;
use Modules\Users\Services\UserService;

/**
 * DailySummariesController — read & lightly edit the per-day roll-up rows.
 *
 * Recalculation is the heavy operation here: the operator can dispatch a
 * queued job that rebuilds the summaries for a date range (or for a single
 * user/date pair) without blocking the request.
 */
class DailySummariesController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private DailyAttendanceSummaryService $summaryService,
        private UserService $userService,
    ) {}

    /**
     * Display a listing of daily summaries.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-attendance');

        $filters = $this->cleanFilters($request->only([
            'search', 'user_id', 'status', 'session_type', 'date', 'from', 'to', 'is_complete',
        ]));

        return Inertia::render('Attendance/DailySummaries/Index', [
            'filters' => fn () => $filters,
            'summaries' => fn () => DailyAttendanceSummaryResource::collection(
                $this->summaryService->getForDateRange(
                    $filters['from'] ?? now()->subDays(30)->toDateString(),
                    $filters['to'] ?? now()->toDateString(),
                    $filters['user_id'] ?? null,
                )
            )->response($request)->getData(true),
            'users' => fn () => $this->userService->getActiveUsers()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'employee_code' => $u->employee_code]),
        ]);
    }

    /**
     * Display the specified daily summary.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-attendance');

        $summary = $this->summaryService->findById($id);
        if (! $summary) {
            abort(404);
        }

        return Inertia::render('Attendance/DailySummaries/Show', [
            'summary' => fn () => (new DailyAttendanceSummaryResource($summary))->resolve(),
        ]);
    }

    /**
     * Update the specified summary (manual patch only).
     */
    public function update(UpdateDailyAttendanceSummaryRequest $request, int $id): RedirectResponse
    {
        $this->authorize('edit-attendance');

        $summary = $this->summaryService->findById($id);
        if (! $summary) {
            abort(404);
        }

        $this->summaryService->updateSummary($summary, $request->validated());

        return redirect()->route('attendance.daily-summaries.show', $id)
            ->with('success', __('attendance.daily_summary_updated_successfully'));
    }

    /**
     * Dispatch a recalculation job for the supplied (user, date) pair.
     */
    public function recalculate(Request $request): RedirectResponse
    {
        $this->authorize('edit-attendance');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        RecalculateDailySummariesChunk::dispatch([
            ['user_id' => (int) $data['user_id'], 'date' => $data['date']],
        ]);

        return redirect()->back()
            ->with('success', __('attendance.recalculate_dispatched'));
    }

    /**
     * Dispatch a bulk recalculation job for the supplied date range.
     */
    public function recalculateRange(Request $request): RedirectResponse
    {
        $this->authorize('edit-attendance');

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'missing_only' => ['nullable', 'boolean'],
        ]);

        RecalculateDateRangeChunk::dispatch(
            $data['from'],
            $data['to'],
            (bool) ($data['missing_only'] ?? false),
        );

        return redirect()->back()
            ->with('success', __('attendance.recalculate_range_dispatched'));
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
