<?php

namespace Modules\Vacations\Services;

use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Modules\Users\Models\User;
use Modules\Vacations\Events\VacationApproved;
use Modules\Vacations\Events\VacationCancelled;
use Modules\Vacations\Events\VacationRejected;
use Modules\Vacations\Events\VacationRequested;
use Modules\Vacations\Models\UserVacationRequest;
use Modules\Vacations\Models\VacationType;
use Modules\Vacations\Repositories\UserVacationRequestRepository;

/**
 * VacationRequestService — orchestrates the lifecycle of a single request.
 *
 * The service implements the four transitions a request can go through:
 *   - open    : pending + reserve pending days on the balance
 *   - approve : consume reserved days + emit `VacationApproved`
 *   - reject  : release reserved days + emit `VacationRejected`
 *   - cancel  : release reserved or used days + emit `VacationCancelled`
 *
 * Day-counting uses `VacationBalanceService::projectDays()` so a single
 * source of truth governs both the entitlement math and the request
 * payload.
 */
class VacationRequestService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private UserVacationRequestRepository $repository,
        private VacationTypeService $typeService,
        private VacationBalanceService $balanceService,
        private HolidayLookup $holidayLookup,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Get a paginated list of requests filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllRequests(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Find a request by its primary key.
     */
    public function findRequest(int $id): ?UserVacationRequest
    {
        return $this->repository->findById($id);
    }

    // ------------------------------------------------------------------
    // Lifecycle writes
    // ------------------------------------------------------------------

    /**
     * Open a new vacation request.
     *
     * @param  array<string, mixed>  $data
     */
    public function openRequest(array $data, ?int $createdBy = null): UserVacationRequest
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $typeId = (int) ($data['vacation_type_id'] ?? 0);
        $start = (string) ($data['start_date'] ?? '');
        $end = (string) ($data['end_date'] ?? '');

        if ($userId <= 0 || $typeId <= 0 || $start === '' || $end === '') {
            throw new InvalidArgumentException(
                __('vacations.missing_required_fields')
            );
        }

        $user = User::find($userId);
        if (! $user) {
            throw new InvalidArgumentException(
                __('vacations.user_not_found', ['id' => $userId])
            );
        }
        if ($user->isSuperAdmin()) {
            throw new InvalidArgumentException(
                __('vacations.super_admin_cannot_request')
            );
        }

        $type = $this->typeService->findType($typeId);
        if (! $type || ! $type->is_active) {
            throw new InvalidArgumentException(
                __('vacations.type_not_found', ['id' => $typeId])
            );
        }

        $startDt = new DateTimeImmutable($start);
        $endDt = new DateTimeImmutable($end);
        if ($endDt < $startDt) {
            throw new InvalidArgumentException(
                __('vacations.end_date_must_be_after_start')
            );
        }

        $today = new DateTimeImmutable('today');
        if ($startDt < $today) {
            throw new InvalidArgumentException(
                __('vacations.start_date_must_be_today_or_later')
            );
        }

        if ($type->advance_notice_days > 0) {
            $earliest = $today->modify("+{$type->advance_notice_days} days");
            if ($startDt < $earliest) {
                throw new InvalidArgumentException(
                    __('vacations.advance_notice_required', [
                        'days' => $type->advance_notice_days,
                    ])
                );
            }
        }

        $daysCount = (int) $startDt->diff($endDt)->days + 1;
        $workingDays = $this->balanceService->projectDays($type, $start, $end, $this->holidayLookup);

        if ($type->max_days_per_request > 0 && $workingDays > $type->max_days_per_request) {
            throw new InvalidArgumentException(
                __('vacations.exceeds_max_per_request', [
                    'max' => $type->max_days_per_request,
                ])
            );
        }

        // Reserve pending days on the balance (always — even for non-deducting
        // types — so the audit trail is consistent).
        $year = (int) $startDt->format('Y');
        $balance = $this->balanceService->resolveBalance($userId, $typeId, $year);

        if ($type->deducts_from_balance) {
            $remaining = $balance->daysRemaining();
            if ($workingDays > $remaining) {
                throw new InvalidArgumentException(
                    __('vacations.insufficient_balance', [
                        'remaining' => $remaining,
                        'requested' => $workingDays,
                    ])
                );
            }
        }

        if ($type->requires_attachment && empty($data['attachments'])) {
            throw new InvalidArgumentException(
                __('vacations.attachment_required')
            );
        }

        $balanceAfter = $type->deducts_from_balance
            ? $this->balanceService->reserveDays($userId, $typeId, $year, $workingDays, null, $createdBy)
            : $balance;

        $managerId = $data['manager_id'] ?? $user->manager_id;

        $request = $this->repository->create([
            'user_id' => $userId,
            'vacation_type_id' => $typeId,
            'manager_id' => $managerId,
            'balance_id' => $balanceAfter->id,
            'start_date' => $start,
            'end_date' => $end,
            'days_count' => $daysCount,
            'working_days_count' => $workingDays,
            'status' => UserVacationRequest::STATUS_PENDING,
            'reason' => $data['reason'] ?? null,
            'attachments' => $data['attachments'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'requested_at' => new DateTimeImmutable,
        ]);

        Event::dispatch(new VacationRequested($request));

        return $request->fresh(['user', 'manager', 'vacationType', 'balance']);
    }

    /**
     * Edit a request that is still in `pending` state.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRequest(UserVacationRequest $request, array $data): UserVacationRequest
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException(
                __('vacations.only_pending_can_be_edited')
            );
        }

        // Refund the previously reserved days before re-reserving.
        $oldDays = (int) $request->working_days_count;
        $oldYear = (int) $request->start_date->format('Y');
        $this->balanceService->releasePending(
            (int) $request->user_id,
            (int) $request->vacation_type_id,
            $oldYear,
            $oldDays,
            $request->id,
        );

        $payload = [
            'vacation_type_id' => (int) ($data['vacation_type_id'] ?? $request->vacation_type_id),
            'start_date' => (string) ($data['start_date'] ?? $request->start_date->format('Y-m-d')),
            'end_date' => (string) ($data['end_date'] ?? $request->end_date->format('Y-m-d')),
            'reason' => $data['reason'] ?? $request->reason,
            'attachments' => $data['attachments'] ?? $request->attachments,
            'metadata' => $data['metadata'] ?? $request->metadata,
        ];

        $type = $this->typeService->findType($payload['vacation_type_id']);
        if (! $type) {
            throw new InvalidArgumentException(
                __('vacations.type_not_found', ['id' => $payload['vacation_type_id']])
            );
        }

        $newDays = $this->balanceService->projectDays(
            $type,
            $payload['start_date'],
            $payload['end_date'],
            $this->holidayLookup,
        );

        $newYear = (int) (new DateTimeImmutable($payload['start_date']))->format('Y');
        $balance = $this->balanceService->resolveBalance(
            (int) $request->user_id,
            (int) $payload['vacation_type_id'],
            $newYear,
        );

        if ($type->deducts_from_balance) {
            $remaining = $balance->daysRemaining();
            if ($newDays > $remaining) {
                throw new InvalidArgumentException(
                    __('vacations.insufficient_balance', [
                        'remaining' => $remaining,
                        'requested' => $newDays,
                    ])
                );
            }
            $balance = $this->balanceService->reserveDays(
                (int) $request->user_id,
                (int) $payload['vacation_type_id'],
                $newYear,
                $newDays,
                $request->id,
            );
        }

        $payload['working_days_count'] = $newDays;
        $payload['days_count'] = (int) (new DateTimeImmutable($payload['start_date']))
            ->diff(new DateTimeImmutable($payload['end_date']))->days + 1;
        $payload['balance_id'] = $balance->id;

        $updated = $this->repository->update($request, $payload);

        return $updated;
    }

    /**
     * Approve a request: convert pending days to used, stamp the decision.
     */
    public function approveRequest(UserVacationRequest $request, ?int $decidedBy = null, ?string $note = null): UserVacationRequest
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException(
                __('vacations.only_pending_can_be_approved')
            );
        }

        $type = $this->typeService->findType((int) $request->vacation_type_id);
        $year = (int) $request->start_date->format('Y');
        $days = (int) $request->working_days_count;

        $balance = $type && $type->deducts_from_balance
            ? $this->balanceService->consumeDays(
                (int) $request->user_id,
                (int) $request->vacation_type_id,
                $year,
                $days,
                $request->id,
                $decidedBy,
            )
            : $this->balanceService->resolveBalance(
                (int) $request->user_id,
                (int) $request->vacation_type_id,
                $year,
            );

        $updated = $this->repository->update($request, [
            'status' => UserVacationRequest::STATUS_APPROVED,
            'decided_at' => new DateTimeImmutable,
            'manager_note' => $note,
            'balance_id' => $balance->id,
        ]);

        Event::dispatch(new VacationApproved($updated));

        return $updated;
    }

    /**
     * Reject a pending request: release the reserved days.
     */
    public function rejectRequest(UserVacationRequest $request, ?int $decidedBy = null, ?string $note = null): UserVacationRequest
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException(
                __('vacations.only_pending_can_be_rejected')
            );
        }

        $type = $this->typeService->findType((int) $request->vacation_type_id);
        $year = (int) $request->start_date->format('Y');
        $days = (int) $request->working_days_count;

        $balance = $type && $type->deducts_from_balance
            ? $this->balanceService->releasePending(
                (int) $request->user_id,
                (int) $request->vacation_type_id,
                $year,
                $days,
                $request->id,
                $decidedBy,
            )
            : $this->balanceService->resolveBalance(
                (int) $request->user_id,
                (int) $request->vacation_type_id,
                $year,
            );

        $updated = $this->repository->update($request, [
            'status' => UserVacationRequest::STATUS_REJECTED,
            'decided_at' => new DateTimeImmutable,
            'manager_note' => $note,
            'balance_id' => $balance->id,
        ]);

        Event::dispatch(new VacationRejected($updated));

        return $updated;
    }

    /**
     * Cancel a request: release pending (if still pending) or refund used
     * (if already approved). Rejected / cancelled requests are idempotent.
     */
    public function cancelRequest(UserVacationRequest $request, ?int $cancelledBy = null, ?string $note = null): UserVacationRequest
    {
        if ($request->isCancelled()) {
            return $request;
        }

        $type = $this->typeService->findType((int) $request->vacation_type_id);
        $year = (int) $request->start_date->format('Y');
        $days = (int) $request->working_days_count;
        $balance = null;

        if ($type && $type->deducts_from_balance && $days > 0) {
            $balance = $request->isPending()
                ? $this->balanceService->releasePending(
                    (int) $request->user_id,
                    (int) $request->vacation_type_id,
                    $year,
                    $days,
                    $request->id,
                    $cancelledBy,
                )
                : $this->balanceService->refundUsed(
                    (int) $request->user_id,
                    (int) $request->vacation_type_id,
                    $year,
                    $days,
                    $request->id,
                    $cancelledBy,
                );
        }

        $updated = $this->repository->update($request, [
            'status' => UserVacationRequest::STATUS_CANCELLED,
            'cancelled_at' => new DateTimeImmutable,
            'manager_note' => $note ?? $request->manager_note,
            'balance_id' => $balance?->id ?? $request->balance_id,
        ]);

        Event::dispatch(new VacationCancelled($updated));

        return $updated;
    }

    /**
     * Soft delete a request. The audit trail is kept — the request row is
     * hidden from list views but remains in the database.
     */
    public function deleteRequest(UserVacationRequest $request): bool
    {
        return $this->repository->delete($request);
    }

    // ------------------------------------------------------------------
    // Helpers exposed to controllers
    // ------------------------------------------------------------------

    /**
     * Compute the number of working days for a hypothetical request.
     */
    public function previewDays(VacationType $type, string $from, string $to): int
    {
        return $this->balanceService->projectDays($type, $from, $to, $this->holidayLookup);
    }
}
