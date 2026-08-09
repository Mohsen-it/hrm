<?php

namespace Modules\Vacations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Users\Services\UserService;
use Modules\Vacations\Exports\AttendanceJustificationsExport;
use Modules\Vacations\Http\Requests\ResolveAttendanceScheduleRequest;
use Modules\Vacations\Http\Requests\StoreAttendanceJustificationRequest;
use Modules\Vacations\Http\Resources\AttendanceJustificationRequestResource;
use Modules\Vacations\Services\AttendanceJustificationService;

class AttendanceJustificationRequestsController extends Controller
{
    use ExcelExportable;

    public function __construct(private AttendanceJustificationService $service, private UserService $users) {}

    /** List attendance justifications. */
    public function index(Request $request): Response
    {
        $this->authorize('view-vacation-requests');
        $filters = array_filter($request->only(['search', 'date']), fn ($value) => $value !== null && $value !== '');

        return Inertia::render('Vacations/Justifications/Index', ['filters' => $filters, 'requests' => fn () => AttendanceJustificationRequestResource::collection($this->service->paginate($filters, $request->input('per_page', 20)))]);
    }

    /** Display a new justification form. */
    public function create(): Response
    {
        $this->authorize('create-vacation-requests');

        return Inertia::render('Vacations/Justifications/Create', ['users' => fn () => $this->users->getActiveUsers()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'employee_code' => $user->employee_code])]);
    }

    /** Return the rotation window selected for an employee and attendance date. */
    public function schedule(ResolveAttendanceScheduleRequest $request): JsonResponse
    {
        $this->authorize('view-vacation-requests');
        $data = $request->validated();

        return response()->json($this->service->resolveSchedule((int) $data['user_id'], $data['attendance_date']));
    }

    /** Store the submitted explanation. */
    public function store(StoreAttendanceJustificationRequest $request): RedirectResponse
    {
        $this->authorize('create-vacation-requests');
        $this->service->create($request->validated());

        return redirect()->route('vacations.justifications.index')->with('success', 'تم تسجيل طلب التبرير وحساب النتيجة وفق نافذة الدورية.');
    }

    /** Display an existing justification for editing. */
    public function edit(int $justification): Response
    {
        $this->authorize('edit-vacation-requests');
        $request = $this->service->find($justification) ?? abort(404);

        return Inertia::render('Vacations/Justifications/Edit', ['request' => (new AttendanceJustificationRequestResource($request))->resolve(), 'users' => fn () => $this->users->getActiveUsers()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'employee_code' => $user->employee_code])]);
    }

    /** Save an edited justification. */
    public function update(StoreAttendanceJustificationRequest $request, int $justification): RedirectResponse
    {
        $this->authorize('edit-vacation-requests');
        $record = $this->service->find($justification) ?? abort(404);
        $this->service->update($record, $request->validated());

        return redirect()->route('vacations.justifications.index')->with('success', 'تم تحديث التبرير وإعادة احتساب البيانات المرتبطة بالدورية.');
    }

    /** Soft-delete a justification. */
    public function destroy(int $justification): RedirectResponse
    {
        $this->authorize('delete-vacation-requests');
        $record = $this->service->find($justification) ?? abort(404);
        $this->service->delete($record);

        return redirect()->route('vacations.justifications.index')->with('success', 'تم حذف التبرير.');
    }

    /** Download the currently filtered justification queue as Excel. */
    public function export(Request $request)
    {
        $this->authorize('view-vacation-requests');
        $filters = array_filter($request->only(['search', 'date']), fn ($value) => $value !== null && $value !== '');

        return $this->downloadExcel((new AttendanceJustificationsExport($this->service->export($filters)))->build(), 'attendance-justifications');
    }
}
