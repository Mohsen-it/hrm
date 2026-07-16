<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_timeinterval', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 50)->unique();
            $table->smallInteger('use_mode')->default(0);
            $table->time('in_time');
            $table->integer('in_ahead_margin')->default(0);
            $table->integer('in_above_margin')->default(0);
            $table->integer('out_ahead_margin')->default(0);
            $table->integer('out_above_margin')->default(0);
            $table->integer('duration')->default(0);
            $table->smallInteger('in_required')->default(1);
            $table->smallInteger('out_required')->default(1);
            $table->integer('allow_late')->default(0);
            $table->integer('allow_leave_early')->default(0);
            $table->double('work_day')->default(1.0);
            $table->smallInteger('early_in')->default(0);
            $table->integer('min_early_in')->default(0);
            $table->smallInteger('late_out')->default(0);
            $table->integer('min_late_out')->default(0);
            $table->smallInteger('overtime_lv')->default(0);
            $table->smallInteger('overtime_lv1')->default(0);
            $table->smallInteger('overtime_lv2')->default(0);
            $table->smallInteger('overtime_lv3')->default(0);
            $table->smallInteger('multiple_punch')->default(0);
            $table->smallInteger('available_interval_type')->default(0);
            $table->integer('available_interval')->default(0);
            $table->integer('work_time_duration')->default(0);
            $table->smallInteger('func_key')->default(0);
            $table->smallInteger('work_type')->default(0);
            $table->time('day_change');
            $table->boolean('enable_early_in')->default(false);
            $table->boolean('enable_late_out')->default(false);
            $table->boolean('enable_overtime')->default(false);
            $table->uuid('ot_rule')->nullable();
            $table->string('color_setting', 30)->nullable();
            $table->boolean('enable_max_ot_limit')->default(false);
            $table->integer('max_ot_limit')->default(0);
            $table->boolean('count_early_in_interval')->default(false);
            $table->boolean('count_late_out_interval')->default(false);
            $table->unsignedBigInteger('ot_pay_code_id')->nullable();
            $table->smallInteger('overtime_policy')->default(0);
            $table->foreignId('company_id')->constrained('companies');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ot_pay_code_id')->references('id')->on('att_paycode')->nullOnDelete();
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_timeinterval');
    }
};
