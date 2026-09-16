<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every backup / restore run with file metadata (plan §7.2).
     */
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_config_id')->nullable()->constrained('backup_configs')->nullOnDelete();
            $table->string('type', 30)->default('manual'); // automatic|manual|restore_test|restore_production
            $table->string('status', 20)->default('pending'); // pending|running|completed|failed|cancelled
            $table->string('database_driver', 20)->default('mysql');
            $table->string('database_name', 100);
            $table->string('database_server_version', 30)->nullable();
            $table->string('file_path', 500);
            $table->string('remote_file_path', 500)->nullable();
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum_algorithm', 10)->default('sha256');
            $table->string('checksum', 128);
            $table->boolean('compressed')->default(true);
            $table->boolean('encrypted')->default(true);
            $table->string('verification_status', 20)->default('pending'); // pending|verified|failed
            $table->text('verification_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'type'], 'backup_runs_status_type_idx');
            $table->index(['verification_status'], 'backup_runs_verification_idx');
            $table->index(['created_at'], 'backup_runs_created_idx');
            $table->index(['database_name', 'status'], 'backup_runs_db_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
