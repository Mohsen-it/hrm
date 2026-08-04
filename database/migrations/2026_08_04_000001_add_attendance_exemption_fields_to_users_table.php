<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add employee states that exclude a person from absence calculations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('attendance_exemption_type', 20)->nullable()->after('termination_date');
            $table->date('attendance_exemption_from')->nullable()->after('attendance_exemption_type');
            $table->date('attendance_exemption_to')->nullable()->after('attendance_exemption_from');
            $table->index(['attendance_exemption_from', 'attendance_exemption_to'], 'users_attendance_exemption_dates_index');
        });
    }

    /**
     * Remove the attendance-exemption fields.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_attendance_exemption_dates_index');
            $table->dropColumn([
                'attendance_exemption_type',
                'attendance_exemption_from',
                'attendance_exemption_to',
            ]);
        });
    }
};
