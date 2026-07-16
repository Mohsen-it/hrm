<?php

namespace Modules\Zones\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Zones\Models\Zone;
use Modules\Zones\Services\ZoneBranchService;

/**
 * ZoneBranchesController — manage the branch membership of a single zone.
 *
 * Endpoints are intentionally narrow: attach / detach / list. The bulk
 * replacement path lives on {@see ZonesController::assignBranches()}.
 */
class ZoneBranchesController extends Controller
{
    public function __construct(
        private ZoneBranchService $zoneBranchService,
    ) {}

    /**
     * List the branches attached to the given zone.
     */
    public function index(int $zone): JsonResponse
    {
        $this->authorize('view-zones');

        if (! Zone::whereKey($zone)->exists()) {
            abort(404);
        }

        return response()->json([
            'zone_id' => $zone,
            'branches' => $this->zoneBranchService->getBranchesForZone($zone)
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'branch_code' => $b->branch_code,
                    'branch_name' => $b->branch_name,
                    'city' => $b->city,
                    'is_main' => (bool) $b->is_main,
                    'pivot_is_primary' => (bool) ($b->pivot_is_primary ?? false),
                    'pivot_priority' => (int) ($b->pivot_priority ?? 0),
                    'pivot_notes' => $b->pivot_notes,
                ]),
        ]);
    }

    /**
     * Attach a branch to a zone.
     */
    public function store(Request $request, int $zone): RedirectResponse
    {
        $this->authorize('edit-zones');

        if (! Zone::whereKey($zone)->exists()) {
            abort(404);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'is_primary' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->zoneBranchService->attachBranch(
            $zone,
            (int) $data['branch_id'],
            (bool) ($data['is_primary'] ?? false),
            (int) ($data['priority'] ?? 0),
            $data['notes'] ?? null,
        );

        return redirect()->route('zones.branches', $zone)
            ->with('success', __('zones.branch_attached_successfully'));
    }

    /**
     * Detach a branch from a zone.
     */
    public function destroy(int $zone, int $branch): RedirectResponse
    {
        $this->authorize('edit-zones');

        if (! Zone::whereKey($zone)->exists()) {
            abort(404);
        }

        $this->zoneBranchService->detachBranch($zone, $branch);

        return redirect()->route('zones.branches', $zone)
            ->with('success', __('zones.branch_detached_successfully'));
    }
}
