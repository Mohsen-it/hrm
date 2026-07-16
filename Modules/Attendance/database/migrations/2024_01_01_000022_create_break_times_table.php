<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_breaktime', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 50)->unique();
            $table->time('period_start');
            $table->integer('duration');
            $table->integer('end_margin')->default(0);
            $table->smallInteger('func_key')->default(0);
            $table->smallInteger('available_interval_type')->default(0);
            $table->integer('available_interval')->default(0);
            $table->smallInteger('multiple_punch')->default(0);
            $table->smallInteger('calc_type')->default(0);
            $table->integer('minimum_duration')->nullable();
            $table->smallInteger('early_in')->default(0);
            $table->smallInteger('late_in')->default(0);
            $table->boolean('profit_rule')->default(false);
            $table->integer('min_early_in')->default(0);
            $table->boolean('loss_rule')->default(false);
            $table->integer('min_late_in')->default(0);
            $table->unsignedBigInteger('loss_code_id')->nullable();
            $table->unsignedBigInteger('profit_code_id')->nullable();
            $table->foreignId('company_id')->constrained('companies');
            $table->timestamps();

            $table->foreign('loss_code_id')->references('id')->on('att_paycode')->nullOnDelete();
            $table->foreign('profit_code_id')->references('id')->on('att_paycode')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_breaktime');
    }
};
