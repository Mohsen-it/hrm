<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_rotation_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rotation_id');
            $table->string('name', 50)->comment('Group name, e.g. A, B, C, D');
            $table->unsignedSmallInteger('group_index')->comment('0-based offset in days from the rotation anchor');
            $table->unsignedBigInteger('time_schedule_id')->nullable()->comment('Optional time schedule override for this group');
            $table->timestamps();

            $table->foreign('rotation_id')->references('id')->on('att_rotations')->cascadeOnDelete();
            $table->foreign('time_schedule_id')->references('id')->on('att_time_schedules')->nullOnDelete();
            $table->index('rotation_id');
            $table->unique(['rotation_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_rotation_groups');
    }
};
