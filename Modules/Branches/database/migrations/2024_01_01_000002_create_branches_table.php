<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('branch_code', 50);
            $table->string('branch_name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('address2')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('country', 10)->nullable();
            $table->string('state', 20)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('manager_name')->nullable();
            $table->string('manager_phone', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_main')->default(false);
            $table->smallInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'branch_code']);
            $table->index(['company_id', 'status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
