<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('fingerprint_devices')->nullOnDelete();
            $table->tinyInteger('finger_id')->default(0);
            $table->binary('template_data')->nullable();
            $table->string('template_format', 30)->default('ZKTeco');
            $table->integer('template_version')->default(9);
            $table->tinyInteger('quality')->default(0);
            $table->boolean('is_master')->default(false);
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'finger_id']);
            $table->index(['device_id', 'synced_at']);
            $table->index(['is_master', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fingerprints');
    }
};
