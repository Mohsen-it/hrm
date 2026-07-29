<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('fingerprint_devices')->cascadeOnDelete();
            $table->string('command_type', 50);
            $table->text('command_body');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('priority')->default(5);
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->text('error_message')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->index(['device_id', 'status', 'priority']);
            $table->index(['status', 'created_at']);
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
