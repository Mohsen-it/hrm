<?php

namespace Modules\Vacations\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Vacations\Http\Requests\AdjustVacationBalanceRequest;
use Modules\Vacations\Services\VacationBalanceService;
use Illuminate\Http\RedirectResponse;

class VacationBalancesController extends Controller
{
    public function __construct(
        private VacationBalanceService $balanceService
    ) {}

    public function adjust(AdjustVacationBalanceRequest $request): RedirectResponse
    {
        $this->balanceService->adjust(
            $request->user_id,
            $request->vacation_type_id,
            $request->year,
            $request->days_delta,
            auth()->id(),
            $request->notes
        );

        return redirect()->back()->with('success', __('vacations.balance_adjusted_successfully'));
    }
}
