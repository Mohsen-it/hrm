<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_departmentschedule', function (Blueprint $table) {
            $table->id();
            $table->timestamp('create_time')->nullable();
            $table->string('create_user', 150)->nullable();
            $table->timestamp('change_time')->nullable();
            $table->string('change_user', 150)->nullable();
            $table->smallInteger('status')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('shift_id')->constrained('att_attshift');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_departmentschedule');
    }
};
