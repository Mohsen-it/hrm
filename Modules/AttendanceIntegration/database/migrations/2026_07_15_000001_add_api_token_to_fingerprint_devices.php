<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fingerprint_devices', function (Blueprint $table) {
            $table->string('api_token', 128)->nullable()->after('push_url');
            $table->index('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('fingerprint_devices', function (Blueprint $table) {
            $table->dropIndex(['api_token']);
            $table->dropColumn('api_token');
        });
    }
};
