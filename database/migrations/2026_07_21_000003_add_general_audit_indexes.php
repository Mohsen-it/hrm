<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T015 — Add action+created_at index to audit_logs (Shifts module).
 *
 * Serves searches by action type (e.g. "user.login", "shift.update") within a time range.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->index(['action', 'created_at'], 'idx_audit_action_date');
            });
        } catch (QueryException|PDOException $e) {
            $this->handleDuplicateKey($e);
        }
    }

    public function down(): void
    {
        try {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropIndex('idx_audit_action_date');
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
