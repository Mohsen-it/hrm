<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Shifts\Services\ScheduleResolverService;

/**
 * ScheduleResolverController — powers the Virtual Roster Preview (Step 4.1).
 *
 * Streams the 30-day calendar matrix ON-THE-FLY using the dynamic engine's
 * mapping equation. No static days are persisted; the contract payload is
 * computed per employee per day and returned for the UI to colour-code.
 */
class ScheduleResolverController extends Controller
{
    public function __construct(
        private ScheduleResolverService $resolver,
    ) {}

    /**
     * 30-day (or N-day) matrix for a single employee.
     */
    public function employee(int $id, Request $request): JsonResponse
    {
        $this->authorize('view-shift-categories');

        $from = Carbon::parse($request->query('from', now()->toDateString()))->startOfDay();
        $days = (int) $request->query('days', 30);
        $days = max(1, min($days, 90));

        return response()->json([
            'employee_id' => $id,
            'from' => $from->toDateString(),
            'days' => $days,
            'matrix' => $this->buildMatrix($id, $from, $days),
        ]);
    }

    /**
     * Matrix for every active employee in a department (team roster).
     */
    public function department(int $departmentId, Request $request): JsonResponse
    {
        $this->authorize('view-shift-categories');

        $from = Carbon::parse($request->query('from', now()->toDateString()))->startOfDay();
        $days = (int) $request->query('days', 30);
        $days = max(1, min($days, 90));

        $employeeIds = \DB::table('users')
            ->where('department_id', $departmentId)
            ->where('is_active_employee', true)
            ->pluck('id')
            ->all();

        $people = [];
        foreach ($employeeIds as $employeeId) {
            $people[] = [
                'employee_id' => $employeeId,
                'matrix' => $this->buildMatrix($employeeId, $from, $days),
            ];
        }

        return response()->json([
            'department_id' => $departmentId,
            'from' => $from->toDateString(),
            'days' => $days,
            'people' => $people,
        ]);
    }

    /**
     * A single day resolve (used by the leave/swap interceptor preview).
     */
    public function day(int $id, Request $request): JsonResponse
    {
        $this->authorize('view-shift-categories');

        $date = $request->query('date', now()->toDateString());

        return response()->json($this->resolver->resolve($id, $date));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrix(int $employeeId, Carbon $from, int $days): array
    {
        $matrix = [];
        $current = $from->copy();

        for ($i = 0; $i < $days; $i++) {
            $matrix[] = $this->resolver->resolve($employeeId, $current);
            $current->addDay();
        }

        return $matrix;
    }
}
