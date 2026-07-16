<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Http\Requests\StoreAttendanceShiftRequest;
use Modules\Attendance\Http\Requests\UpdateAttendanceShiftRequest;
use Modules\Attendance\Services\AttendanceShiftService;

/**
 * AttendanceShiftsController — manage attendance shifts and their daily details.
 */
class AttendanceShiftsController extends Controller
{
    public function __construct(
        private AttendanceShiftService $shiftService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('view-attendance-shifts');

        $filters = $request->only(['search', 'company_id']);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        return Inertia::render('Shifts/AttendanceShifts/Index', [
            'shifts' => fn () => $this->shiftService->getAllShifts($filters, 20),
            'filters' => fn () => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create-attendance-shifts');

        return Inertia::render('Shifts/AttendanceShifts/Create', []);
    }

    public function store(StoreAttendanceShiftRequest $request): RedirectResponse
    {
        $this->authorize('create-attendance-shifts');

        $this->shiftService->createShift($request->validated());

        return redirect()->route('attendance.shifts.index')
            ->with('success', __('attendance.messages.shift_created_successfully'));
    }

    public function show(int $id): Response
    {
        $this->authorize('view-attendance-shifts');

        $shift = $this->shiftService->getShiftWithDetails($id);

        return Inertia::render('Shifts/AttendanceShifts/Show', [
            'shift' => fn () => $shift,
        ]);
    }

    public function edit(int $id): Response
    {
        $this->authorize('edit-attendance-shifts');

        $shift = $this->shiftService->getShiftWithDetails($id);

        return Inertia::render('Shifts/AttendanceShifts/Edit', [
            'shift' => fn () => $shift,
        ]);
    }

    public function update(UpdateAttendanceShiftRequest $request, int $id): RedirectResponse
    {
        $this->authorize('edit-attendance-shifts');

        $shift = $this->shiftService->getShiftWithDetails($id);
        $this->shiftService->updateShift($shift, $request->validated());

        return redirect()->route('attendance.shifts.show', $id)
            ->with('success', __('attendance.messages.shift_updated_successfully'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-attendance-shifts');

        $shift = $this->shiftService->getShiftWithDetails($id);
        $this->shiftService->deleteShift($shift);

        return redirect()->route('attendance.shifts.index')
            ->with('success', __('attendance.messages.shift_deleted_successfully'));
    }
}
