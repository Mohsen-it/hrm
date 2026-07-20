<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fingerprint_devices', function (Blueprint $table) {
            $table->timestamp('last_pushed_at')->nullable()->after('last_synced_at');
            $table->unsignedInteger('sync_log_count')->default(0)->after('last_pushed_at');
        });
    }

    public function down(): void
    {
        Schema::table('fingerprint_devices', function (Blueprint $table) {
            $table->dropColumn(['last_pushed_at', 'sync_log_count']);
        });
    }
};
