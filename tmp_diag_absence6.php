<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

$dateStr = Carbon::today()->toDateString();

line('=== RAW LOG TIMEZONE CHECK ===');
// Take today's morning sessions and match to raw logs to see how punch_time is stored
$sessions = DB::table('attendance_sessions')
    ->where('attendance_date', $dateStr)
    ->whereNotNull('check_in_at')
    ->orderBy('check_in_at')
    ->limit(5)
    ->get(['user_id', 'check_in_at', 'raw_log_id']);
foreach ($sessions as $s) {
    $raw = DB::table('raw_attendance_logs')->where('id', $s->raw_log_id)->first();
    $rawForUser = $raw ? null : DB::table('raw_attendance_logs')->where('user_id', $s->user_id)->orderByDesc('punch_time')->first();
    line('session user=' . $s->user_id . ' check_in=' . $s->check_in_at . ' raw_log_id=' . $s->raw_log_id);
    if ($raw) {
        line('  raw.id=' . $raw->id . ' punch_time=' . $raw->punch_time . ' (local=' . Carbon::parse($raw->punch_time)->setTimezone('Asia/Riyadh')->format('Y-m-d H:i:s') . ')');
    } else {
        line('  NO raw log with that id');
        if ($rawForUser) line('  latest raw for user: ' . $rawForUser->punch_time);
    }
}

line('');
line('=== TODAY PUNCH DISTRIBUTION (stored values, UTC-window) ===');
$utcFrom = Carbon::parse($dateStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$utcTo = Carbon::parse($dateStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
line('window=' . $utcFrom . ' -> ' . $utcTo);

$buckets = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$utcFrom, $utcTo])
    ->selectRaw('HOUR(punch_time) as h, COUNT(*) as c')
    ->groupBy('h')
    ->orderBy('h')
    ->get();
foreach ($buckets as $b) {
    $localH = ($b->h + 3) % 24;
    line("  hour(utc)={$b->h} -> hour(local)={$localH}: {$b->c} punches");
}

line('');
line('=== PRESENT EMPLOYEES: how many have sessions vs raw only ===');
$repo = app(Modules\Shifts\Repositories\RotationAssignmentRepository::class);
$service = app(Modules\Shifts\Services\AbsenceCalculationService::class);
$expected = $service->getExpectedEmployees(Carbon::today())->toArray();

$sessUsers = DB::table('attendance_sessions')->where('attendance_date', $dateStr)->whereIn('user_id', $expected)->distinct()->pluck('user_id')->toArray();
$rawUsers = DB::table('raw_attendance_logs')->whereIn('user_id', $expected)->whereBetween('punch_time', [$utcFrom, $utcTo])->distinct()->pluck('user_id')->toArray();
$both = array_values(array_intersect($sessUsers, $rawUsers));
$sessOnly = array_values(array_diff($sessUsers, $rawUsers));
$rawOnly = array_values(array_diff($rawUsers, $sessUsers));

line('session_users=' . count($sessUsers) . ' raw_users=' . count($rawUsers));
line('both=' . count($both) . ' session_only=' . count($sessOnly) . ' raw_only=' . count($rawOnly));

line('');
line('=== SESSION-ONLY employees (session but no raw in window) — sample ===');
foreach (array_slice($sessOnly, 0, 8) as $uid) {
    $name = DB::table('users')->where('id', $uid)->value('name');
    $sess = DB::table('attendance_sessions')->where('user_id', $uid)->where('attendance_date', $dateStr)->first();
    $raws = DB::table('raw_attendance_logs')->where('user_id', $uid)->orderByDesc('punch_time')->limit(3)->get(['punch_time']);
    line("  user={$uid} {$name} session_in={$sess?->check_in_at}");
    foreach ($raws as $r) {
        line("    latest raw: {$r->punch_time} (local " . Carbon::parse($r->punch_time)->setTimezone('Asia/Riyadh')->format('Y-m-d H:i:s') . ')');
    }
}

line('');
line('=== EXPECTED employees WITHOUT any punch today (the "absent" pool) ===');
$absentPool = array_values(array_diff($expected, array_merge($sessUsers, $rawUsers)));
line('absent_pool=' . count($absentPool));
// Of these, how many have raw punches on 08-11 or 08-10 (i.e. they work but punches stopped)?
$yesterdayFrom = Carbon::parse($dateStr)->subDay()->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$yesterdayTo = Carbon::parse($dateStr)->subDay()->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
$hadPunchYesterday = DB::table('raw_attendance_logs')->whereIn('user_id', $absentPool)->whereBetween('punch_time', [$yesterdayFrom, $yesterdayTo])->distinct()->pluck('user_id');
line('absent_pool_with_punch_yesterday=' . count($hadPunchYesterday));

// And how many of the absent pool have open sessions (punched in and never out)?
$openSessions = DB::table('attendance_sessions')
    ->whereIn('user_id', $absentPool)
    ->whereNull('check_out_at')
    ->select('user_id', 'attendance_date', 'check_in_at')
    ->get()
    ->groupBy('user_id');
line('absent_pool_with_open_session=' . count($openSessions));
foreach ($openSessions->take(5) as $uid => $rows) {
    $name = DB::table('users')->where('id', $uid)->value('name');
    line("  user={$uid} {$name}: " . $rows->map(fn ($r) => $r->attendance_date . ' ' . substr((string)$r->check_in_at, 11, 5))->implode(' | '));
}
