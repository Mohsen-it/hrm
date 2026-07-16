<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_attendance_logs', function (Blueprint $table) {
            $table->index(['device_id', 'device_user_id', 'punch_time'], 'idx_raw_logs_dedup');
            $table->index(['device_user_id', 'punch_time'], 'idx_raw_logs_user_time');
        });
    }

    public function down(): void
    {
        Schema::table('raw_attendance_logs', function (Blueprint $table) {
            $table->dropIndex('idx_raw_logs_dedup');
            $table->dropIndex('idx_raw_logs_user_time');
        });
    }
};
