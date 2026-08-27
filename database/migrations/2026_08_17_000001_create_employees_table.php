<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('pin', 20)->unique()->index(); // employee code / PIN
            $table->string('name', 100);
            $table->tinyInteger('privilege')->default(0); // 0=user, 1=admin, etc.
            $table->string('password', 50)->nullable()->default('');
            $table->integer('card')->nullable()->default(0);
            $table->string('group_id', 20)->nullable()->default('1');
            $table->string('tz', 20)->nullable()->default('1'); // time zones
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'pin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
