<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Shifts\Http\Requests\GenerateScheduleRequest;
use Modules\Shifts\Http\Requests\PublishScheduleRequest;
use Modules\Shifts\Models\ScheduleEntry;
use Modules\Shifts\Repositories\ScheduleEntryRepository;
use Modules\Shifts\Repositories\SchedulePeriodRepository;
use Modules\Shifts\Services\ScheduleGenerationService;

class SchedulesController extends Controller
{
    public function __construct(
        private ScheduleGenerationService $generationService,
        private SchedulePeriodRepository $periodRepository,
        private ScheduleEntryRepository $entryRepository,
    ) {}

    /**
     * Display a listing of schedule periods.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'year', 'month', 'sort_by', 'sort_dir']);
        $periods = $this->periodRepository->getAll($filters, perPage: 15);

        return Inertia::render('Shifts/Schedules/Index', [
            'periods' => $periods,
            'filters' => $filters,
        ]);
    }

    /**
     * Display the specified schedule period with entries.
     */
    public function show(int $id): Response
    {
        $period = $this->periodRepository->findById($id);
        abort_if(! $period, 404);

        $entries = ScheduleEntry::where('schedule_period_id', $id)
            ->with(['employee', 'dutyCategory'])
            ->orderBy('employee_id')
            ->orderBy('date')
            ->get()
            ->groupBy('employee_id');

        return Inertia::render('Shifts/Schedules/Show', [
            'period' => $period,
            'entries' => $entries,
        ]);
    }

    /**
     * Generate a new monthly schedule.
     */
    public function store(GenerateScheduleRequest $request): RedirectResponse
    {
        $period = $this->generationService->generateMonthlySchedule(
            $request->validated('year'),
            $request->validated('month')
        );

        return redirect()->route('schedules.show', $period->id)
            ->with('success', __('shifts.schedule_generated'));
    }

    /**
     * Publish a draft schedule.
     */
    public function publish(PublishScheduleRequest $request, int $id): RedirectResponse
    {
        $period = $this->generationService->publishSchedule($id);

        return redirect()->route('schedules.show', $id)
            ->with('success', __('shifts.schedule_published'));
    }

    /**
     * Regenerate a published schedule (creates new version).
     */
    public function regenerate(int $id): RedirectResponse
    {
        $period = $this->generationService->regenerateSchedule($id);

        return redirect()->route('schedules.show', $period->id)
            ->with('success', __('shifts.schedule_regenerated'));
    }

    /**
     * Get schedule entries for API/AJAX calls.
     */
    public function entries(Request $request, int $periodId): JsonResponse
    {
        $entries = $this->entryRepository->getForPeriod($periodId);

        return response()->json($entries);
    }
}
