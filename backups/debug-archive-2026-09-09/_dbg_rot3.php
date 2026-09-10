<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\Services\SchedulePunchClassifierService;
use Modules\Shifts\Services\ScheduleResolverService;
use Modules\Users\Models\User;

$names = ['ثائر محمد خضور', 'حسان علي عبود'];
foreach ($names as $name) {
    $u = User::where('name', $name)->first();
    if (! $u) {
        echo "$name NOT FOUND\n";

        continue;
    }
    echo "===== $name (id={$u->id}) =====\n";
    $resolver = app(ScheduleResolverService::class);
    foreach (['2026-08-14', '2026-08-15', '2026-08-16'] as $d) {
        $r = $resolver->resolve($u->id, $d);
        echo "  $d work=".var_export($r['is_work_day'], true).' in='.($r['in_ahead_margin'] ?? '-').'-'.($r['in_above_margin'] ?? '-').' out='.($r['out_ahead_margin'] ?? '-').'-'.($r['out_above_margin'] ?? '-').' overnight='.var_export($r['is_overnight'] ?? false, true).PHP_EOL;
    }
    $classifier = app(SchedulePunchClassifierService::class);
    foreach ([
        '2026-08-14 19:25:00', '2026-08-15 00:07:00',
        '2026-08-15 19:33:00', '2026-08-15 23:51:00',
        '2026-08-16 09:45:00',
    ] as $t) {
        $type = $classifier->classify($u->id, Carbon::parse($t), PunchType::CheckIn);
        echo "  $t -> $type->value\n";
    }
}
