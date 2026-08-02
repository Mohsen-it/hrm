<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add source and deduplication metadata for realtime ADMS biometric templates. */
    public function up(): void
    {
        Schema::table('user_fingerprints', function (Blueprint $table): void {
            $table->string('device_serial', 100)->nullable()->after('device_id');
            $table->string('template_type', 30)->nullable()->after('template_format');
            $table->char('template_hash', 64)->nullable()->after('template_data');
            $table->json('template_metadata')->nullable()->after('template_hash');
            $table->index(['user_id', 'device_serial', 'template_hash'], 'user_fingerprint_adms_dedupe_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_fingerprints', function (Blueprint $table): void {
            $table->dropIndex('user_fingerprint_adms_dedupe_index');
            $table->dropColumn(['device_serial', 'template_type', 'template_hash', 'template_metadata']);
        });
    }
};
