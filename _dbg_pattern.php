<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Modules\Users\Models\User;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Models\AttendanceSession;

$u = User::where('name', 'وسيم احمد زيداني')->first();
echo "=== RAW LOGS 08-01..08-16 ===\n";
$raw = RawAttendanceLog::where('user_id', $u->id)
    ->whereBetween('punch_time', ['2026-08-01 00:00:00', '2026-08-16 23:59:59'])
    ->orderBy('punch_time')->get();
foreach ($raw as $r) {
    echo '  '.substr($r->punch_time, 0, 16).' type='.($r->punch_type ?? 'NULL').PHP_EOL;
}
echo "\n=== SESSIONS 08-01..08-16 ===\n";
$s = AttendanceSession::where('user_id', $u->id)
    ->whereBetween('check_in_at', ['2026-08-01 00:00:00', '2026-08-16 23:59:59'])
    ->orderBy('check_in_at')->get();
foreach ($s as $ss) {
    echo '  id='.$ss->id.' '.substr($ss->check_in_at ?? 'NULL', 0, 16).' -> '.substr($ss->check_out_at ?? 'OPEN', 0, 16).' raw='.($ss->raw_log_id ?? '-').PHP_EOL;
}
