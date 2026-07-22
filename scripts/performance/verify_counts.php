<?php

/**
 * T002: Verify baseline — compare current COUNT(*) with baseline_counts.json.
 *
 * Usage:  php scripts/performance/verify_counts.php
 * Exit:   0 if all match, 1 if any mismatch
 */

use Illuminate\Support\Facades\DB;

$baselinePath = base_path('baseline_counts.json');

if (! file_exists($baselinePath)) {
    echo "❌ baseline_counts.json not found. Run snapshot_counts.php first.\n";
    exit(1);
}

$baseline = json_decode(file_get_contents($baselinePath), true);

$skipKeys = ['driver', 'timestamp'];

$allMatch = true;
$maxTableLen = 0;

foreach ($baseline as $table => $beforeCount) {
    if (in_array($table, $skipKeys, true)) {
        continue;
    }

    try {
        $afterCount = DB::table($table)->count();
    } catch (Exception $e) {
        echo sprintf("⚠️  %-35s table not found or error: %s\n", $table, $e->getMessage());
        $allMatch = false;

        continue;
    }

    $match = ($beforeCount === $afterCount);
    $status = $match ? '✅' : '❌ MISMATCH';

    echo sprintf("%-35s before=%-8d after=%-8d %s\n", $table, $beforeCount, $afterCount, $status);

    if (! $match) {
        $allMatch = false;
    }
}

echo "\n";

if ($allMatch) {
    echo "🎉 ALL COUNTS MATCH — Data preserved!\n";
    exit(0);
} else {
    echo "❌ DATA MISMATCH DETECTED — review output above.\n";
    exit(1);
}
