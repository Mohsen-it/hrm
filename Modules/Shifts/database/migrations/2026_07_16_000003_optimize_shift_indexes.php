<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 — Optimise date-difference math for biometric synchronisation.
 *
 * These indexes eliminate table scans during:
 *  - employee group-history scope resolution (DATEDIFF against start/end),
 *  - group -> schedule lookups,
 *  - raw biometric log dedupe / punch-time range scans on iclock_transaction.
 *
 * All additions are additive (new indexes) — no column or data change.
 */
return new class extends Migration
{
    public function up(): void
    {
        // (employee_id, start_date, end_date) — overlapping-assignment & scope queries.
        Schema::table('att_employee_shift_categories', function (Blueprint $table) {
            $table->index(
                ['employee_id', 'start_date', 'end_date'],
                'att_esc_emp_start_end_idx'
            );
        });

        // Group -> schedule pivot scan during resolver math.
        Schema::table('att_category_time_schedule', function (Blueprint $table) {
            $table->index('time_schedule_id', 'att_cts_schedule_idx');
        });

        // iclock_transaction (biometric punch table) — guard so the migration
        // is safe even if the device table is provisioned by the ZKTeco sync.
        if (Schema::hasTable('iclock_transaction')) {
            Schema::table('iclock_transaction', function (Blueprint $table) {
                $table->index(['emp_id', 'punch_time'], 'idx_iclock_emp_punch');
            });
        }
    }

    public function down(): void
    {
        Schema::table('att_employee_shift_categories', function (Blueprint $table) {
            $table->dropIndex('att_esc_emp_start_end_idx');
        });

        Schema::table('att_category_time_schedule', function (Blueprint $table) {
            $table->dropIndex('att_cts_schedule_idx');
        });

        if (Schema::hasTable('iclock_transaction')) {
            Schema::table('iclock_transaction', function (Blueprint $table) {
                $table->dropIndex('idx_iclock_emp_punch');
            });
        }
    }
};
