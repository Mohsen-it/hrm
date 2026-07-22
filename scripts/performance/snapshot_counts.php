<?php

/**
 * T001: Baseline snapshot — save COUNT(*) for all tables affected by the indexing migrations.
 *
 * Usage:  php scripts/performance/snapshot_counts.php
 * Output: baseline_counts.json  (repo root)
 */

use Illuminate\Support\Facades\DB;

$driver = DB::connection()->getDriverName();

$counts = [];

$counts['users'] = DB::table('users')->count();
$counts['attendance_sessions'] = DB::table('attendance_sessions')->count();
$counts['daily_attendance_summaries'] = DB::table('daily_attendance_summaries')->count();
$counts['raw_attendance_logs'] = DB::table('raw_attendance_logs')->count();
$counts['schedule_entries'] = DB::table('schedule_entries')->count();
$counts['user_vacation_requests'] = DB::table('user_vacation_requests')->count();
$counts['fingerprint_devices'] = DB::table('fingerprint_devices')->count();
$counts['audit_logs'] = DB::table('audit_logs')->count();
$counts['subordinations'] = DB::table('subordinations')->count();
$counts['holidays'] = DB::table('holidays')->count();
$counts['user_vacation_balance_transactions'] = DB::table('user_vacation_balance_transactions')->count();
$counts['device_sync_logs'] = DB::table('device_sync_logs')->count();
$counts['schedule_periods'] = DB::table('schedule_periods')->count();
$counts['att_hours_tracking'] = DB::table('att_hours_tracking')->count();
$counts['att_rotation_assignments'] = DB::table('att_rotation_assignments')->count();
$counts['att_employee_shift_categories'] = DB::table('att_employee_shift_categories')->count();
$counts['fingerprint_devices'] = DB::table('fingerprint_devices')->count();

$counts['driver'] = $driver;
$counts['timestamp'] = now()->toISOString();

$dest = base_path('baseline_counts.json');
file_put_contents($dest, json_encode($counts, JSON_PRETTY_PRINT));

echo "✅ Snapshot saved to {$dest}\n";
echo "   Driver: {$driver}\n";
echo '   Tables: '.count($counts)."\n";
