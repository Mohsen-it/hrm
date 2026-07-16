<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('attendance_integration_audit_logs', function (Blueprint $table) {
                $table->foreign('device_id')
                    ->references('id')
                    ->on('fingerprint_devices')
                    ->nullOnDelete();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('attendance_integration_audit_logs', function (Blueprint $table) {
                $table->dropForeign(['device_id']);
                $table->dropForeign(['user_id']);
            });
        }
    }
};
