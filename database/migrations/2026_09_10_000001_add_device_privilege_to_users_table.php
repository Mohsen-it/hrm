<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-employee device privilege override for ZKTeco terminals.
     *
     * null = automatic (14 for the system super-admin, 0 otherwise),
     * 0 = regular device member, 14 = device administrator.
     * Consumed by EmployeeAdmsObserver when queueing USERINFO commands.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('device_privilege')->nullable()->after('status');
        });
    }

    /**
     * Remove the device-privilege override.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('device_privilege');
        });
    }
};
