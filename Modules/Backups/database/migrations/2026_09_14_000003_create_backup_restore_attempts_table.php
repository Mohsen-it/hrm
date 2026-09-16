<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Test + production restore attempts (plan §7.3).
     */
    public function up(): void
    {
        Schema::create('backup_restore_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_run_id')->constrained('backup_runs')->cascadeOnDelete();
            $table->string('restore_type', 20)->default('test'); // test|production
            $table->string('target_database', 100);
            $table->foreignId('pre_restore_backup_id')->nullable()->constrained('backup_runs')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending|running|completed|failed|rolled_back
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamps();

            $table->index(['backup_run_id', 'restore_type'], 'restore_attempts_run_type_idx');
            $table->index(['status'], 'restore_attempts_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_restore_attempts');
    }
};
