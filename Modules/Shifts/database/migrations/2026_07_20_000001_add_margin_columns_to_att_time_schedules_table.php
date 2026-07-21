<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('att_time_schedules', function (Blueprint $table) {
            $table->unsignedSmallInteger('in_ahead_margin')->default(0)->after('early_margin')->comment('Minutes before in_time check-in is allowed');
            $table->unsignedSmallInteger('in_above_margin')->default(0)->after('in_ahead_margin')->comment('Minutes after in_time check-in is accepted');
            $table->unsignedSmallInteger('out_ahead_margin')->default(0)->after('in_above_margin')->comment('Minutes before out_time check-out is allowed');
            $table->unsignedSmallInteger('out_above_margin')->default(0)->after('out_ahead_margin')->comment('Minutes after out_time check-out is accepted');
        });
    }

    public function down(): void
    {
        Schema::table('att_time_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'in_ahead_margin',
                'in_above_margin',
                'out_ahead_margin',
                'out_above_margin',
            ]);
        });
    }
};
