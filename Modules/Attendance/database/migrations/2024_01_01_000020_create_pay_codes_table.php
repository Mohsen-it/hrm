<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_paycode', function (Blueprint $table) {
            $table->id();
            $table->timestamp('create_time')->nullable();
            $table->string('create_user', 150)->nullable();
            $table->timestamp('change_time')->nullable();
            $table->string('change_user', 150)->nullable();
            $table->smallInteger('status')->default(1);
            $table->string('code', 20)->unique();
            $table->string('name', 50)->unique();
            $table->smallInteger('code_type')->default(0);
            $table->smallInteger('tag')->nullable();
            $table->smallInteger('fixed_code')->nullable();
            $table->boolean('is_work')->default(false);
            $table->decimal('fixed_hours', 8, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_benefit')->default(false);
            $table->smallInteger('round_off')->default(0);
            $table->decimal('min_val', 4, 1)->default(0);
            $table->smallInteger('display_format')->default(0);
            $table->string('symbol', 20)->nullable();
            $table->smallInteger('display_order')->default(0);
            $table->text('desc')->nullable();
            $table->boolean('is_display')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('color_setting', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_paycode');
    }
};
