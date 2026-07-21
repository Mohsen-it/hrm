<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Monthly schedule entries generated from rotations do not always map to a
     * legacy shift category. Making duty_category_id nullable allows the
     * generation pipeline to insert WORK/REST days without inventing a fake
     * category reference.
     *
     * For SQLite the original create migration already defines the column as
     * nullable, so this migration only runs on engines that support ALTER COLUMN.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->dropForeign(['duty_category_id']);
            $table->foreignId('duty_category_id')->nullable()->change();
        });

        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->foreign('duty_category_id')
                ->references('id')
                ->on('att_shift_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->dropForeign(['duty_category_id']);
            $table->foreignId('duty_category_id')->nullable(false)->change();
        });

        Schema::table('schedule_entries', function (Blueprint $table) {
            $table->foreign('duty_category_id')
                ->references('id')
                ->on('att_shift_categories')
                ->cascadeOnDelete();
        });
    }
};
