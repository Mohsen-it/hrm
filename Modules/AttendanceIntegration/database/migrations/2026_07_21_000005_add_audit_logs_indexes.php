<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T014 — Add correlation and actor indexes to attendance integration audit logs.
 *
 *  - correlation_id + occurred_at: trace request flows
 *  - user_id + occurred_at: audit trail by user
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            'idx_audit_correlation' => ['correlation_id', 'occurred_at'],
            'idx_audit_user' => ['user_id', 'occurred_at'],
        ];

        foreach ($indexes as $name => $cols) {
            try {
                Schema::table('attendance_integration_audit_logs', function (Blueprint $table) use ($cols, $name): void {
                    $table->index($cols, $name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleDuplicateKey($e);
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'idx_audit_correlation',
            'idx_audit_user',
        ];

        foreach ($indexes as $name) {
            try {
                Schema::table('attendance_integration_audit_logs', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            } catch (QueryException|PDOException $e) {
                $this->handleMissingIndex($e);
            }
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
