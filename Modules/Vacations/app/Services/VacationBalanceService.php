<?php

namespace Modules\Vacations\Services;

use Carbon\CarbonPeriod;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Modules\Users\Models\User;
use Modules\Vacations\Models\UserVacationBalance;
use Modules\Vacations\Models\VacationType;
use Modules\Vacations\Repositories\UserVacationBalanceRepository;
use Modules\Vacations\Repositories\UserVacationBalanceTransactionRepository;

/**
 * VacationBalanceService — the single owner of the per-user entitlement ledger.
 *
 * Responsibilities:
 *  - Grant entitlements when a new year opens (or a new employee joins).
 *  - Reserve pending days when a request is opened.
 *  - Convert pending days to used days when a request is approved.
 *  - Refund pending / used days when a request is rejected / cancelled.
 *  - Roll over unused days at year-end (`runYearEndCarry`).
 *  - Maintain the immutable audit trail of every change.
 *
 * The service is intentionally stateless across requests — its only
 * state is the database. Every change is wrapped in a transaction so a
 * partial write never leaves the ledger inconsistent.
 */
class VacationBalanceService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private UserVacationBalanceRepository $balanceRepository,
        private UserVacationBalanceTransactionRepository $transactionRepository,
        private VacationTypeService $typeService,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Return every balance held by the supplied user across types / years.
     *
     * @return Collection<int, UserVacationBalance>
     */
    public function getBalancesForUser(int $userId): Collection
    {
        return $this->balanceRepository->getForUser($userId);
    }

    /**
     * Return the (user, type, year) balance, creating it on first use.
     */
    public function resolveBalance(int $userId, int $typeId, int $year): UserVacationBalance
    {
        $existing = $this->balanceRepository->findForUserTypeYear($userId, $typeId, $year);
        if ($existing) {
            return $existing;
        }

        $type = $this->typeService->findType($typeId);
        if (! $type) {
            throw new InvalidArgumentException(
                __('vacations.type_not_found', ['id' => $typeId])
            );
        }

        return $this->grant($userId, $type, $year, 0, null, false);
    }

    /**
     * Compute how many days a hypothetical request would consume.
     *
     * Uses the type's `counts_weekends` / `counts_holidays` flags to
     * decide which days of the supplied range should be charged against
     * the balance.
     */
    public function projectDays(VacationType $type, string $from, string $to, ?HolidayLookup $holidayLookup = null): int
    {
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs === false || $toTs === false || $fromTs > $toTs) {
            return 0;
        }

        $count = 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->format('Y-m-d');
            $weekday = (int) $day->format('N'); // 1 (Mon) .. 7 (Sun)

            $isWeekend = $weekday === 5 || $weekday === 6; // Fri/Sat — Middle-East default
            $isHoliday = $holidayLookup ? $holidayLookup->isHoliday($date) : false;

            if ($isWeekend && ! $type->counts_weekends) {
                continue;
            }
            if ($isHoliday && ! $type->counts_holidays) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    // ------------------------------------------------------------------
    // Writes — entitlement management
    // ------------------------------------------------------------------

    /**
     * Grant (or top-up) the supplied balance, defaulting to the type's
     * `default_days_per_year` when no explicit amount is passed.
     *
     * The grant is logged as a `grant` transaction in the audit trail.
     *
     * @param  array<string, mixed>|null  $reference  Optional morph-like reference.
     */
    public function grant(
        int $userId,
        VacationType $type,
        int $year,
        ?int $days = null,
        ?int $createdBy = null,
        bool $logTransaction = true,
        ?array $reference = null,
    ): UserVacationBalance {
        $amount = $days ?? (int) $type->default_days_per_year;
        if ($amount < 0) {
            throw new InvalidArgumentException(
                __('vacations.negative_grant_not_allowed')
            );
        }

        return $this->upsertWithDelta(
            $userId,
            $type->id,
            $year,
            daysEntitledDelta: $amount,
            type: 'grant',
            notes: $days === null ? __('vacations.grant_from_default') : null,
            createdBy: $createdBy,
            logTransaction: $logTransaction,
            reference: $reference,
        );
    }

    /**
     * Apply a year-end carry: copy remaining (used ≤ entitled + carry) days
     * from the supplied year to the next one, capped by the type's
     * `max_carry_days`.
     *
     * @return array{processed:int, carried_total:int}
     */
    public function runYearEndCarry(int $fromYear, int $toYear, ?int $createdBy = null): array
    {
        $processed = 0;
        $carriedTotal = 0;

        $balances = $this->balanceRepository->getAllForYear($fromYear);

        foreach ($balances as $balance) {
            /** @var UserVacationBalance $balance */
            $type = $balance->vacationType ?: $this->typeService->findType((int) $balance->vacation_type_id);
            if (! $type || ! $type->deducts_from_balance) {
                continue;
            }

            $remaining = (int) $balance->days_entitled
                + (int) $balance->days_carried_over
                + (int) $balance->days_adjustment
                - (int) $balance->days_used
                - (int) $balance->days_pending;

            $carryCap = (int) ($type->max_carry_days ?? 0);
            $carryCap = $carryCap > 0 ? $carryCap : (int) config('vacations.default_carry_days', 0);
            $carry = max(0, min($remaining, $carryCap));

            if ($carry <= 0) {
                continue;
            }

            $this->upsertWithDelta(
                (int) $balance->user_id,
                (int) $balance->vacation_type_id,
                $toYear,
                daysCarriedOverDelta: $carry,
                type: 'carry_over',
                notes: __('vacations.carry_from_year', ['year' => $fromYear]),
                createdBy: $createdBy,
                logTransaction: true,
                reference: ['reference_type' => 'year_carry', 'reference_id' => $fromYear],
            );

            $processed++;
            $carriedTotal += $carry;
        }

        return ['processed' => $processed, 'carried_total' => $carriedTotal];
    }

    // ------------------------------------------------------------------
    // Writes — request lifecycle hooks
    // ------------------------------------------------------------------

    /**
     * Reserve `days` against the balance when a request is opened.
     */
    public function reserveDays(int $userId, int $typeId, int $year, int $days, ?int $referenceId = null, ?int $createdBy = null): UserVacationBalance
    {
        if ($days < 0) {
            throw new InvalidArgumentException(
                __('vacations.negative_reserve_not_allowed')
            );
        }

        return $this->upsertWithDelta(
            $userId,
            $typeId,
            $year,
            daysPendingDelta: $days,
            type: 'request_approved',
            notes: __('vacations.reserve_pending_days', ['days' => $days]),
            createdBy: $createdBy,
            logTransaction: true,
            reference: ['reference_type' => 'vacation_request', 'reference_id' => $referenceId],
        );
    }

    /**
     * Convert `days` of pending to used when a request is approved.
     */
    public function consumeDays(int $userId, int $typeId, int $year, int $days, ?int $referenceId = null, ?int $createdBy = null): UserVacationBalance
    {
        if ($days < 0) {
            throw new InvalidArgumentException(
                __('vacations.negative_consume_not_allowed')
            );
        }

        return $this->upsertWithDelta(
            $userId,
            $typeId,
            $year,
            daysPendingDelta: -1 * $days,
            daysUsedDelta: $days,
            type: 'request_approved',
            notes: __('vacations.consume_approved_days', ['days' => $days]),
            createdBy: $createdBy,
            logTransaction: true,
            reference: ['reference_type' => 'vacation_request', 'reference_id' => $referenceId],
        );
    }

    /**
     * Refund `days` of pending when a request is rejected.
     */
    public function releasePending(int $userId, int $typeId, int $year, int $days, ?int $referenceId = null, ?int $createdBy = null): UserVacationBalance
    {
        if ($days < 0) {
            throw new InvalidArgumentException(
                __('vacations.negative_release_not_allowed')
            );
        }

        return $this->upsertWithDelta(
            $userId,
            $typeId,
            $year,
            daysPendingDelta: -1 * $days,
            type: 'request_rejected',
            notes: __('vacations.release_rejected_days', ['days' => $days]),
            createdBy: $createdBy,
            logTransaction: true,
            reference: ['reference_type' => 'vacation_request', 'reference_id' => $referenceId],
        );
    }

    /**
     * Refund `days` of used when a previously approved request is cancelled.
     */
    public function refundUsed(int $userId, int $typeId, int $year, int $days, ?int $referenceId = null, ?int $createdBy = null): UserVacationBalance
    {
        if ($days < 0) {
            throw new InvalidArgumentException(
                __('vacations.negative_refund_not_allowed')
            );
        }

        return $this->upsertWithDelta(
            $userId,
            $typeId,
            $year,
            daysUsedDelta: -1 * $days,
            type: 'request_cancelled',
            notes: __('vacations.refund_cancelled_days', ['days' => $days]),
            createdBy: $createdBy,
            logTransaction: true,
            reference: ['reference_type' => 'vacation_request', 'reference_id' => $referenceId],
        );
    }

    /**
     * Apply an operator-driven manual adjustment.
     */
    public function adjust(int $userId, int $typeId, int $year, int $daysDelta, ?int $createdBy = null, ?string $notes = null): UserVacationBalance
    {
        return $this->upsertWithDelta(
            $userId,
            $typeId,
            $year,
            daysAdjustmentDelta: $daysDelta,
            type: 'manual_adjustment',
            notes: $notes,
            createdBy: $createdBy,
            logTransaction: true,
            reference: null,
        );
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Apply a positive or negative delta to one or more balance columns,
     * creating the (user, type, year) row on first use and writing the
     * matching audit transaction.
     */
    protected function upsertWithDelta(
        int $userId,
        int $typeId,
        int $year,
        int $daysEntitledDelta = 0,
        int $daysUsedDelta = 0,
        int $daysPendingDelta = 0,
        int $daysCarriedOverDelta = 0,
        int $daysAdjustmentDelta = 0,
        string $type = 'manual_adjustment',
        ?string $notes = null,
        ?int $createdBy = null,
        bool $logTransaction = true,
        ?array $reference = null,
    ): UserVacationBalance {
        return \DB::transaction(function () use (
            $userId, $typeId, $year,
            $daysEntitledDelta, $daysUsedDelta, $daysPendingDelta, $daysCarriedOverDelta, $daysAdjustmentDelta,
            $type, $notes, $createdBy, $logTransaction, $reference,
        ): UserVacationBalance {
            $balance = $this->balanceRepository->findForUserTypeYear($userId, $typeId, $year);
            $wasNew = $balance === null;

            $data = [
                'days_entitled' => max(0, ($wasNew ? 0 : (int) $balance->days_entitled) + $daysEntitledDelta),
                'days_used' => max(0, ($wasNew ? 0 : (int) $balance->days_used) + $daysUsedDelta),
                'days_pending' => max(0, ($wasNew ? 0 : (int) $balance->days_pending) + $daysPendingDelta),
                'days_carried_over' => max(0, ($wasNew ? 0 : (int) $balance->days_carried_over) + $daysCarriedOverDelta),
                'days_adjustment' => ($wasNew ? 0 : (int) $balance->days_adjustment) + $daysAdjustmentDelta,
                'last_recalculated_at' => new DateTimeImmutable,
            ];

            $balance = $wasNew
                ? $this->balanceRepository->create(array_merge([
                    'user_id' => $userId,
                    'vacation_type_id' => $typeId,
                    'year' => $year,
                ], $data))
                : $this->balanceRepository->update($balance, $data);

            if ($logTransaction) {
                $delta = $daysEntitledDelta + $daysUsedDelta + $daysPendingDelta
                    + $daysCarriedOverDelta + $daysAdjustmentDelta;

                $this->transactionRepository->create([
                    'balance_id' => $balance->id,
                    'user_id' => $userId,
                    'vacation_type_id' => $typeId,
                    'type' => $type,
                    'days_delta' => $delta,
                    'balance_after' => $balance->daysRemaining(),
                    'reference_type' => $reference['reference_type'] ?? null,
                    'reference_id' => $reference['reference_id'] ?? null,
                    'notes' => $notes,
                    'created_by' => $createdBy,
                ]);
            }

            return $balance;
        });
    }
}
