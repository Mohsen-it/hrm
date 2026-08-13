<?php

namespace Modules\UserActivity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\UserActivity\Services\UserActivityService;
use Modules\Users\Models\User;

/**
 * UserActivityController — monitoring pages for user activity in the system.
 */
class UserActivityController extends Controller
{
    public function __construct(private UserActivityService $service) {}

    /**
     * Summary overview across all users.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'from' => $request->input('from', now()->subDays(29)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
            'search' => (string) $request->input('search', ''),
            'period' => (string) $request->input('period', 'custom'),
        ];

        return Inertia::render('UserActivity/Index', [
            'overview' => $this->service->overview(
                $filters['from'],
                $filters['to'],
                $filters['search'],
                (int) $request->integer('page', 1),
                15
            ),
            'filters' => $filters,
            'idle_gap_minutes' => $this->service->idleGapMinutes(),
        ]);
    }

    /**
     * Persist the idle-gap threshold used by the active-time calculation.
     */
    public function updateIdleGap(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'idle_gap_minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $this->service->saveIdleGapMinutes((int) $data['idle_gap_minutes']);

        return back()->with('success', __('useractivity.idle_gap_saved'));
    }

    /**
     * Detailed report for one user.
     */
    public function show(Request $request, int $user): Response
    {
        $userModel = User::query()
            ->with(['department:id,department_name', 'position:id,position_name'])
            ->findOrFail($user);

        $filters = [
            'from' => $request->input('from', now()->subDays(29)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
            'period' => (string) $request->input('period', 'custom'),
        ];

        return Inertia::render('UserActivity/Show', [
            'user' => [
                'id' => $userModel->id,
                'name' => $userModel->name,
                'full_name' => $userModel->full_name,
                'email' => $userModel->email,
                'employee_code' => $userModel->employee_code,
                'avatar_url' => $userModel->avatar_url,
                'department_name' => $userModel->department?->department_name,
                'position_name' => $userModel->position?->position_name,
                'last_login_at' => $userModel->last_login_at?->format('Y-m-d H:i:s'),
            ],
            'detail' => $this->service->userDetail($userModel, $filters['from'], $filters['to']),
            'filters' => $filters,
            'idle_gap_minutes' => $this->service->idleGapMinutes(),
        ]);
    }
}
