<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Services\RotationEngine;
use Modules\Users\Models\User;

foreach (['أحمد عبد القادر صالح', 'وسيم احمد زيداني', 'ثائر محمد خضور', 'انس مالك ماضي'] as $name) {
    $u = User::where('name', $name)->first();
    if (! $u) {
        echo "$name NOT FOUND\n";

        continue;
    }
    $a = RotationAssignment::where('employee_id', $u->id)->orderByDesc('start_date')->first();
    $engine = app(RotationEngine::class);
    $snap = $a->snapshot_data['time_schedule'] ?? null;
    echo "===== $name (assign {$a->id}, rot {$a->rotation_id}) =====\n";
    echo '  snapshot ts: '.json_encode($snap, JSON_UNESCAPED_UNICODE)."\n";
    $live = $a->rotation?->timeSchedule;
    echo '  live ts: '.($live ? json_encode(['in' => $live->in_time, 'out' => $live->out_time, 'multi' => (bool) $live->is_multi_day, 'in_ahead' => $live->in_ahead_margin, 'in_above' => $live->in_above_margin, 'out_ahead' => $live->out_ahead_margin, 'out_above' => $live->out_above_margin], JSON_UNESCAPED_UNICODE) : 'NULL')."\n";
    $r = $engine->resolveTimes($a);
    echo "  resolved: in={$r['check_in']} out={$r['check_out']} overnight=".var_export($r['is_overnight'], true)." inW={$r['in_ahead_margin']}-{$r['in_above_margin']} outW={$r['out_ahead_margin']}-{$r['out_above_margin']}\n\n";
}
