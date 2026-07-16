<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_rotation_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('rotation_id');
            $table->unsignedBigInteger('rotation_group_id');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('Null means active');
            $table->json('snapshot_data')->nullable()->comment('Snapshot of rotation + group config at assignment time');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('rotation_id')->references('id')->on('att_rotations')->cascadeOnDelete();
            $table->foreign('rotation_group_id')->references('id')->on('att_rotation_groups')->cascadeOnDelete();

            $table->index('employee_id');
            $table->index('rotation_id');
            $table->index('rotation_group_id');
            $table->index(['start_date', 'end_date']);
            $table->index(['employee_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_rotation_assignments');
    }
};
