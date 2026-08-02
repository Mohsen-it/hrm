<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add covering indexes for the live-monitoring and smart-absence queries.
     */
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->index(
                ['attendance_date', 'check_out_at', 'check_in_at'],
                'att_sessions_live_monitoring_idx',
            );
        });

        Schema::table('daily_attendance_summaries', function (Blueprint $table): void {
            $table->index(
                ['summary_date', 'status', 'late_minutes'],
                'daily_summaries_monitoring_idx',
            );
        });

        Schema::table('raw_attendance_logs', function (Blueprint $table): void {
            $table->index(
                ['processed', 'punch_time', 'source'],
                'raw_logs_processing_source_idx',
            );
        });
    }

    /**
     * Remove the monitoring indexes.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dropIndex('att_sessions_live_monitoring_idx');
        });

        Schema::table('daily_attendance_summaries', function (Blueprint $table): void {
            $table->dropIndex('daily_summaries_monitoring_idx');
        });

        Schema::table('raw_attendance_logs', function (Blueprint $table): void {
            $table->dropIndex('raw_logs_processing_source_idx');
        });
    }
};
