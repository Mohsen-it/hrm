<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('att_rotations', function (Blueprint $table) {
            $table->time('in_ahead_margin')->nullable()->after('grace_minutes');
            $table->time('in_above_margin')->nullable()->after('in_ahead_margin');
            $table->time('out_ahead_margin')->nullable()->after('in_above_margin');
            $table->time('out_above_margin')->nullable()->after('out_ahead_margin');
        });
    }

    public function down(): void
    {
        Schema::table('att_rotations', function (Blueprint $table) {
            $table->dropColumn([
                'in_ahead_margin',
                'in_above_margin',
                'out_ahead_margin',
                'out_above_margin',
            ]);
        });
    }
};
