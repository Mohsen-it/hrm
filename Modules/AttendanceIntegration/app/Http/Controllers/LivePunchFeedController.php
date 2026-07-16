<?php

namespace Modules\AttendanceIntegration\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AttendanceIntegration\Services\LivePunchFeedService;

class LivePunchFeedController extends Controller
{
    public function __construct(
        private LivePunchFeedService $feedService,
    ) {}

    public function snapshot(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 30), 1), 100);

        return response()->json([
            'punches' => $this->feedService->getRecentPunches($limit),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
