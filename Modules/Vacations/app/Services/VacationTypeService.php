<?php

namespace Modules\Vacations\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Vacations\Models\VacationType;
use Modules\Vacations\Repositories\VacationTypeRepository;

/**
 * VacationTypeService — CRUD orchestration for the vacation catalog.
 *
 * Owns the validation of the type payload (uniqueness of code, sane day
 * limits) and the public read helpers used by the controllers and the
 * balance service.
 */
class VacationTypeService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private VacationTypeRepository $repository,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Get a paginated list of vacation types filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllTypes(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Find a vacation type by its primary key.
     */
    public function findType(int $id): ?VacationType
    {
        return $this->repository->findById($id);
    }

    /**
     * Find a vacation type by its machine code (annual, sick, ...).
     */
    public function findByCode(string $code): ?VacationType
    {
        return $this->repository->findByCode($code);
    }

    /**
     * Return all active vacation types ordered for select boxes.
     *
     * @return Collection<int, VacationType>
     */
    public function getActiveTypes(): Collection
    {
        return $this->repository->listActive();
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Create a new vacation type.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException When the payload is invalid.
     */
    public function createType(array $data): VacationType
    {
        $payload = $this->validatePayload($data, null);

        return $this->repository->create($payload);
    }

    /**
     * Update an existing vacation type.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateType(VacationType $type, array $data): VacationType
    {
        $payload = $this->validatePayload($data, $type);

        return $this->repository->update($type, $payload);
    }

    /**
     * Soft delete a vacation type.
     */
    public function deleteType(VacationType $type): bool
    {
        // Guard: do not allow deleting the default annual type code.
        $protected = (string) config('vacations.annual_code', 'annual');

        if ($type->code === $protected) {
            throw new InvalidArgumentException(
                __('vacations.cannot_delete_protected_type', ['code' => $protected])
            );
        }

        return $this->repository->delete($type);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Validate the supplied payload, normalising it before persistence.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException When the payload is invalid.
     */
    protected function validatePayload(array $data, ?VacationType $ignore): array
    {
        if (empty($data['code']) && empty($data['name_ar'])) {
            throw new InvalidArgumentException(
                __('vacations.code_or_name_required')
            );
        }

        $code = trim((string) ($data['code'] ?? Str::slug((string) ($data['name_ar'] ?? ''), '_')));
        if ($code === '') {
            throw new InvalidArgumentException(
                __('vacations.code_required')
            );
        }

        $existing = $this->repository->findByCode($code);
        if ($existing && (! $ignore || $existing->id !== $ignore->id)) {
            throw new InvalidArgumentException(
                __('vacations.code_already_exists', ['code' => $code])
            );
        }

        $maxPerRequest = (int) ($data['max_days_per_request'] ?? 0);
        $maxCarry = (int) ($data['max_carry_days'] ?? 0);
        $defaultDays = (int) ($data['default_days_per_year'] ?? 0);

        if ($maxPerRequest < 0 || $maxCarry < 0 || $defaultDays < 0) {
            throw new InvalidArgumentException(
                __('vacations.negative_days_not_allowed')
            );
        }

        if ($maxPerRequest > 0 && $maxPerRequest > $defaultDays + $maxCarry) {
            throw new InvalidArgumentException(
                __('vacations.max_per_request_exceeds_entitlement')
            );
        }

        return [
            'code' => $code,
            'name_ar' => (string) ($data['name_ar'] ?? $code),
            'name_en' => $data['name_en'] ?? null,
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'default_days_per_year' => $defaultDays,
            'max_days_per_request' => $maxPerRequest,
            'max_carry_days' => $maxCarry,
            'advance_notice_days' => (int) ($data['advance_notice_days'] ?? 0),
            'is_paid' => (bool) ($data['is_paid'] ?? true),
            'requires_approval' => (bool) ($data['requires_approval'] ?? true),
            'requires_attachment' => (bool) ($data['requires_attachment'] ?? false),
            'deducts_from_balance' => (bool) ($data['deducts_from_balance'] ?? true),
            'counts_weekends' => (bool) ($data['counts_weekends'] ?? false),
            'counts_holidays' => (bool) ($data['counts_holidays'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'description' => $data['description'] ?? null,
        ];
    }
}
