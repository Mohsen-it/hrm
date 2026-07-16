<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `user_vacation_requests` is the lifecycle table for a single request.
     * The row is created in `pending` state, transitions to `approved` or
     * `rejected` after a manager decision (or `cancelled` if the employee
     * withdraws it), and is never hard-deleted (softDeletes only).
     */
    public function up(): void
    {
        Schema::create('user_vacation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vacation_type_id')->constrained('vacation_types')->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('balance_id')->nullable()->constrained('user_vacation_balances')->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_count')->default(0);
            $table->unsignedSmallInteger('working_days_count')->default(0);

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending');
            $table->text('reason')->nullable();
            $table->text('manager_note')->nullable();

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['vacation_type_id', 'status']);
            $table->index(['manager_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_vacation_requests');
    }
};
