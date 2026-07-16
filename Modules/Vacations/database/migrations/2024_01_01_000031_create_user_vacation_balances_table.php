<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `user_vacation_balances` holds the entitlement ledger for one
     * (user, vacation_type, year) triple. The unique index lets the
     * service use `updateOrCreate` safely.
     */
    public function up(): void
    {
        Schema::create('user_vacation_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vacation_type_id')->constrained('vacation_types')->restrictOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('days_entitled')->default(0);
            $table->unsignedSmallInteger('days_used')->default(0);
            $table->unsignedSmallInteger('days_pending')->default(0);
            $table->unsignedSmallInteger('days_carried_over')->default(0);
            $table->unsignedSmallInteger('days_adjustment')->default(0);

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('last_recalculated_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'vacation_type_id', 'year'], 'vacation_balances_user_type_year_unique');
            $table->index(['year', 'vacation_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_vacation_balances');
    }
};
