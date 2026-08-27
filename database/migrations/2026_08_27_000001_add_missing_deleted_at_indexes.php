<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T009 — Add missing deleted_at indexes for softDeletes tables.
 *
 * `attendance_sessions` and `raw_attendance_logs` use SoftDeletes but had no
 * index on `deleted_at`, forcing table scans for every query with
 * `WHERE deleted_at IS NULL`. Adds single-column indexes (safe on all drivers).
 */
return new class extends Migration
{
    private const INDEXES = [
        'attendance_sessions' => [
            'idx_att_sessions_deleted_at' => ['deleted_at'],
        ],
        'raw_attendance_logs' => [
            'idx_raw_logs_deleted_at' => ['deleted_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                // Skip if any column missing (defensive).
                $missing = false;
                foreach ($columns as $col) {
                    if (! Schema::hasColumn($tableName, $col)) {
                        $missing = true;
                        break;
                    }
                }
                if ($missing) {
                    continue;
                }

                try {
                    Schema::table($tableName, function (Blueprint $table) use ($columns, $name): void {
                        $table->index($columns, $name);
                    });
                } catch (QueryException|PDOException $e) {
                    if (! $this->isDuplicateIndexException($e)) {
                        throw $e;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($name): void {
                        $table->dropIndex($name);
                    });
                } catch (QueryException|PDOException $e) {
                    if (! $this->isMissingIndexException($e)) {
                        throw $e;
                    }
                }
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
