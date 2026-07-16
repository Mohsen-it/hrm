<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Shifts\Http\Requests\StoreTimeScheduleRequest;
use Modules\Shifts\Http\Requests\UpdateTimeScheduleRequest;
use Modules\Shifts\Http\Resources\TimeScheduleResource;
use Modules\Shifts\Services\TimeScheduleService;

class TimeSchedulesController extends Controller
{
    public function __construct(
        private TimeScheduleService $timeScheduleService
    ) {}

    /**
     * Display a listing of time schedules.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-time-schedules');

        return Inertia::render('Shifts/TimeSchedules/Index', [
            'filters' => fn () => $request->only(['search', 'company_id']),
            'schedules' => fn () => TimeScheduleResource::collection(
                $this->timeScheduleService->getAll(
                    $request->only(['search', 'company_id'])
                )
            ),
        ]);
    }

    /**
     * Show the form for creating a new time schedule.
     */
    public function create(): Response
    {
        $this->authorize('create-time-schedules');

        return Inertia::render('Shifts/TimeSchedules/Create');
    }

    /**
     * Store a newly created time schedule.
     */
    public function store(StoreTimeScheduleRequest $request): RedirectResponse
    {
        $this->authorize('create-time-schedules');

        $this->timeScheduleService->create($request->validated());

        return redirect()->route('time-schedules.index')
            ->with('success', __('shifts.schedule_created'));
    }

    /**
     * Display the specified time schedule.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-time-schedules');

        $schedule = $this->timeScheduleService->getById($id);

        if (! $schedule) {
            abort(404);
        }

        return Inertia::render('Shifts/TimeSchedules/Show', [
            'schedule' => fn () => new TimeScheduleResource($schedule->load('breaks', 'categoryTimeSchedule.shiftCategory')),
        ]);
    }

    /**
     * Show the form for editing the specified time schedule.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-time-schedules');

        $schedule = $this->timeScheduleService->getById($id);

        if (! $schedule) {
            abort(404);
        }

        return Inertia::render('Shifts/TimeSchedules/Edit', [
            'schedule' => fn () => new TimeScheduleResource($schedule),
        ]);
    }

    /**
     * Update the specified time schedule.
     */
    public function update(UpdateTimeScheduleRequest $request, int $id): RedirectResponse
    {
        $this->authorize('edit-time-schedules');

        $this->timeScheduleService->update($id, $request->validated());

        return redirect()->route('time-schedules.index')
            ->with('success', __('shifts.schedule_updated'));
    }

    /**
     * Remove the specified time schedule.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-time-schedules');

        $this->timeScheduleService->delete($id);

        return redirect()->route('time-schedules.index')
            ->with('success', __('shifts.schedule_deleted'));
    }

    /**
     * Copy the specified time schedule.
     */
    public function copy(int $id, Request $request): RedirectResponse
    {
        $this->authorize('create-time-schedules');

        $this->timeScheduleService->copy($id, $request->input('name'));

        return redirect()->route('time-schedules.index')
            ->with('success', __('shifts.copy_success'));
    }
}
