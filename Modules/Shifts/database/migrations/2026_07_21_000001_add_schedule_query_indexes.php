<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T017 — Add performance indexes to schedule, hours tracking, and rotations.
 *
 *  - schedule_entries: date+employee+day_status, period+day_status
 *  - att_hours_tracking: employee+period, employee+category+period
 *  - att_rotation_assignments: employee+start+end
 */
return new class extends Migration
{
    public function up(): void
    {
        // schedule_entries — 2 indexes
        $schedIndexes = [
            'idx_schedule_entries_date_emp' => ['date', 'employee_id', 'day_status'],
            'idx_schedule_entries_period_status' => ['schedule_period_id', 'day_status'],
        ];

        foreach ($schedIndexes as $name => $cols) {
            try {
                Schema::table('schedule_entries', function (Blueprint $table) use ($cols, $name): void {
                    $table->index($cols, $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }

        // att_hours_tracking — 2 indexes
        $hoursIndexes = [
            'idx_hours_emp_date' => ['employee_id', 'period_start', 'period_end'],
            'idx_hours_emp_category' => ['employee_id', 'shift_category_id', 'period_start'],
        ];

        foreach ($hoursIndexes as $name => $cols) {
            try {
                Schema::table('att_hours_tracking', function (Blueprint $table) use ($cols, $name): void {
                    $table->index($cols, $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }

        // att_rotation_assignments — 1 index
        try {
            Schema::table('att_rotation_assignments', function (Blueprint $table): void {
                $table->index(['employee_id', 'start_date', 'end_date'], 'idx_rotation_assign_emp_dates');
            });
        } catch (QueryException|PDOException $e) {
            $this->handleDuplicateKey($e);
        }
    }

    public function down(): void
    {
        $schedIndexes = [
            'idx_schedule_entries_date_emp',
            'idx_schedule_entries_period_status',
        ];

        foreach ($schedIndexes as $name) {
            try {
                Schema::table('schedule_entries', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }

        $hoursIndexes = [
            'idx_hours_emp_date',
            'idx_hours_emp_category',
        ];

        foreach ($hoursIndexes as $name) {
            try {
                Schema::table('att_hours_tracking', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
        }

        try {
            Schema::table('att_rotation_assignments', function (Blueprint $table): void {
                $table->dropIndex('idx_rotation_assign_emp_dates');
            });
        } catch (QueryException|PDOException $e) {
            $this->handleMissingIndex($e);
        }
    }

    protected function handleDuplicateKey(Throwable $e): void
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate key name') || str_contains($msg, '1061')
            || str_contains($msg, 'already exists') || str_contains($msg, 'index already exists')) {
            return;
        }
        throw $e;
    }

    protected function handleMissingIndex(Throwable $e): void
    {
        $msg = $e->getMessage();
        if (str_contains($msg, "doesn't exist") || str_contains($msg, '1091')
            || str_contains($msg, 'does not exist') || str_contains($msg, 'Can\'t drop')
            || str_contains($msg, 'Cannot drop index') || str_contains($msg, '1553')
            || str_contains($msg, 'needed in a foreign key constraint')) {
            return;
        }
        throw $e;
    }
};
