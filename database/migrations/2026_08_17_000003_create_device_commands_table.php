<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The FingerprintDevices module already owns a live `device_commands`
        // table (device_id / command_body, statuses pending|sending|completed|failed).
        // This migration is the promt.md-style ADMS variant; skip it rather than
        // crash the migrator when the module table exists (production and tests).
        if (Schema::hasTable('device_commands')) {
            return;
        }

        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->string('device_sn', 64)->index();
            $table->text('command_string')->comment('Full ZKTeco command: C:123:DATA UPDATE USER PIN=1001\tName=Ahmad...');
            $table->enum('status', ['pending', 'dispatched', 'executed', 'failed'])->default('pending')->index();
            $table->unsignedInteger('sequence')->default(0)->comment('Ordering for command execution on device');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->text('error_message')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['device_sn', 'status', 'sequence']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};