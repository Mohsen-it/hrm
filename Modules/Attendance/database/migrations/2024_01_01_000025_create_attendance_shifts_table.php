<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_attshift', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 50);
            $table->smallInteger('cycle_unit')->default(1);
            $table->integer('shift_cycle')->default(1);
            $table->boolean('work_weekend')->default(false);
            $table->smallInteger('weekend_type')->default(0);
            $table->boolean('work_day_off')->default(false);
            $table->smallInteger('day_off_type')->default(0);
            $table->smallInteger('auto_shift')->default(0);
            $table->boolean('enable_ot_rule')->default(false);
            $table->uuid('ot_rule')->nullable();
            $table->smallInteger('frequency')->default(1);
            $table->foreignId('company_id')->constrained('companies');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_attshift');
    }
};
