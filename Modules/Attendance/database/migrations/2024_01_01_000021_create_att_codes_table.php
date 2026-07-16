<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_attcode', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('alias', 50)->unique();
            $table->smallInteger('display_format')->default(0);
            $table->string('symbol', 20);
            $table->smallInteger('round_off')->default(0);
            $table->decimal('min_val', 4, 1)->default(0);
            $table->boolean('symbol_only')->default(false);
            $table->smallInteger('order')->default(0);
            $table->string('color_setting', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_attcode');
    }
};
