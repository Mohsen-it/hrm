<?php

namespace Modules\Vacations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Vacations\Http\Requests\DecisionVacationRequestRequest;
use Modules\Vacations\Http\Requests\StoreVacationRequestRequest;
use Modules\Vacations\Http\Requests\UpdateVacationRequestRequest;
use Modules\Vacations\Http\Resources\UserVacationBalanceResource;
use Modules\Vacations\Http\Resources\UserVacationRequestResource;
use Modules\Vacations\Models\VacationType;
use Modules\Vacations\Services\VacationBalanceService;
use Modules\Vacations\Services\VacationRequestService;
use Modules\Vacations\Services\VacationTypeService;

/**
 * MyVacationsController — employee-facing "my vacations" view.
 *
 * The controller automatically scopes every read / write to the
 * authenticated user so an employee can only see their own requests
 * and balances.
 */
class MyVacationsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private VacationRequestService $requestService,
        private VacationTypeService $typeService,
        private VacationBalanceService $balanceService,
    ) {}

    /**
     * Display the "my vacations" dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $this->authorize('view-vacations');

        $filters = $this->cleanFilters($request->only([
            'status', 'vacation_type_id', 'year',
        ]));
        $filters['user_id'] = (int) $user->id;

        $balances = $this->balanceService->getBalancesForUser((int) $user->id);

        return Inertia::render('Vacations/My/Index', [
            'filters' => fn () => $filters,
            'requests' => fn () => UserVacationRequestResource::collection(
                $this->requestService->getAllRequests($filters, 20)
            )->response($request)->getData(true),
            'balances' => fn () => UserVacationBalanceResource::collection($balances)->resolve(),
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
     * Show the form for creating a new request (employee-facing).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create-vacations');

        return Inertia::render('Vacations/My/Create', [
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
     * Persist a new request on behalf of the authenticated user.
     */
    public function store(StoreVacationRequestRequest $request): RedirectResponse
    {
        $this->authorize('create-vacations');

        $data = $request->validated();
        $data['user_id'] = (int) $request->user()->id;

        $created = $this->requestService->openRequest($data, (int) $request->user()->id);

        return redirect()->route('vacations.my.show', $created->id)
            ->with('success', __('vacations.request_created_successfully'));
    }

    /**
     * Display the specified request.
     */
    public function show(Request $request, int $vacation): Response
    {
        $this->authorize('view-vacations');

        $req = $this->requestService->findRequest($vacation);
        if (! $req || (int) $req->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        return Inertia::render('Vacations/My/Show', [
            'request' => fn () => (new UserVacationRequestResource($req))->resolve(),
        ]);
    }

    /**
     * Show the form for editing a still-pending request.
     */
    public function edit(Request $request, int $vacation): Response
    {
        $this->authorize('edit-vacations');

        $req = $this->requestService->findRequest($vacation);
        if (! $req || (int) $req->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        return Inertia::render('Vacations/My/Edit', [
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
     * Update a pending request owned by the authenticated user.
     */
    public function update(UpdateVacationRequestRequest $request, int $vacation): RedirectResponse
    {
        $this->authorize('edit-vacations');

        $req = $this->requestService->findRequest($vacation);
        if (! $req || (int) $req->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        $this->requestService->updateRequest($req, $request->validated());

        return redirect()->route('vacations.my.show', $req->id)
            ->with('success', __('vacations.request_updated_successfully'));
    }

    /**
     * Cancel a request owned by the authenticated user.
     */
    public function cancel(DecisionVacationRequestRequest $request, int $vacation): RedirectResponse
    {
        $this->authorize('delete-vacations');

        $req = $this->requestService->findRequest($vacation);
        if (! $req || (int) $req->user_id !== (int) $request->user()->id) {
            abort(404);
        }

        $this->requestService->cancelRequest(
            $req,
            (int) $request->user()->id,
            $request->validated()['manager_note'] ?? null,
        );

        return redirect()->route('vacations.my.index')
            ->with('success', __('vacations.request_cancelled_successfully'));
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
