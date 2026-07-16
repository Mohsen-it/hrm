<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Events\SessionCreated;
use Modules\Attendance\Events\SessionDeleted;
use Modules\Attendance\Events\SessionUpdated;
use Modules\Attendance\Http\Requests\StoreAttendanceSessionRequest;
use Modules\Attendance\Http\Requests\UpdateAttendanceSessionRequest;
use Modules\Attendance\Http\Resources\AttendanceSessionResource;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Shifts\Services\ShiftService;
use Modules\Users\Services\UserService;

/**
 * AttendanceSessionsController — manage individual check-in / check-out
 * sessions.
 *
 * The page is read-mostly: list (index), detail (show), manual check-in
 * (create / store), quick check-out (close), and a soft delete.
 */
class AttendanceSessionsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private AttendanceSessionService $sessionService,
        private UserService $userService,
        private ShiftService $shiftService,
    ) {}

    /**
     * Display a listing of attendance sessions.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-attendance');

        $filters = $this->cleanFilters($request->only([
            'search', 'user_id', 'shift_id', 'status',
            'session_type', 'source', 'date', 'from', 'to', 'open',
        ]));

        return Inertia::render('Attendance/Sessions/Index', [
            'filters' => fn () => $filters,
            'sessions' => fn () => AttendanceSessionResource::collection(
                $this->sessionService->getAllSessions($filters, 20)
            )->response($request)->getData(true),
            'users' => fn () => $this->userService->getActiveUsers()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'employee_code' => $u->employee_code]),
            'shifts' => fn () => $this->shiftService->getActiveShifts()
                ->map(fn ($s) => ['id' => $s->id, 'shift_name' => $s->shift_name, 'shift_code' => $s->shift_code]),
        ]);
    }

    /**
     * Show the form for creating a new session (manual check-in).
     */
    public function create(): Response
    {
        $this->authorize('create-attendance');

        return Inertia::render('Attendance/Sessions/Create', [
            'users' => fn () => $this->userService->getActiveUsers()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'employee_code' => $u->employee_code]),
            'shifts' => fn () => $this->shiftService->getActiveShifts()
                ->map(fn ($s) => ['id' => $s->id, 'shift_name' => $s->shift_name, 'shift_code' => $s->shift_code]),
        ]);
    }

    /**
     * Persist a new manual check-in session.
     */
    public function store(StoreAttendanceSessionRequest $request): RedirectResponse
    {
        $this->authorize('create-attendance');

        $data = $request->validated();
        $at = new \DateTimeImmutable($data['check_in_at']);

        $session = $this->sessionService->checkIn(
            (int) $data['user_id'],
            $at,
            [
                'shift_id' => $data['shift_id'] ?? null,
                'session_type' => $data['session_type'] ?? 'normal',
                'source' => $data['source'] ?? 'manual',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]
        );

        event(new SessionCreated($session));

        return redirect()->route('attendance.sessions.show', $session->id)
            ->with('success', __('attendance.session_created_successfully'));
    }

    /**
     * Display the specified session.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-attendance');

        $session = $this->sessionService->findSession($id);
        if (! $session) {
            abort(404);
        }

        return Inertia::render('Attendance/Sessions/Show', [
            'session' => fn () => (new AttendanceSessionResource($session->load(['rawLog'])))->resolve(),
        ]);
    }

    /**
     * Show the form for editing the specified session.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-attendance');

        $session = $this->sessionService->findSession($id);
        if (! $session) {
            abort(404);
        }

        return Inertia::render('Attendance/Sessions/Edit', [
            'session' => fn () => (new AttendanceSessionResource($session))->resolve(),
            'shifts' => fn () => $this->shiftService->getActiveShifts()
                ->map(fn ($s) => ['id' => $s->id, 'shift_name' => $s->shift_name, 'shift_code' => $s->shift_code]),
        ]);
    }

    /**
     * Update the specified session.
     */
    public function update(UpdateAttendanceSessionRequest $request, int $id): RedirectResponse
    {
        $this->authorize('edit-attendance');

        $session = $this->sessionService->findSession($id);
        if (! $session) {
            abort(404);
        }

        $data = $request->validated();
        $updated = $this->sessionService->updateSession($session, $data);
        event(new SessionUpdated($updated));

        return redirect()->route('attendance.sessions.show', $id)
            ->with('success', __('attendance.session_updated_successfully'));
    }

    /**
     * Soft delete the specified session.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-attendance');

        $session = $this->sessionService->findSession($id);
        if (! $session) {
            abort(404);
        }

        event(new SessionDeleted($session));
        $this->sessionService->deleteSession($session);

        return redirect()->route('attendance.sessions.index')
            ->with('success', __('attendance.session_deleted_successfully'));
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
