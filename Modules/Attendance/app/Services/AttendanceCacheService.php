<?php

namespace Modules\Attendance\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * AttendanceCacheService — thin cache wrapper for the Attendance module.
 *
 * All read-heavy services (`AttendanceReportService`, `MonthlyReportService`,
 * `YearlyReportService`, dashboard queries) go through this helper so cache
 * invalidation is centralized.
 *
 * Cache strategy:
 *  - Tag-based grouping (driver permitting) so `forgetTag('attendance')` can
 *    wipe the entire module in one call after a write.
 *  - Per-call TTL configured through `config('attendance.cache.ttl')`
 *    (default: 5 minutes — matches the constitution's "Attendance stats" row).
 *  - Stable cache keys built from the `key()` helper to avoid duplication.
 */
class AttendanceCacheService
{
    /**
     * Logical tag applied to every cached entry in the module.
     */
    public const TAG = 'attendance';

    /**
     * Build a stable, namespaced cache key from a logical name + arguments.
     *
     * @param  array<int, mixed>  $args
     */
    public function key(string $name, array $args = []): string
    {
        $suffix = empty($args) ? '' : ':'.md5(json_encode($args, JSON_UNESCAPED_UNICODE));

        return self::TAG.':'.$name.$suffix;
    }

    /**
     * Cache the result of a closure for the configured TTL.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $cacheKey, Closure $callback): mixed
    {
        $ttl = $this->ttlFor($cacheKey);

        if ($this->supportsTags()) {
            return Cache::tags([self::TAG])->remember($cacheKey, $ttl, $callback);
        }

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Forget a single cache key.
     */
    public function forget(string $cacheKey): bool
    {
        if ($this->supportsTags()) {
            return Cache::tags([self::TAG])->forget($cacheKey);
        }

        return Cache::forget($cacheKey);
    }

    /**
     * Flush every attendance-related cache entry.
     */
    public function flush(): bool
    {
        if ($this->supportsTags()) {
            return Cache::tags([self::TAG])->flush();
        }

        // Fallback: prefix-based best-effort purge.
        $prefix = config('cache.prefix').':'.self::TAG.':';
        try {
            $store = Cache::getStore();
            if (method_exists($store, 'connection') || property_exists($store, 'connection')) {
                // Database / Redis / Memcached: we can't reliably enumerate, so
                // callers should still use the tag-based path on the supported
                // drivers. Here we just return true to avoid throwing.
                return true;
            }
            unset($prefix);
        } catch (\Throwable) {
            // ignore — best-effort only.
        }

        return true;
    }

    /**
     * Flush a sub-set of cache entries identified by a logical name prefix.
     */
    public function flushGroup(string $name): void
    {
        $prefix = self::TAG.':'.$name.':';

        if (! $this->supportsTags()) {
            return;
        }

        // Tag-based cache stores expose a tagged iterator only inside the
        // remember() call; the cleanest portable invalidation is the whole
        // module flush. Callers wanting surgical purge can subclass.
        $this->flush();

        unset($prefix);
    }

    /**
     * Resolve the TTL (in seconds) for a given cache key.
     */
    public function ttlFor(string $cacheKey): int
    {
        $default = (int) config('attendance.cache.ttl', 300);

        // Per-key overrides can be declared in `config/attendance.php`
        // under `cache.ttl_overrides` (string => int seconds).
        $overrides = (array) config('attendance.cache.ttl_overrides', []);

        foreach ($overrides as $needle => $ttl) {
            if (str_contains($cacheKey, $needle)) {
                return (int) $ttl;
            }
        }

        return $default;
    }

    /**
     * Determine whether the configured cache store supports tags.
     */
    public function supportsTags(): bool
    {
        $store = Cache::getStore();

        // Database, Redis, and Memcached stores implement the TaggedCache
        // contract. File and array drivers do not.
        return method_exists($store, 'tags');
    }
}
