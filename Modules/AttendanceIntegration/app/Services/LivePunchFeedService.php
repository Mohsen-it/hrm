<?php

namespace Modules\AttendanceIntegration\Services;

use Illuminate\Support\Facades\Cache;

class LivePunchFeedService
{
    private const CACHE_KEY = 'attendanceintegration:live_punches:recent';

    private const CACHE_TTL_HOURS = 6;

    private const MAX_ITEMS = 100;

    public function getRecentPunches(int $limit = 50): array
    {
        $items = Cache::get(self::CACHE_KEY, []);

        return array_slice($items, 0, $limit);
    }

    public function addPunch(array $payload): void
    {
        $items = Cache::get(self::CACHE_KEY, []);
        array_unshift($items, $payload);
        $items = array_slice($items, 0, self::MAX_ITEMS);
        Cache::put(self::CACHE_KEY, $items, now()->addHours(self::CACHE_TTL_HOURS));
    }

    public function clearFeed(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
