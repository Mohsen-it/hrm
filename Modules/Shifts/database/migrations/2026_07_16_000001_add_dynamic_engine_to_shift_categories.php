<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 — Safe Schema Evolution (ALTER approach, no data loss).
 *
 * Patches `att_shift_categories` to hold the dynamic-cycle anchor so the
 * engine can resolve any target date purely from date math:
 *   Cycle_Length = work_days + rest_days
 *   Day_Index    = ((Target_Date - anchor_start_date) % Cycle_Length) + 1
 *
 * `cycle_length` is a derived/denormalised column kept in sync by the
 * ShiftCategoryService so that date-difference queries and reporting can
 * filter/index without recomputing the sum on every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('att_shift_categories', function (Blueprint $table) {
            $table->date('anchor_start_date')->nullable()->after('rest_days')
                ->comment('Cycle anchor (Day 1) used by the dynamic shift engine.');
            $table->unsignedSmallInteger('cycle_length')->nullable()->after('anchor_start_date')
                ->comment('Denormalised work_days + rest_days for fast date math / indexing.');
            $table->boolean('is_dynamic')->default(false)->after('cycle_length')
                ->comment('True when the category is driven by the dynamic cycle engine.');
        });

        // Index the anchor so DATEDIFF / modulo math can be scoped cheaply.
        Schema::table('att_shift_categories', function (Blueprint $table) {
            $table->index('anchor_start_date', 'att_shift_categories_anchor_idx');
            $table->index('cycle_length', 'att_shift_categories_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::table('att_shift_categories', function (Blueprint $table) {
            $table->dropIndex('att_shift_categories_anchor_idx');
            $table->dropIndex('att_shift_categories_cycle_idx');
            $table->dropColumn(['anchor_start_date', 'cycle_length', 'is_dynamic']);
        });
    }
};
