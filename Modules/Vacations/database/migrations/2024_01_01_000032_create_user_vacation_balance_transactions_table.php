<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `user_vacation_balance_transactions` is the audit trail for every
     * change to a balance row (entitlement grant, carry-over, request
     * approval, manual adjustment, year-end reset).
     */
    public function up(): void
    {
        Schema::create('user_vacation_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balance_id')->constrained('user_vacation_balances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vacation_type_id')->constrained('vacation_types')->restrictOnDelete();

            $table->enum('type', [
                'grant', 'carry_over', 'request_approved',
                'request_rejected', 'request_cancelled',
                'manual_adjustment', 'year_reset',
            ]);
            $table->integer('days_delta');
            $table->unsignedSmallInteger('balance_after')->default(0);

            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'vacation_type_id', 'type'], 'vbt_user_type_kind_idx');
            $table->index(['reference_type', 'reference_id'], 'vbt_reference_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_vacation_balance_transactions');
    }
};
