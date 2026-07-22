<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SnapshotCounts extends Command
{
    protected $signature = 'performance:snapshot';

    protected $description = 'Save baseline COUNT(*) for all tables to baseline_counts.json';

    public function handle(): int
    {
        $tables = [
            'users',
            'attendance_sessions',
            'daily_attendance_summaries',
            'raw_attendance_logs',
            'schedule_entries',
            'user_vacation_requests',
            'fingerprint_devices',
            'audit_logs',
            'subordinations',
            'holidays',
            'user_vacation_balance_transactions',
            'device_sync_logs',
            'schedule_periods',
            'att_hours_tracking',
            'att_rotation_assignments',
            'att_employee_shift_categories',
        ];

        $counts = [];

        foreach ($tables as $table) {
            try {
                $counts[$table] = DB::table($table)->count();
            } catch (\Exception $e) {
                $this->warn("  ⚠️  {$table}: {$e->getMessage()}");
                $counts[$table] = 0;
            }
        }

        $counts['driver'] = DB::connection()->getDriverName();
        $counts['timestamp'] = now()->toISOString();

        $dest = base_path('baseline_counts.json');
        file_put_contents($dest, json_encode($counts, JSON_PRETTY_PRINT));

        $this->info("✅ Snapshot saved to {$dest}");

        $rows = [];
        foreach ($counts as $k => $v) {
            if (in_array($k, ['driver', 'timestamp'], true)) {
                continue;
            }
            $rows[] = [$k, $v];
        }
        $this->table(['Table', 'Count'], $rows);

        return Command::SUCCESS;
    }
}
