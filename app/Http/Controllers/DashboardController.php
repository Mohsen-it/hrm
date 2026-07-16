<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\AttendanceIntegration\Services\LivePunchFeedService;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;
use Modules\Users\Models\User;
use Modules\Vacations\Models\UserVacationRequest;

class DashboardController extends Controller
{
    private const STATS_CACHE_TTL = 30;

    private const RECENT_CACHE_TTL = 5;

    private const PULL_LOCK_TTL = 10;

    private const PULL_COOLDOWN = 8;

    public function __construct(
        private LivePunchFeedService $livePunchProcessor,
        private FingerprintDeviceService $deviceService,
    ) {}

    /**
     * Display the dashboard with statistics.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'title' => __('menu.dashboard'),
            'stats' => fn () => $this->getStats(),
            'recentAttendance' => fn () => $this->getRecentAttendance()['merged'],
        ]);
    }

    /**
     * Lightweight JSON endpoint for dashboard polling.
     */
    public function snapshot(): JsonResponse
    {
        return response()->json([
            'stats' => $this->getStats(),
            'recentAttendance' => $this->getRecentAttendance()['merged'],
        ]);
    }

    /**
     * Lightweight endpoint that returns only the cached live punch feed.
     *
     * Heavy device sync is intentionally NOT performed here. The caller
     * (the Vue dashboard) can request `sync: true` to trigger a single
     * background pull, which is rate-limited server-side.
     */
    public function pullEvents(Request $request): JsonResponse
    {
        $payload = $this->getRecentAttendance(20);

        $response = [
            'events' => $payload['merged'],
            'devices_polled' => 0,
            'errors' => [],
            'server_time' => now()->toDateTimeString(),
        ];

        if ($request->boolean('sync') && Cache::lock('dashboard:pull-sync', self::PULL_LOCK_TTL)->get()) {
            try {
                $devices = FingerprintDevice::query()
                    ->where('status', 'online')
                    ->get();

                $cooldownKey = 'dashboard:pull-last';
                $lastPull = Cache::get($cooldownKey);
                $shouldSync = $lastPull === null || (time() - (int) $lastPull) >= self::PULL_COOLDOWN;

                $errors = [];
                $polled = 0;

                if ($shouldSync) {
                    Cache::put($cooldownKey, time(), 60);

                    foreach ($devices as $device) {
                        try {
                            $this->deviceService->syncAttendance($device);
                            $polled++;
                        } catch (\Throwable $e) {
                            $errors[] = [
                                'device' => $device->name,
                                'error' => $e->getMessage(),
                            ];
                            Log::warning('Dashboard pullEvents: failed for device', [
                                'device_id' => $device->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    Cache::forget('dashboard:recent_attendance');
                }

                $response['events'] = $this->getRecentAttendance(20)['merged'];
                $response['devices_polled'] = $polled;
                $response['errors'] = $errors;
                $response['sync_performed'] = $shouldSync;
            } finally {
                Cache::lock('dashboard:pull-sync')->release();
            }
        }

        return response()->json($response);
    }

    /**
     * Gather live dashboard statistics (cached).
     *
     * @return array<string, int>
     */
    private function getStats(): array
    {
        return Cache::remember('dashboard:stats', self::STATS_CACHE_TTL, function (): array {
            $today = CarbonImmutable::now()->toDateString();

            $activeUserIds = User::query()
                ->where('id', '!=', User::SUPER_ADMIN_ID)
                ->where('status', 1)
                ->where('is_active_employee', true)
                ->pluck('id');

            $employees = $activeUserIds->count();

            $presentToday = (int) AttendanceSession::onDate($today)
                ->whereIn('user_id', $activeUserIds)
                ->distinct()
                ->count('user_id');

            $absentToday = (int) DailyAttendanceSummary::onDate($today)
                ->whereIn('user_id', $activeUserIds)
                ->where('status', 'absent')
                ->count();

            $onVacation = (int) UserVacationRequest::pending()
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count();

            $pendingRequests = (int) UserVacationRequest::pending()
                ->count();

            $activeDevices = (int) FingerprintDevice::where('status', 'online')->count();

            return [
                'employees' => $employees,
                'present_today' => $presentToday,
                'absent_today' => $absentToday,
                'on_vacation' => $onVacation,
                'pending_requests' => $pendingRequests,
                'active_devices' => $activeDevices,
            ];
        });
    }

    /**
     * Fetch the most recent attendance sessions for the dashboard table (cached).
     *
     * Merges live punches from the cache ring buffer (real-time from devices)
     * with recent sessions from the database.
     *
     * @return array{merged: array<int, array<string, mixed>>}
     */
    private function getRecentAttendance(int $limit = 20): array
    {
        $cacheKey = 'dashboard:recent_attendance';

        return Cache::remember($cacheKey, self::RECENT_CACHE_TTL, function () use ($limit): array {
            return ['merged' => $this->buildRecentAttendance($limit)];
        });
    }

    /**
     * Build the merged live + DB recent attendance list (no cache).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentAttendance(int $limit = 20): array
    {
        $livePunches = $this->livePunchProcessor->getRecentPunches(50);

        $sessions = AttendanceSession::with(['user', 'device'])
            ->orderByDesc('check_in_at')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'id' => 'session_'.$s->id,
                'source' => 'session',
                'session_id' => $s->id,
                'employee_name' => $s->user?->full_name ?? $s->user?->name ?? '#'.$s->user_id,
                'employee_code' => $s->user?->employee_code ?? '',
                'avatar_url' => $s->user?->avatar_url,
                'device_name' => $s->device?->name ?? '',
                'time' => $s->check_in_at?->format('H:i:s') ?? '—',
                'check_in_at' => $s->check_in_at?->toIso8601String(),
                'type' => $s->check_out_at ? 'خروج' : 'دخول',
                'status' => $s->check_out_at ? 'check_out' : 'check_in',
            ]);

        $liveMapped = collect($livePunches)->map(fn ($p) => [
            'id' => 'live_'.$p['session_id'],
            'source' => 'live',
            'session_id' => $p['session_id'],
            'employee_name' => $p['user']['name'] ?? '',
            'employee_code' => $p['user']['employee_code'] ?? '',
            'avatar_url' => null,
            'device_name' => $p['device']['name'] ?? '',
            'time' => isset($p['punched_at']) ? Carbon::parse($p['punched_at'])->format('H:i:s') : '—',
            'check_in_at' => $p['punched_at'] ?? null,
            'type' => $p['punch_type'] === 'check_in' ? 'دخول' : 'خروج',
            'status' => $p['punch_type'],
        ]);

        $seenSessionIds = [];
        $merged = collect();

        foreach ($liveMapped as $item) {
            $sid = $item['session_id'];
            if (! in_array($sid, $seenSessionIds, true)) {
                $seenSessionIds[] = $sid;
                $merged->push($item);
            }
        }

        foreach ($sessions as $item) {
            $sid = $item['session_id'];
            if (! in_array($sid, $seenSessionIds, true)) {
                $seenSessionIds[] = $sid;
                $merged->push($item);
            }
        }

        return $merged->take($limit)->values()->all();
    }
}
