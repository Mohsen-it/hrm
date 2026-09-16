<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backup schedules / settings (plan §7.1).
     */
    public function up(): void
    {
        Schema::create('backup_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->string('frequency', 20)->default('daily'); // daily|weekly|specific_days|manual_only
            $table->string('scheduled_time', 5)->default('02:00');
            $table->tinyInteger('day_of_week')->nullable();
            $table->string('timezone', 50)->default('Asia/Damascus');
            $table->string('database_connection', 20)->default('mysql');
            $table->string('database_name', 100)->default('hrmair');
            $table->boolean('include_files')->default(false);
            $table->string('local_disk', 50)->default('backups');
            $table->string('remote_disk', 50)->nullable();
            $table->unsignedInteger('retention_daily')->default(14);
            $table->unsignedInteger('retention_weekly')->default(12);
            $table->unsignedInteger('retention_monthly')->default(12);
            $table->boolean('encryption_enabled')->default(true);
            $table->boolean('verification_enabled')->default(true);
            $table->boolean('notify_on_success')->default(false);
            $table->boolean('notify_on_failure')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_enabled', 'frequency'], 'backup_configs_enabled_freq_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_configs');
    }
};
