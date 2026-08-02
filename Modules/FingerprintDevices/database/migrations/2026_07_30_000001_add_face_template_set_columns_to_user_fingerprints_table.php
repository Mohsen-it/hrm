<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Store the original ZKTeco face-component index and its enrollment batch. */
    public function up(): void
    {
        Schema::table('user_fingerprints', function (Blueprint $table): void {
            $table->unsignedSmallInteger('template_index')->nullable()->after('template_type');
            $table->string('face_template_set_id', 100)->nullable()->after('template_index');
            $table->index(
                ['user_id', 'device_id', 'face_template_set_id'],
                'user_fingerprint_face_set_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('user_fingerprints', function (Blueprint $table): void {
            $table->dropIndex('user_fingerprint_face_set_index');
            $table->dropColumn(['template_index', 'face_template_set_id']);
        });
    }
};
