<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes that the application relies on but that the
 * original per-module migrations did not declare. Adding them in a
 * dedicated migration keeps the per-module migration files pristine
 * and lets us add a database driver-aware switch for MySQL / SQLite
 * / PostgreSQL without leaking that detail into the modules.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        $this->addAttendanceIndexes($driver);
        $this->addFingerprintIndexes($driver);
        $this->addUserPivotIndexes($driver);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dropIndex('att_sessions_user_checkin_idx');
            $table->dropIndex('att_sessions_status_idx');
        });

        Schema::table('daily_attendance_summaries', function (Blueprint $table): void {
            $table->dropIndex('att_summaries_shift_date_idx');
        });

        Schema::table('raw_attendance_logs', function (Blueprint $table): void {
            $table->dropIndex('att_raw_logs_user_ts_idx');
            $table->dropIndex('att_raw_logs_processed_idx');
        });

        Schema::table('user_fingerprints', function (Blueprint $table): void {
            $table->dropIndex('user_fingerprints_device_user_idx');
        });

        Schema::table('user_shifts', function (Blueprint $table): void {
            $table->dropIndex('user_shifts_user_primary_idx');
        });

        Schema::table('user_zone', function (Blueprint $table): void {
            $table->dropIndex('user_zone_user_primary_idx');
        });
    }

    /**
     * Attendance tables — the busiest in the system.
     *
     * Most composite indexes that this query set needs already live on
     * the per-table migrations (see `create_attendance_sessions_table`,
     * `create_daily_attendance_summaries_table`). This method only adds
     * the *additional* indexes that the per-table migrations are missing
     * so we don't collide on `Duplicate key name`.
     */
    protected function addAttendanceIndexes(string $driver): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->index(['user_id', 'check_in_at'], 'att_sessions_user_checkin_idx');
            $table->index('status', 'att_sessions_status_idx');
        });

        // (user_id, summary_date) is already covered by the unique index
        // on the per-table migration. We add (shift_id, summary_date) here
        // because the daily-rollup-by-shift report is not covered upstream.
        Schema::table('daily_attendance_summaries', function (Blueprint $table): void {
            $table->index(['shift_id', 'summary_date'], 'att_summaries_shift_date_idx');
        });

        Schema::table('raw_attendance_logs', function (Blueprint $table): void {
            $table->index(['user_id', 'punch_time'], 'att_raw_logs_user_ts_idx');
            $table->index('processed', 'att_raw_logs_processed_idx');
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE daily_attendance_summaries ADD FULLTEXT INDEX att_summaries_notes_fulltext (notes)');
        }
    }

    /**
     * Fingerprint tables — joins happen on every pull.
     */
    protected function addFingerprintIndexes(string $driver): void
    {
        Schema::table('user_fingerprints', function (Blueprint $table): void {
            $table->index(['device_id', 'user_id'], 'user_fingerprints_device_user_idx');
        });
    }

    /**
     * Pivot tables that grow with the headcount.
     */
    protected function addUserPivotIndexes(string $driver): void
    {
        Schema::table('user_shifts', function (Blueprint $table): void {
            $table->index(['user_id', 'is_primary'], 'user_shifts_user_primary_idx');
        });

        Schema::table('user_zone', function (Blueprint $table): void {
            $table->index(['user_id', 'is_primary'], 'user_zone_user_primary_idx');
        });
    }
};
