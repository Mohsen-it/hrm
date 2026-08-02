<?php

namespace Modules\Vacations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Vacations\Exports\VacationBalancesExport;
use Modules\Vacations\Http\Requests\GrantAllBalancesRequest;
use Modules\Vacations\Http\Requests\SetVacationBalanceRequest;
use Modules\Vacations\Jobs\RecalculateVacationBalances;
use Modules\Vacations\Services\VacationBalanceService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * VacationBalancesController — HR-facing balances management.
 *
 * Exposes the balances matrix (employees × vacation types × year) with
 * absolute per-cell entitlement setting, a bulk grant dispatcher and an
 * Excel export. Single-cell adjustments remain available on the
 * `vacations.balances.adjust` route.
 */
class VacationBalancesController extends Controller
{
    public function __construct(
        private VacationBalanceService $balanceService,
    ) {}

    /**
     * Render the balances matrix for the supplied year / filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-vacation-balances');

        [$year, $departmentId, $search] = $this->resolveFilters($request);

        $matrix = $this->balanceService->getBalancesMatrix($year, $departmentId, $search);

        return Inertia::render('Vacations/Balances/Index', [
            'year' => $year,
            'types' => $matrix['types']->map(fn ($type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name_ar' => $type->name_ar,
                'name_en' => $type->name_en,
                'color' => $type->color,
                'default_days_per_year' => (int) $type->default_days_per_year,
            ])->values(),
            'employees' => $matrix['employees'],
            'departments' => $this->buildDepartmentOptions(),
            'years' => $this->buildYearOptions(),
            'filters' => [
                'year' => $year,
                'department_id' => $departmentId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Set the absolute entitled days for a single (user, type, year) cell.
     */
    public function set(SetVacationBalanceRequest $request): RedirectResponse
    {
        $this->balanceService->setDays(
            (int) $request->user_id,
            (int) $request->vacation_type_id,
            (int) $request->year,
            (int) $request->days,
            auth()->id(),
            $request->notes,
        );

        return redirect()->back()->with('success', __('vacations.balance_set_successfully'));
    }

    /**
     * Dispatch the bulk grant job for the supplied year (and optional type).
     */
    public function grantAll(GrantAllBalancesRequest $request): RedirectResponse
    {
        RecalculateVacationBalances::dispatch(
            (int) $request->year,
            $request->vacation_type_id,
        );

        return redirect()->back()->with('success', __('vacations.grant_all_dispatched'));
    }

    /**
     * Export the balances matrix as a fully-formatted .xlsx file.
     */
    public function export(Request $request): HttpResponse
    {
        $this->authorize('view-vacation-balances');

        [$year, $departmentId, $search] = $this->resolveFilters($request);

        $matrix = $this->balanceService->getBalancesMatrix($year, $departmentId, $search);

        $export = new VacationBalancesExport(
            year: $year,
            types: $matrix['types'],
            employees: $matrix['employees'],
        );

        $fileName = "vacation-balances-{$year}.xlsx";
        $content = $export->toBinary();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Normalise the shared year / department / search filters.
     *
     * @return array{0: int, 1: ?int, 2: ?string}
     */
    protected function resolveFilters(Request $request): array
    {
        $year = (int) ($request->input('year') ?? now()->year);
        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;
        $search = trim((string) $request->input('search', ''));

        return [$year, $departmentId, $search !== '' ? $search : null];
    }

    /**
     * Active department options used by the filter bar.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function buildDepartmentOptions(): array
    {
        return DB::table('departments')
            ->where('status', 1)
            ->orderBy('department_name')
            ->get(['id', 'department_name'])
            ->map(fn ($dept) => ['id' => (int) $dept->id, 'name' => $dept->department_name])
            ->all();
    }

    /**
     * Years offered by the year selector (aligned with the validation range).
     *
     * @return array<int, int>
     */
    protected function buildYearOptions(): array
    {
        $current = (int) now()->year;

        return range(max(2020, $current - 5), $current + 1);
    }
}
