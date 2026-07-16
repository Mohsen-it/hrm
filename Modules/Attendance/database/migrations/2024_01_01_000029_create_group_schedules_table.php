<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_groupschedule', function (Blueprint $table) {
            $table->id();
            $table->timestamp('create_time')->nullable();
            $table->string('create_user', 150)->nullable();
            $table->timestamp('change_time')->nullable();
            $table->string('change_user', 150)->nullable();
            $table->smallInteger('status')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('group_id')->constrained('att_attgroup');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('shift_id')->references('id')->on('att_attshift')->nullOnDelete();
            $table->index(['group_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_groupschedule');
    }
};
