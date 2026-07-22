<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckExplain extends Command
{
    protected $signature = 'performance:explain';

    protected $description = 'Run EXPLAIN on key queries and show index usage';

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();

        $queries = [
            'users' => 'SELECT * FROM users WHERE company_id = 1 AND status = 1 AND is_active_employee = 1 LIMIT 20',
            'attendance_sessions' => 'SELECT * FROM attendance_sessions WHERE user_id = 1 AND attendance_date BETWEEN "2026-01-01" AND "2026-12-31"',
            'raw_attendance_logs' => 'SELECT * FROM raw_attendance_logs WHERE processed = 0 AND punch_time >= "2026-01-01"',
        ];

        $rows = [];

        foreach ($queries as $label => $sql) {
            $explain = DB::select("EXPLAIN {$sql}");
            $row = $explain[0] ?? null;

            if ($row) {
                $rows[] = [
                    $label,
                    $row->type ?? '?',
                    $row->key ?? 'NULL',
                    $row->rows ?? '?',
                    $row->Extra ?? '',
                ];
            } else {
                $rows[] = [$label, 'N/A', 'N/A', 'N/A', 'no plan'];
            }
        }

        $this->table(['Table', 'type', 'key', 'rows', 'Extra'], $rows);

        // Check for any ALL (table scans)
        $hasTableScan = false;
        foreach ($rows as $row) {
            if ($row[1] === 'ALL') {
                $hasTableScan = true;
                $this->warn("⚠️  Table scan detected on: {$row[0]}");
            }
        }

        if ($hasTableScan) {
            $this->error('❌ Some queries use table scans (type=ALL). Review indexes.');

            return Command::FAILURE;
        }

        $this->info('✅ No table scans — all queries use indexes.');

        return Command::SUCCESS;
    }
}
