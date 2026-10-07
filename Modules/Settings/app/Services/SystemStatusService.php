<?php

namespace Modules\Settings\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Settings\Models\SystemLifecycle;
use Modules\Settings\Repositories\SystemLifecycleRepository;

/**
 * SystemStatusService — مدة التشغيل + آخر إطفاء/إقلاع + logs مفيدة.
 *
 * مصدر الحقيقة لمدة التشغيل (بالأولوية):
 *  1. ملف العلامة storage/app/system_boot.json (يُكتب عند أول boot للـ stack).
 *  2. آخر سطر "Starting HRM stack" في storage/logs/hrm-startup.log.
 *  3. أقدم عملية php.exe ما تزال حية (Windows فقط).
 *  4. آخر سطر boot في جدول system_lifecycles.
 */
class SystemStatusService
{
    private const MARKER = 'system_boot.json';

    private const LOG_TAIL_LIMIT = 150;

    public function __construct(
        private SystemLifecycleRepository $lifecycles,
    ) {}

    /**
     * يُستدعى من AppServiceProvider::boot — آمن للفشل ولا يسجل أكثر من
     * مرة واحدة لكل إقلاع فعلي للـ stack (dedupe عبر ملف العلامة).
     */
    public function recordBootIfNeeded(): ?SystemLifecycle
    {
        try {
            $osBoot = $this->detectOsBootTime();
            $marker = $this->readMarker();
            $markerBoot = $marker['boot_at'] ?? null;

            $needsNewBoot = $markerBoot === null
                || ($osBoot && abs(Carbon::parse($markerBoot)->diffInSeconds($osBoot)) > 300)
                || ($osBoot && Carbon::parse($markerBoot)->lt($osBoot->copy()->subMinutes(5)));

            if (! $needsNewBoot) {
                return null;
            }

            $previousBoot = $this->lifecycles->latestBoot();
            $lastShutdown = $this->lifecycles->latestShutdown();
            $unclean = $previousBoot && (! $lastShutdown || $lastShutdown->id < $previousBoot->id);

            $bootAt = $osBoot?->toDateTimeString() ?? now()->toDateTimeString();

            $row = $this->lifecycles->record([
                'event' => 'boot',
                'hostname' => gethostname() ?: null,
                'pid' => getmypid() ?: null,
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'reason' => $unclean ? 'unclean-restart' : 'boot',
                'meta' => [
                    'boot_at' => $bootAt,
                    'os_boot_at' => $osBoot?->toDateTimeString(),
                    'startup_log_at' => $this->detectStartupLogTime()?->toDateTimeString(),
                    'previous_boot_at' => $previousBoot?->created_at?->toDateTimeString(),
                    'unclean_previous' => (bool) $unclean,
                ],
            ]);

            $this->writeMarker(['boot_at' => $bootAt, 'lifecycle_id' => $row->id]);

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * سجل إطفاء صريح (يُستدعى من artisan system:record-shutdown قبل إيقاف الـ stack).
     */
    public function recordShutdown(string $reason = 'manual'): SystemLifecycle
    {
        return $this->lifecycles->record([
            'event' => 'shutdown',
            'hostname' => gethostname() ?: null,
            'pid' => getmypid() ?: null,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'reason' => $reason,
        ]);
    }

    /**
     * الحالة الكاملة لصفحة الإعدادات.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $now = now();
        $stackBoot = $this->detectStackBootTime();
        $osBoot = $this->detectOsBootTime();
        $lastShutdown = $this->lifecycles->latestShutdown();
        $latestBoot = $this->lifecycles->latestBoot();

        // آخر إطفاء: سطر shutdown صريح، وإلا نستنتج فجوة غير نظيفة من السجل.
        $inferredShutdown = null;
        $clean = true;
        if ($lastShutdown) {
            $clean = $latestBoot ? $lastShutdown->id > $latestBoot->id - 1 && $lastShutdown->created_at->gt($latestBoot->created_at->copy()->subSecond()) : true;
            // القاعدة الأدق: آخر حدث shutdown يسبق آخر boot = إطفاء غير نظيف سابق.
            if ($latestBoot && $lastShutdown->created_at->lt($latestBoot->created_at)) {
                $inferredShutdown = $latestBoot->meta['previous_boot_at'] ?? null;
                $clean = ! ($latestBoot->meta['unclean_previous'] ?? false);
            }
        } elseif ($latestBoot && ($latestBoot->meta['unclean_previous'] ?? false)) {
            $inferredShutdown = $latestBoot->meta['previous_boot_at'] ?? null;
            $clean = false;
        }

        $uptimeSeconds = $stackBoot ? (int) abs($now->diffInSeconds($stackBoot)) : null;

        return [
            'now' => $now->toDateTimeString(),
            'stack_boot_at' => $stackBoot?->toDateTimeString(),
            'os_boot_at' => $osBoot?->toDateTimeString(),
            'uptime_seconds' => $uptimeSeconds,
            'uptime_human' => $uptimeSeconds !== null ? $this->humanDuration($uptimeSeconds) : null,
            'last_shutdown_at' => $lastShutdown?->created_at?->toDateTimeString() ?? $inferredShutdown,
            'last_shutdown_clean' => $clean,
            'last_shutdown_reason' => $lastShutdown?->reason,
            'boots_count' => $this->lifecycles->countBoots(),
            'unclean_count' => $this->lifecycles->countUnclean(),
            'hostname' => gethostname() ?: null,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * آخر أحداث الإقلاع/الإطفاء (الأحدث أولاً).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $limit = 50): array
    {
        return $this->lifecycles->recent($limit)->map(fn (SystemLifecycle $r) => [
            'id' => $r->id,
            'event' => $r->event,
            'hostname' => $r->hostname,
            'pid' => $r->pid,
            'reason' => $r->reason,
            'unclean_previous' => (bool) ($r->meta['unclean_previous'] ?? false),
            'created_at' => $r->created_at?->toDateTimeString(),
        ])->all();
    }

    /**
     * ذيول الـ logs المفيدة لتتبع المشاكل: آخر أخطاء Laravel + Supervisor
     * + Scheduler + Startup، مع عدّاد لكل ملف. القراءة من نهاية الملف
     * فقط حتى لا نحمّل ملفات بعشرات الميغا في الذاكرة.
     *
     * @return array<string, mixed>
     */
    public function getLogs(): array
    {
        $logDir = storage_path('logs');
        $today = now()->format('Y-m-d');

        $files = [
            'laravel' => $logDir.'/laravel-'.$today.'.log',
            'supervisor' => $logDir.'/hrm-supervisor.log',
            'scheduler' => $logDir.'/hrm-schedule-run.log',
            'startup' => $logDir.'/hrm-startup.log',
            'queue' => $logDir.'/hrm-queue.log',
        ];

        $tails = [];
        foreach ($files as $name => $path) {
            $tails[$name] = $this->tailFile($path, self::LOG_TAIL_LIMIT);
        }

        // أخطر 100 سطر: نلتقط سطور الخطأ من كل الذيل ونرتبها زمنياً.
        $errors = [];
        foreach ($tails as $name => $tail) {
            foreach ($tail['lines'] as $line) {
                if ($this->isErrorLine($line)) {
                    $errors[] = ['source' => $name, 'line' => mb_substr($line, 0, 500)];
                }
            }
        }
        $errors = array_slice($errors, -100);

        return ['files' => $tails, 'errors' => $errors];
    }

    /**
     * فحوص صحة سريعة: قاعدة البيانات + الكاش + القرص + حجم الـ logs.
     *
     * @return array<string, mixed>
     */
    public function getHealth(): array
    {
        $db = true;
        $dbError = null;
        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            $db = false;
            $dbError = $e->getMessage();
        }

        $cacheOk = true;
        try {
            Cache::put('system:health-ping', '1', 10);
            $cacheOk = Cache::get('system:health-ping') === '1';
        } catch (\Throwable) {
            $cacheOk = false;
        }

        $diskFree = null;
        $diskTotal = null;
        try {
            $diskFree = disk_free_space(base_path());
            $diskTotal = disk_total_space(base_path());
        } catch (\Throwable) {
        }

        $logsSize = 0;
        try {
            foreach (glob(storage_path('logs/*.log')) ?: [] as $f) {
                $logsSize += (int) filesize($f);
            }
        } catch (\Throwable) {
        }

        return [
            'db_ok' => $db,
            'db_error' => $dbError ? mb_substr($dbError, 0, 300) : null,
            'db_driver' => config('database.default'),
            'cache_ok' => $cacheOk,
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'disk_free_bytes' => $diskFree,
            'disk_total_bytes' => $diskTotal,
            'logs_size_bytes' => $logsSize,
        ];
    }

    // ── كشف أوقات الإقلاع ──────────────────────────────────────────

    private function detectStackBootTime(): ?Carbon
    {
        // 1) ملف العلامة.
        $marker = $this->readMarker();
        if (! empty($marker['boot_at'])) {
            try {
                return Carbon::parse($marker['boot_at']);
            } catch (\Throwable) {
            }
        }

        // 2) سجل بدء الـ stack.
        if ($t = $this->detectStartupLogTime()) {
            return $t;
        }

        // 3) أقدم عملية php حية.
        if ($t = $this->detectOldestPhpProcess()) {
            return $t;
        }

        // 4) آخر boot في قاعدة البيانات.
        return $this->lifecycles->latestBoot()?->created_at;
    }

    private function detectStartupLogTime(): ?Carbon
    {
        $path = storage_path('logs/hrm-startup.log');
        if (! is_file($path)) {
            return null;
        }
        $tail = $this->tailFile($path, 10);
        for ($i = count($tail['lines']) - 1; $i >= 0; $i--) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+Starting HRM stack/', $tail['lines'][$i], $m)) {
                try {
                    return Carbon::createFromFormat('Y-m-d H:i:s', $m[1]);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    private function detectOldestPhpProcess(): ?Carbon
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }
        try {
            $out = shell_exec('wmic process where "name=\'php.exe\'" get CreationDate /value 2>NUL');
            if (! $out) {
                return null;
            }
            $oldest = null;
            foreach (preg_split('/\r?\n/', $out) as $line) {
                if (preg_match('/CreationDate=(\d{14})/', $line, $m)) {
                    $dt = Carbon::createFromFormat('YmdHis', substr($m[1], 0, 14));
                    if ($oldest === null || $dt->lt($oldest)) {
                        $oldest = $dt;
                    }
                }
            }

            return $oldest;
        } catch (\Throwable) {
            return null;
        }
    }

    private function detectOsBootTime(): ?Carbon
    {
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $out = shell_exec('wmic os get lastbootuptime /value 2>NUL');
                if ($out && preg_match('/LastBootUpTime=(\d{14})/', $out, $m)) {
                    return Carbon::createFromFormat('YmdHis', substr($m[1], 0, 14));
                }
            } catch (\Throwable) {
            }
        } else {
            try {
                $out = trim((string) shell_exec('uptime -s 2>/dev/null'));
                if ($out) {
                    return Carbon::parse($out);
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    // ── ملف العلامة ───────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function readMarker(): array
    {
        $path = storage_path('app/'.self::MARKER);
        if (! is_file($path)) {
            return [];
        }
        try {
            $data = json_decode((string) file_get_contents($path), true);

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeMarker(array $data): void
    {
        try {
            file_put_contents(
                storage_path('app/'.self::MARKER),
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable) {
        }
    }

    // ── أدوات logs ────────────────────────────────────────────────

    /**
     * @return array{exists: bool, size: int, lines: array<int, string>}
     */
    private function tailFile(string $path, int $limit): array
    {
        if (! is_file($path)) {
            return ['exists' => false, 'size' => 0, 'lines' => []];
        }
        $size = (int) filesize($path);

        try {
            $file = new \SplFileObject($path, 'r');
            $file->seek(PHP_INT_MAX);
            $total = $file->key() + 1;
            $start = max(0, $total - $limit);
            $file->seek($start);
            $lines = [];
            while (! $file->eof() && count($lines) < $limit) {
                $line = $file->current();
                $file->next();
                if ($line === false) {
                    continue;
                }
                $line = rtrim((string) $line, "\r\n");
                if ($line !== '') {
                    $lines[] = $line;
                }
            }

            return ['exists' => true, 'size' => $size, 'lines' => $lines];
        } catch (\Throwable) {
            return ['exists' => true, 'size' => $size, 'lines' => []];
        }
    }

    private function isErrorLine(string $line): bool
    {
        return (bool) preg_match(
            '/ERROR|CRITICAL|ALERT|EMERGENCY|Exception|FATAL|FAILED|failed|ErrorException|QueryException/i',
            $line
        );
    }

    private function humanDuration(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.' يوم';
        }
        if ($hours > 0) {
            $parts[] = $hours.' ساعة';
        }
        if ($minutes > 0 || empty($parts)) {
            $parts[] = $minutes.' دقيقة';
        }

        return implode(' و ', $parts);
    }
}
