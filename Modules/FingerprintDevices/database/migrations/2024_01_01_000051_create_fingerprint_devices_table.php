<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_type_id')->constrained('fingerprint_device_types')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 150);
            $table->string('serial_number', 100)->unique();
            $table->string('ip_address', 45);
            $table->integer('port')->default(4370);
            $table->integer('comm_key')->default(0);
            $table->string('timezone', 50)->default('Asia/Baghdad');
            $table->enum('connection_type', ['tcp', 'udp'])->default('tcp');
            $table->integer('timeout')->default(30);
            $table->enum('status', ['online', 'offline', 'maintenance', 'deactivated'])->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('capabilities')->nullable();
            $table->integer('user_count')->default(0);
            $table->integer('fingerprint_count')->default(0);
            $table->integer('attendance_log_count')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_push_enabled')->default(false);
            $table->string('push_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'deleted_at']);
            $table->index(['branch_id', 'status']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_devices');
    }
};
