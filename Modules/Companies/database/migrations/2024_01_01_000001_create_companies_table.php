<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 50)->unique();
            $table->string('company_name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('address2')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('country', 10)->nullable();
            $table->string('state', 20)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('logo', 200)->nullable();
            $table->text('description')->nullable();
            $table->date('established_date')->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->string('commercial_number', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->smallInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
