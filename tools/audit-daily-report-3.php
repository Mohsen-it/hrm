<?php

foreach ([10339, 10005, 16652, 10216, 10241, 10143, 10425, 14500] as $uid) {
    echo "== uid $uid ==\n";
    $sess = DB::table('attendance_sessions')->where('user_id', $uid)->whereBetween('attendance_date', ['2026-10-05', '2026-10-08'])->orderBy('check_in_at')->get(['id', 'attendance_date', 'check_in_at', 'check_out_at', 'notes']);
    foreach ($sess as $s) {
        echo '  sess#'.$s->id.' date='.$s->attendance_date.' in='.($s->check_in_at ?? 'NULL').' out='.($s->check_out_at ?? 'NULL').' notes='.substr((string) $s->notes, 0, 25)."\n";
    }
    $raw = DB::table('raw_attendance_logs')->where('user_id', $uid)->whereBetween('punch_time', ['2026-10-05 00:00:00', '2026-10-09 00:00:00'])->orderBy('punch_time')->pluck('punch_time');
    echo '  raw: '.$raw->map(fn ($x) => substr((string) $x, 5, 11))->implode(', ')."\n";
}
