<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('iclock_transaction')) {
            return;
        }

        Schema::create('iclock_transaction', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code', 20)->nullable();
            $table->unsignedBigInteger('emp_id')->nullable()->index();
            $table->dateTime('punch_time')->nullable()->index();
            $table->string('punch_state', 5)->nullable();
            $table->unsignedInteger('verify_type')->nullable();
            $table->string('work_code', 20)->nullable();
            $table->string('terminal_sn', 50)->nullable();
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->integer('duration')->nullable();
            $table->string('source', 20)->nullable();
            $table->timestamps();

            $table->index(['emp_id', 'punch_time'], 'idx_iclock_emp_punch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iclock_transaction');
    }
};
