<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recalculation with weekends/holidays counted can legitimately push a
     * balance below zero (overdraft). The audit column `balance_after` and
     * the ledger column `days_adjustment` were unsigned, so recording a
     * negative value crashed with SQLSTATE 1264. Convert both to signed.
     *
     * SQLite does not enforce unsigned ranges, so this is a no-op there.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `user_vacation_balance_transactions` MODIFY `balance_after` SMALLINT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE `user_vacation_balances` MODIFY `days_adjustment` SMALLINT NOT NULL DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `user_vacation_balance_transactions` MODIFY `balance_after` SMALLINT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE `user_vacation_balances` MODIFY `days_adjustment` SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    }
};
