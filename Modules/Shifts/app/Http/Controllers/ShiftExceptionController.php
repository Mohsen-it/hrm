<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shifts\Http\Requests\StoreShiftExceptionRequest;
use Modules\Shifts\Http\Resources\ShiftExceptionResource;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Services\ShiftExceptionService;

/**
 * ShiftExceptionController — Instant Leave & Shift-Swap Interception (Step 4.4).
 *
 * Writes the isolated interceptor rows consumed by the ScheduleResolver in
 * fail-fast order. Creating/cancelling here instantly updates the calendar
 * preview on the frontend (reactive state / AJAX) without a full reload.
 */
class ShiftExceptionController extends Controller
{
    public function __construct(
        private ShiftExceptionService $service,
    ) {}

    /**
     * List active interceptor entries (optionally filtered by employee/range).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view-shift-categories');

        $query = ShiftException::query()
            ->with(['employee', 'createdBy'])
            ->active();

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        $from = $request->query('from');
        $to = $request->query('to');
        if ($from && $to) {
            $query->where('from_date', '<=', $to)->where('to_date', '>=', $from);
        }

        return response()->json(ShiftExceptionResource::collection($query->orderBy('from_date')->get()));
    }

    /**
     * Create a leave / mission / swap interceptor entry.
     */
    public function store(StoreShiftExceptionRequest $request): JsonResponse
    {
        $this->authorize('edit-shift-categories');

        $exception = $this->service->create($request->validated());

        return response()->json(new ShiftExceptionResource($exception), 201);
    }

    /**
     * Cancel an interceptor entry (e.g. leave revoked / swap undone).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('edit-shift-categories');

        $this->service->cancel($id);

        return response()->json(['message' => __('shifts.exception_cancelled')]);
    }
}
