<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Modules\Users\Models\User;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Models\AttendanceSession;

foreach (['انس مالك ماضي', 'ايفين احمد ديب', 'رامز محمد ديب'] as $name) {
    $u = User::where('name', $name)->first();
    if (!$u) { echo "$name NOT FOUND\n"; continue; }
    echo "===== $name (id={$u->id}) =====\n";
    $raw = RawAttendanceLog::where('user_id', $u->id)
        ->whereBetween('punch_time', ['2026-08-15 00:00:00', '2026-08-16 23:59:59'])
        ->orderBy('punch_time')->get();
    foreach ($raw as $r) {
        echo "  RAW {$r->punch_time} type={$r->punch_type}\n";
    }
    $s = AttendanceSession::where('user_id', $u->id)
        ->whereBetween('check_in_at', ['2026-08-15 00:00:00', '2026-08-16 23:59:59'])
        ->orderBy('check_in_at')->get();
    foreach ($s as $ss) {
        echo "  SESS id={$ss->id} in=".substr((string)$ss->check_in_at, 0, 16)." out=".substr((string)($ss->check_out_at ?? 'OPEN'), 0, 16)." raw={$ss->raw_log_id} status={$ss->status}\n";
    }
    echo "\n";
}
