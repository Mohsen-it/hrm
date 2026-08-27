<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->string('sn', 64)->primary()->comment('Device Serial Number');
            $table->string('name', 100)->nullable();
            $table->string('ip', 45)->nullable()->comment('Device IP address');
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown')->index();
            $table->string('firmware_version', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->integer('user_count')->default(0);
            $table->integer('face_count')->default(0);
            $table->integer('fp_count')->default(0);
            $table->boolean('is_push_enabled')->default(true);
            $table->string('driver', 20)->default('zkteco')->comment('zkteco, hikvision, etc.');
            $table->json('capabilities')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
