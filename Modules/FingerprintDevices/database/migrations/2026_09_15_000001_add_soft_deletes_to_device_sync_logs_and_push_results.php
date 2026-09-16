<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft-delete columns missing from the original create migrations.
     *
     * Additive only: both DeviceSyncLog and DevicePushResult models use the
     * SoftDeletes trait, so every query adds a `deleted_at` constraint. The
     * original 2026_07_20 create migrations never created the column, which
     * breaks log-status lookups (500 instead of 404/200). Adding a nullable
     * timestamp touches no existing rows.
     */
    public function up(): void
    {
        if (Schema::hasTable('device_sync_logs') && ! Schema::hasColumn('device_sync_logs', 'deleted_at')) {
            Schema::table('device_sync_logs', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('device_push_results') && ! Schema::hasColumn('device_push_results', 'deleted_at')) {
            Schema::table('device_push_results', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Rollback: drop the columns added above.
     */
    public function down(): void
    {
        if (Schema::hasTable('device_push_results') && Schema::hasColumn('device_push_results', 'deleted_at')) {
            Schema::table('device_push_results', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('device_sync_logs') && Schema::hasColumn('device_sync_logs', 'deleted_at')) {
            Schema::table('device_sync_logs', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
