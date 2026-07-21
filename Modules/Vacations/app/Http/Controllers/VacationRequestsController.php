<?php

namespace Modules\Vacations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Users\Services\UserService;
use Modules\Vacations\Exports\VacationRequestsExport;
use Modules\Vacations\Http\Requests\DecisionVacationRequestRequest;
use Modules\Vacations\Http\Requests\StoreVacationRequestRequest;
use Modules\Vacations\Http\Requests\UpdateVacationRequestRequest;
use Modules\Vacations\Http\Resources\UserVacationRequestResource;
use Modules\Vacations\Models\VacationType;
use Modules\Vacations\Services\VacationRequestService;
use Modules\Vacations\Services\VacationTypeService;

/**
 * VacationRequestsController — manage the company-wide request queue.
 *
 * This controller is the HR / manager view. Employees use the sibling
 * `MyVacationsController` to see their own requests.
 */
class VacationRequestsController extends Controller
{
    use ExcelExportable;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private VacationRequestService $requestService,
        private VacationTypeService $typeService,
        private UserService $userService,
    ) {}

    /**
     * Display a listing of vacation requests.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-vacation-requests');

        $filters = $this->cleanFilters($request->only([
            'search', 'user_id', 'manager_id', 'vacation_type_id',
            'status', 'start_date', 'from', 'to', 'year',
        ]));

        return Inertia::render('Vacations/Requests/Index', [
            'filters' => fn () => $filters,
            'requests' => fn () => UserVacationRequestResource::collection(
                $this->requestService->getAllRequests($filters, 20)
            )->response($request)->getData(true),
            'types' => fn () => $this->typeService->getActiveTypes()
                ->map(fn (VacationType $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name_ar' => $t->name_ar,
                    'name_en' => $t->name_en,
                    'color' => $t->color,
                ]),
        ]);
    }

    /**
     * Show the form for creating a new request (operator-driven).
     */
    public function create(): Response
    {
        $this->authorize('create-vacation-requests');

        return Inertia::render('Vacations/Requests/Create', [
            'users' => fn () => $this->userService->getActiveUsers()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'employee_code' => $u->employee_code]),
            'types' => fn () => $this->typeService->getActiveTypes()
                ->map(fn (VacationType $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name_ar' => $t->name_ar,
                    'name_en' => $t->name_en,
                    'color' => $t->color,
                    'default_days_per_year' => (int) $t->default_days_per_year,
                    'max_days_per_request' => (int) $t->max_days_per_request,
                    'advance_notice_days' => (int) $t->advance_notice_days,
                ]),
        ]);
    }

    /**
     * Persist a new request.
     */
    public function store(StoreVacationRequestRequest $request): RedirectResponse
    {
        $this->authorize('create-vacation-requests');

        $created = $this->requestService->openRequest(
            $request->validated(),
            $request->user()?->id,
        );

        return redirect()->route('vacations.requests.show', $created->id)
            ->with('success', __('vacations.request_created_successfully'));
    }

    /**
     * Display the specified request.
     */
    public function show(int $vacationRequest): Response
    {
        $this->authorize('view-vacation-requests');

        $req = $this->requestService->findRequest($vacationRequest);
        if (! $req) {
            abort(404);
        }

        return Inertia::render('Vacations/Requests/Show', [
            'request' => fn () => (new UserVacationRequestResource($req))->resolve(),
        ]);
    }

    /**
     * Show the form for editing a pending request.
     */
    public function edit(int $vacationRequest): Response
    {
        $this->authorize('edit-vacation-requests');

        $req = $this->requestService->findRequest($vacationRequest);
        if (! $req) {
            abort(404);
        }

        return Inertia::render('Vacations/Requests/Edit', [
            'request' => fn () => (new UserVacationRequestResource($req))->resolve(),
            'types' => fn () => $this->typeService->getActiveTypes()
                ->map(fn (VacationType $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name_ar' => $t->name_ar,
                    'name_en' => $t->name_en,
                    'color' => $t->color,
                ]),
        ]);
    }

    /**
     * Update a pending request.
     */
    public function update(UpdateVacationRequestRequest $request, int $vacationRequest): RedirectResponse
    {
        $this->authorize('edit-vacation-requests');

        $req = $this->requestService->findRequest($vacationRequest);
        if (! $req) {
            abort(404);
        }

        $this->requestService->updateRequest($req, $request->validated());

        return redirect()->route('vacations.requests.show', $req->id)
            ->with('success', __('vacations.request_updated_successfully'));
    }

    /**
     * Approve a pending request.
     */
    public function approve(DecisionVacationRequestRequest $request, int $vacationRequest): RedirectResponse
    {
        $this->authorize('approve-vacation-requests');

        $req = $this->requestService->findRequest($vacationRequest);
        if (! $req) {
            abort(404);
        }

        $this->requestService->approveRequest(
            $req,
            $request->user()?->id,
            $request->validated()['manager_note'] ?? null,
        );

        return redirect()->route('vacations.requests.show', $req->id)
            ->with('success', __('vacations.request_approved_successfully'));
    }

    /**
     * Reject a pending request.
     */
    public function reject(DecisionVacationRequestRequest $request, int $vacationRequest): RedirectResponse
    {
        $this->authorize('approve-vacation-requests');

        $req = $this->requestService->findRequest($vacationRequest);
        if (! $req) {
            abort(404);
        }

        $this->requestService->rejectRequest(
            $req,
            $request->user()?->id,
            $request->validated()['manager_note'] ?? null,
        );

        return redirect()->route('vacations.requests.show', $req->id)
            ->with('success', __('vacations.request_rejected_successfully'));
    }

    /**
     * Soft delete the specified request.
     */
    public function destroy(int $vacationRequest): RedirectResponse
    {
        $this->authorize('delete-vacation-requests');

        $req = $this->requestService->findRequest($vacationRequest);
        if (! $req) {
            abort(404);
        }

        $this->requestService->deleteRequest($req);

        return redirect()->route('vacations.requests.index')
            ->with('success', __('vacations.request_deleted_successfully'));
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
     * Export vacation requests to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-vacation-requests');

        $filters = $this->cleanFilters($request->only([
            'search', 'user_id', 'manager_id', 'vacation_type_id',
            'status', 'start_date', 'from', 'to', 'year',
        ]));

        $requests = $this->requestService->getAllRequests($filters, 10000);

        $export = new VacationRequestsExport($requests->getCollection());

        return $this->downloadExcel($export->build(), 'vacation-requests');
    }
}
