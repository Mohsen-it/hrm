<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyCounts extends Command
{
    protected $signature = 'performance:verify';

    protected $description = 'Compare current COUNT(*) with baseline_counts.json';

    public function handle(): int
    {
        $path = base_path('baseline_counts.json');

        if (! file_exists($path)) {
            $this->error('❌ baseline_counts.json not found. Run performance:snapshot first.');

            return Command::FAILURE;
        }

        $baseline = json_decode(file_get_contents($path), true);
        $skipKeys = ['driver', 'timestamp'];

        $allMatch = true;
        $rows = [];

        foreach ($baseline as $table => $before) {
            if (in_array($table, $skipKeys, true)) {
                continue;
            }

            try {
                $after = DB::table($table)->count();
            } catch (\Exception $e) {
                $rows[] = [$table, $before, '?', '⚠️  ERROR'];
                $allMatch = false;

                continue;
            }

            $match = ($before === $after);
            $status = $match ? '✅' : '❌ MISMATCH';

            if (! $match) {
                $allMatch = false;
            }

            $rows[] = [$table, $before, $after, $status];
        }

        $this->table(['Table', 'Before', 'After', 'Status'], $rows);

        if ($allMatch) {
            $this->info('🎉 ALL COUNTS MATCH — Data preserved!');

            return Command::SUCCESS;
        }

        $this->error('❌ DATA MISMATCH DETECTED — review output above.');

        return Command::FAILURE;
    }
}
