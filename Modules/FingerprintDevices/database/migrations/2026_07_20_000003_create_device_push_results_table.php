<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_push_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->constrained('device_sync_logs')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('fingerprint_devices')->cascadeOnDelete();
            $table->enum('record_type', ['user', 'fingerprint', 'face_photo']);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('target_finger_id')->nullable();
            $table->unsignedInteger('device_uid')->nullable();
            $table->enum('status', ['success', 'failed', 'skipped']);
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index('sync_log_id');
            $table->index(['device_id', 'record_type', 'status']);
            $table->index('target_user_id');
            $table->index(['status', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_push_results');
    }
};
