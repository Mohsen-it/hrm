<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\AttendanceNotificationService;
use Modules\Users\Models\User;

/**
 * Attendance:SendWeeklyDigest — push a weekly summary to all managers.
 */
class SendWeeklyDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:send-weekly-digest
                            {--role=manager : Role whose members receive the digest}
                            {--days=7 : Look back N days}';

    /**
     * The console command description.
     */
    protected $description = 'Send a weekly attendance digest to members of a role';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceNotificationService $notifications): int
    {
        $role = (string) $this->option('role');
        $days = (int) $this->option('days');

        $recipients = User::role($role)->get(['id', 'name', 'email']);

        if ($recipients->isEmpty()) {
            $this->components->warn("No users with role '{$role}'.");

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($recipients as $user) {
            $summary = [
                'days' => $days,
                'window' => [
                    'from' => now()->subDays($days)->toDateString(),
                    'to' => now()->toDateString(),
                ],
            ];

            if ($notifications->sendWeeklySummary($user->id, $summary)) {
                $sent++;
            }
        }

        $this->info("Sent weekly digest to {$sent} recipient(s).");

        return self::SUCCESS;
    }
}
