<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\AttendanceNotificationService;

/**
 * Attendance:SendDailyDigest — broadcast the daily KPI digest to admins.
 */
class SendDailyDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:send-daily-digest
                            {--date= : Date to summarise (YYYY-MM-DD). Defaults to today.}';

    /**
     * The console command description.
     */
    protected $description = 'Send a daily attendance digest to the configured admin role';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceNotificationService $notifications): int
    {
        $date = (string) ($this->option('date') ?? now()->toDateString());

        $this->info("Sending daily digest for {$date}...");

        $result = $notifications->runDailyScan($date);

        $this->info('Digest dispatched. '.json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
