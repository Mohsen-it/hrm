<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T011 — Ensure holidays has a covering index for calendar lookups.
 *
 * Existing: holidays_active_deleted_idx (is_active, deleted_at) and holidays_date_idx (date).
 * Adds composite (date, is_active, deleted_at) to serve `WHERE date BETWEEN ? AND ? AND is_active=1 AND deleted_at IS NULL`.
 * Additive only — does not drop existing indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('holidays')) {
            return;
        }

        // Ensure required columns exist before creating composite.
        if (! Schema::hasColumn('holidays', 'date') || ! Schema::hasColumn('holidays', 'is_active') || ! Schema::hasColumn('holidays', 'deleted_at')) {
            return;
        }

        try {
            Schema::table('holidays', function (Blueprint $table): void {
                $table->index(['date', 'is_active', 'deleted_at'], 'idx_holidays_date_active_deleted');
            });
        } catch (QueryException|PDOException $e) {
            if (! $this->isDuplicateIndexException($e)) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('holidays')) {
            return;
        }

        try {
            Schema::table('holidays', function (Blueprint $table): void {
                $table->dropIndex('idx_holidays_date_active_deleted');
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
