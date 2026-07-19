<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->unique(
                ['schedule_period_id', 'employee_id', 'date'],
                'schedule_entries_unique_per_day'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->dropUnique('schedule_entries_unique_per_day');
        });
    }
};
