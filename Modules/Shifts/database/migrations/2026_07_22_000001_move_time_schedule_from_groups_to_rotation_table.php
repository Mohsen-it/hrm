<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('att_rotations', function (Blueprint $table) {
            $table->unsignedBigInteger('time_schedule_id')->nullable()->after('company_id');
            $table->foreign('time_schedule_id')->references('id')->on('att_time_schedules')->nullOnDelete();
        });

        DB::table('att_rotations')
            ->whereIn('id', function ($query) {
                $query->select('rotation_id')
                    ->from('att_rotation_groups')
                    ->whereNotNull('time_schedule_id');
            })
            ->update(['time_schedule_id' => DB::raw('(SELECT time_schedule_id FROM att_rotation_groups WHERE att_rotation_groups.rotation_id = att_rotations.id AND att_rotation_groups.time_schedule_id IS NOT NULL LIMIT 1)')]);

        Schema::table('att_rotation_groups', function (Blueprint $table) {
            $table->dropForeign(['time_schedule_id']);
            $table->dropColumn('time_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('att_rotation_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('time_schedule_id')->nullable()->after('group_index');
            $table->foreign('time_schedule_id')->references('id')->on('att_time_schedules')->nullOnDelete();
        });

        DB::table('att_rotation_groups')
            ->join('att_rotations', 'att_rotations.id', '=', 'att_rotation_groups.rotation_id')
            ->whereNotNull('att_rotations.time_schedule_id')
            ->update(['att_rotation_groups.time_schedule_id' => DB::raw('att_rotations.time_schedule_id')]);

        Schema::table('att_rotations', function (Blueprint $table) {
            $table->dropForeign(['time_schedule_id']);
            $table->dropColumn('time_schedule_id');
        });
    }
};
