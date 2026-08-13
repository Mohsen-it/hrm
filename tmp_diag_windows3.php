<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

function line(string $s): void { echo $s . PHP_EOL; }

line('NOW=' . Carbon::now()->format('Y-m-d H:i:s'));

$resolver = app(Modules\Shifts\Services\ScheduleResolverService::class);
$punchWindow = app(Modules\Attendance\Services\PunchWindowService::class);

// Take today's morning punches (07:00-09:30) and check classification
$from = '2026-08-12 07:00:00';
$to = '2026-08-12 09:30:00';
$punches = DB::table('raw_attendance_logs')
    ->whereBetween('punch_time', [$from, $to])
    ->orderBy('punch_time')
    ->get(['id', 'user_id', 'device_id', 'punch_time', 'punch_type']);

line('MORNING PUNCHES 07:00-09:30 count=' . count($punches));

$unclassified = [];
foreach ($punches as $p) {
    $at = new DateTimeImmutable($p->punch_time);
    $class = $punchWindow->classify($p->user_id, $at, false);
    $resolved = $resolver->resolve($p->user_id, $at->format('Y-m-d'));
    if ($class['type'] === null && $class['has_configured_window']) {
        $unclassified[] = [
            'user' => $p->user_id,
            'at' => substr($p->punch_time, 11, 5),
            'dev' => $p->device_id,
            'in_win' => ($resolved['in_ahead_margin'] ?? '-') . '..' . ($resolved['in_above_margin'] ?? '-'),
            'out_win' => ($resolved['out_ahead_margin'] ?? '-') . '..' . ($resolved['out_above_margin'] ?? '-'),
            'work' => $resolved['is_work_day'] ? 'Y' : 'N',
        ];
    }
}

line('UNCLASSIFIED MORNING PUNCHES (type=null with configured window): ' . count($unclassified));
$byWindow = [];
foreach ($unclassified as $u) {
    $key = $u['in_win'] . ' | out=' . $u['out_win'];
    $byWindow[$key] = ($byWindow[$key] ?? 0) + 1;
}
foreach ($byWindow as $win => $c) {
    line('  ' . $c . 'x  in_win=' . $win);
}

// Show 10 examples
line('');
line('Examples:');
foreach (array_slice($unclassified, 0, 10) as $u) {
    $name = DB::table('users')->where('id', $u['user'])->value('name');
    line('  user=' . $u['user'] . " {$name} at={$u['at']} dev={$u['dev']} work={$u['work']} in_win={$u['in_win']} out_win={$u['out_win']}");
}

line('');
line('=== How many morning punches ARE classified ===');
$ok = 0;
foreach ($punches as $p) {
    $at = new DateTimeImmutable($p->punch_time);
    $class = $punchWindow->classify($p->user_id, $at, false);
    if ($class['type'] !== null) $ok++;
}
line('classified_ok=' . $ok . ' / ' . count($punches));
