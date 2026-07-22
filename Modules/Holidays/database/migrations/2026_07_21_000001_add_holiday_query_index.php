<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T010 — Add date+active index to holidays.
 *
 * Serves calendar-holiday lookups by date range and active flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('holidays', function (Blueprint $table): void {
                $table->index(['date', 'is_active'], 'idx_holidays_date_active');
            });
        } catch (QueryException|PDOException $e) {
            $this->handleDuplicateKey($e);
        }
    }

    public function down(): void
    {
        try {
            Schema::table('holidays', function (Blueprint $table): void {
                $table->dropIndex('idx_holidays_date_active');
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
