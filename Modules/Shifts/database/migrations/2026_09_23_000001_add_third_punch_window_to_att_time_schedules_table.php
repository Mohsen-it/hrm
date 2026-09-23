<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('att_time_schedules', function (Blueprint $table) {
            $table->time('third_punch_start')->nullable()->after('out_above_margin')->comment('Third (evening) punch window start for overnight duties');
            $table->time('third_punch_end')->nullable()->after('third_punch_start')->comment('Third (evening) punch window end for overnight duties');
        });
    }

    public function down(): void
    {
        Schema::table('att_time_schedules', function (Blueprint $table) {
            $table->dropColumn(['third_punch_start', 'third_punch_end']);
        });
    }
};
