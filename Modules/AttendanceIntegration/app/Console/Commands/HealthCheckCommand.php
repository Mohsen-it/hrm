<?php

namespace Modules\AttendanceIntegration\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * attendance:health-check — read-only pipeline health probe.
 *
 * Inspects (never mutates): queue depth + oldest-job age, failed jobs,
 * live-feed freshness, and per-device punch silence. Exit 0 = healthy,
 * exit 1 = needs operator attention. Safe to run every few minutes.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'attendance:health-check
                            {--max-queue-depth=500 : Warn when pending jobs exceed this}
                            {--max-queue-age=600 : Warn when oldest pending job is older (seconds)}
                            {--silent-hours=24 : Warn when a push-enabled device has no punch within this many hours}';

    protected $description = 'Read-only health probe for the attendance pipeline (queue, live feed, devices)';

    public function handle(): int
    {
        $problems = 0;

        // ── Queue ──
        if (Schema::hasTable('jobs')) {
            $depth = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->orderBy('created_at')->value('created_at');
            $age = $oldest !== null ? max(0, time() - (int) $oldest) : 0;

            $this->line("queue: depth={$depth} oldest_age={$age}s");

            if ($depth > (int) $this->option('max-queue-depth')) {
                $this->warn("  ! queue depth {$depth} exceeds limit");
                $problems++;
            }
            if ($depth > 0 && $age > (int) $this->option('max-queue-age')) {
                $this->warn("  ! oldest job stuck for {$age}s (worker may be down)");
                $problems++;
            }
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            $this->line("failed_jobs (24h): {$failed}");
            if ($failed > 0) {
                $this->warn('  ! recent failed jobs need review (queue:failed)');
                $problems++;
            }
        }

        // ── Live feed freshness ──
        $items = Cache::get('attendanceintegration:live_punches:recent', []);
        $newest = null;
        foreach ($items as $item) {
            $ts = strtotime((string) ($item['punched_at'] ?? ''));
            if ($ts && ($newest === null || $ts > $newest)) {
                $newest = $ts;
            }
        }
        $feedAge = $newest === null ? null : max(0, time() - $newest);
        $this->line('live_feed: items='.count($items).($feedAge === null ? ' (empty)' : " newest_age={$feedAge}s"));

        // ── Device silence ──
        if (Schema::hasTable('fingerprint_devices') && Schema::hasTable('raw_attendance_logs')) {
            $since = now()->subHours((int) $this->option('silent-hours'));
            $silent = DB::table('fingerprint_devices as d')
                ->leftJoin('raw_attendance_logs as r', function ($join) use ($since): void {
                    $join->on('r.device_id', '=', 'd.id')->where('r.punch_time', '>=', $since);
                })
                ->where('d.is_push_enabled', true)
                ->where('d.status', '!=', 'deactivated')
                ->whereNull('r.id')
                ->pluck('d.name');

            $this->line('silent_devices ('.$this->option('silent-hours').'h): '.$silent->count());
            foreach ($silent as $name) {
                $this->warn("  ! {$name} — no punches; check device/worker");
                $problems++;
            }
        }

        if ($problems === 0) {
            $this->info('OK — pipeline healthy');
        } else {
            $this->error("ATTENTION — {$problems} problem(s) detected");
        }

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
