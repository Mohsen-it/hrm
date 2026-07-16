<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Users\Models\User;
use Modules\Vacations\Models\UserVacationRequest;
use Modules\Vacations\Models\VacationType;
use Modules\Vacations\Services\VacationBalanceService;
use Modules\Vacations\Services\VacationRequestService;
use Modules\Vacations\Services\VacationTypeService;

/**
 * VacationController — the company-wide vacation overview.
 *
 * Unlike `Modules\Vacations\Http\Controllers\VacationRequestsController`
 * (which manages individual requests) and `MyVacationsController` (which
 * is the employee self-service view), this core controller is the read-only
 * dashboard that aggregates balances, on-leave today, and pending approvals
 * across the company.
 */
class VacationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private VacationRequestService $requestService,
        private VacationBalanceService $balanceService,
        private VacationTypeService $typeService,
    ) {}

    /**
     * Display the company-wide vacation dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-vacation-requests');

        $today = (string) $request->input('date', now()->toDateString());

        return Inertia::render('Vacations/Dashboard', [
            'date' => fn () => $today,
            'onLeaveToday' => fn () => $this->getOnLeaveToday($today),
            'pendingRequests' => fn () => $this->getPendingRequests(),
            'balances' => fn () => $this->getCompanyBalances(),
            'types' => fn () => $this->typeService->getActiveTypes()
                ->map(fn (VacationType $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name_ar' => $t->name_ar,
                    'name_en' => $t->name_en,
                    'color' => $t->color,
                    'default_days_per_year' => (int) $t->default_days_per_year,
                ]),
        ]);
    }

    /**
     * Return the list of employees who are on vacation on a given date.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getOnLeaveToday(string $date): array
    {
        return UserVacationRequest::query()
            ->with(['user:id,name,employee_code', 'type:id,code,name_ar,name_en,color'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->get()
            ->map(fn (UserVacationRequest $r) => [
                'request_id' => $r->id,
                'user_id' => $r->user_id,
                'user_name' => optional($r->user)->name,
                'employee_code' => optional($r->user)->employee_code,
                'vacation_type' => optional($r->type)->code,
                'vacation_color' => optional($r->type)->color,
                'start_date' => $r->start_date?->toDateString(),
                'end_date' => $r->end_date?->toDateString(),
                'days' => (int) $r->days,
            ])
            ->all();
    }

    /**
     * Return a slim summary of the most recent pending requests.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getPendingRequests(): array
    {
        return UserVacationRequest::query()
            ->with(['user:id,name,employee_code', 'type:id,code,name_ar,name_en,color'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (UserVacationRequest $r) => [
                'request_id' => $r->id,
                'user_name' => optional($r->user)->name,
                'employee_code' => optional($r->user)->employee_code,
                'vacation_type' => optional($r->type)->code,
                'start_date' => $r->start_date?->toDateString(),
                'end_date' => $r->end_date?->toDateString(),
                'days' => (int) $r->days,
                'created_at' => $r->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Roll-up balances per user, per vacation type.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getCompanyBalances(): array
    {
        $rows = [];
        $users = User::query()
            ->where('is_active_employee', true)
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'employee_code']);

        foreach ($users as $user) {
            $balances = $this->balanceService->getBalancesForUser($user->id);
            $rows[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'employee_code' => $user->employee_code,
                'balances' => $balances->map(fn ($b) => [
                    'vacation_type_id' => $b->vacation_type_id,
                    'entitled_days' => (int) $b->entitled_days,
                    'used_days' => (int) $b->used_days,
                    'pending_days' => (int) $b->pending_days,
                    'remaining_days' => (int) $b->remaining_days,
                ])->all(),
            ];
        }

        return $rows;
    }
}
