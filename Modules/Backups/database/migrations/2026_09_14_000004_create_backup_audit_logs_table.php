<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable audit trail for every sensitive backup event (plan §7.4).
     */
    public function up(): void
    {
        Schema::create('backup_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_run_id')->nullable()->constrained('backup_runs')->nullOnDelete();
            $table->foreignId('restore_attempt_id')->nullable()->constrained('backup_restore_attempts')->nullOnDelete();
            $table->string('event', 60);
            $table->json('metadata')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['backup_run_id'], 'backup_audit_run_idx');
            $table->index(['event'], 'backup_audit_event_idx');
            $table->index(['created_at'], 'backup_audit_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_audit_logs');
    }
};
