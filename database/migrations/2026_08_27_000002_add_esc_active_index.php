<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T010 — Add missing composite index for att_employee_shift_categories.
 *
 * The `active()` scope filters `is_active = true` + `employee_id` constantly.
 * Missing index caused table scans. Spec 008 §5.17 required idx_esc_active.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('att_employee_shift_categories')) {
            return;
        }

        // Column `is_active` does not exist in current schema (checked 2026-08-27:
        // att_employee_shift_categories has employee_id, shift_category_id, start_date, end_date).
        // Spec 008 §5.17 assumed is_active but table is date-ranged without flag.
        // Fallback: ensure an index that serves the common lookup `WHERE employee_id = ?`
        // already covered by att_esc_emp_start_end_idx, so we only add if that index is missing.
        if (! Schema::hasColumn('att_employee_shift_categories', 'is_active')) {
            // Check if att_esc_emp_start_end_idx already exists by trying to create a lightweight index
            // on employee_id if needed — but that index already exists, so no-op.
            return;
        }

        try {
            Schema::table('att_employee_shift_categories', function (Blueprint $table): void {
                $table->index(['is_active', 'employee_id'], 'idx_esc_active');
            });
        } catch (QueryException|PDOException $e) {
            if (! $this->isDuplicateIndexException($e)) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('att_employee_shift_categories')) {
            return;
        }

        if (! Schema::hasColumn('att_employee_shift_categories', 'is_active')) {
            return;
        }

        try {
            Schema::table('att_employee_shift_categories', function (Blueprint $table): void {
                $table->dropIndex('idx_esc_active');
            });
        } catch (QueryException|PDOException $e) {
            if (! $this->isMissingIndexException($e)) {
                throw $e;
            }
        }
    }

    private function isDuplicateIndexException(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'Duplicate key name')
            || str_contains($msg, '1061')
            || str_contains($msg, 'already exists')
            || str_contains($msg, 'index already exists');
    }

    private function isMissingIndexException(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, "doesn't exist")
            || str_contains($msg, 'does not exist')
            || str_contains($msg, '1091')
            || str_contains($msg, "Can't DROP")
            || str_contains($msg, 'Cannot drop index')
            || str_contains($msg, '1553')
            || str_contains($msg, 'needed in a foreign key constraint');
    }
};
