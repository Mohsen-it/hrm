<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('fingerprint_devices')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('direction', ['pull', 'push', 'bidirectional'])->default('pull');
            $table->json('steps')->nullable();
            $table->json('totals')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->enum('status', ['running', 'completed', 'failed', 'partial'])->default('running');
            $table->timestamps();

            $table->index(['device_id', 'started_at']);
            $table->index('status');
            $table->index(['user_id', 'started_at']);
            $table->index(['direction', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sync_logs');
    }
};
