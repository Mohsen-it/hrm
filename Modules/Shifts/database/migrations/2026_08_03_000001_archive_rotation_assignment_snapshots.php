<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archive the original snapshot payload before keeping a compact runtime copy.
     */
    public function up(): void
    {
        Schema::create('att_rotation_assignment_snapshot_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rotation_assignment_id');
            $table->unique('rotation_assignment_id', 'arasa_assignment_uq');
            $table->foreign('rotation_assignment_id', 'arasa_assignment_fk')
                ->references('id')
                ->on('att_rotation_assignments')
                ->cascadeOnDelete();
            $table->longText('payload');
            $table->string('compression', 20)->default('gzip');
            $table->char('checksum', 64);
            $table->unsignedBigInteger('original_size');
            $table->timestamps();
        });

        DB::table('att_rotation_assignments')
            ->whereNotNull('snapshot_data')
            ->orderBy('id')
            ->chunkById(1, function ($assignments): void {
                foreach ($assignments as $assignment) {
                    $snapshot = (string) $assignment->snapshot_data;
                    $compressed = gzencode($snapshot, 9);

                    if ($compressed === false || ! hash_equals(hash('sha256', $snapshot), hash('sha256', (string) gzdecode($compressed)))) {
                        throw new RuntimeException("Unable to safely archive rotation assignment {$assignment->id}.");
                    }

                    DB::table('att_rotation_assignment_snapshot_archives')->insert([
                        'rotation_assignment_id' => $assignment->id,
                        'payload' => base64_encode($compressed),
                        'compression' => 'gzip',
                        'checksum' => hash('sha256', $snapshot),
                        'original_size' => strlen($snapshot),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('att_rotation_assignments')
                        ->where('id', $assignment->id)
                        ->update([
                            'snapshot_data' => json_encode($this->compactSnapshot($snapshot), JSON_THROW_ON_ERROR),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * Restore the exact original snapshot data if this migration is rolled back.
     */
    public function down(): void
    {
        DB::table('att_rotation_assignment_snapshot_archives')
            ->orderBy('rotation_assignment_id')
            ->chunkById(1, function ($archives): void {
                foreach ($archives as $archive) {
                    $compressed = base64_decode($archive->payload, true);
                    $snapshot = $compressed === false ? false : gzdecode($compressed);

                    if ($snapshot === false || ! hash_equals($archive->checksum, hash('sha256', $snapshot))) {
                        throw new RuntimeException("Unable to safely restore rotation assignment {$archive->rotation_assignment_id}.");
                    }

                    DB::table('att_rotation_assignments')
                        ->where('id', $archive->rotation_assignment_id)
                        ->update([
                            'snapshot_data' => $snapshot,
                            'updated_at' => now(),
                        ]);
                }
            }, 'rotation_assignment_id');

        Schema::dropIfExists('att_rotation_assignment_snapshot_archives');
    }

    /**
     * Keep only the fields consumed at runtime by the schedule resolver.
     *
     * @return array<string, mixed>
     */
    private function compactSnapshot(string $snapshot): array
    {
        $data = json_decode($snapshot, true);

        if (! is_array($data)) {
            return ['archive_version' => 1];
        }

        $rotation = is_array($data['rotation'] ?? null) ? $data['rotation'] : [];
        $group = is_array($data['group'] ?? null) ? $data['group'] : [];
        $timeSchedule = is_array($data['time_schedule'] ?? null) ? $data['time_schedule'] : null;

        return [
            'archive_version' => 1,
            'rotation' => array_intersect_key($rotation, array_flip([
                'id', 'name', 'description', 'anchor_start_date', 'pattern', 'cycle_length',
                'work_days_count', 'rest_days_count', 'number_of_groups', 'time_schedule_id',
                'overtime_enabled', 'work_on_holidays', 'grace_minutes', 'in_ahead_margin',
                'in_above_margin', 'out_ahead_margin', 'out_above_margin', 'color',
            ])),
            'group' => array_intersect_key($group, array_flip(['id', 'name', 'group_index'])),
            'time_schedule' => $timeSchedule === null ? null : array_intersect_key($timeSchedule, array_flip([
                'id', 'name', 'in_time', 'out_time', 'is_multi_day', 'late_margin',
                'early_margin', 'in_ahead_margin', 'in_above_margin',
                'out_ahead_margin', 'out_above_margin', 'breaks',
            ])),
        ];
    }
};
