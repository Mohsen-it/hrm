<?php

namespace Modules\Subordinations\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Subordinations\Models\Subordination;
use Modules\Subordinations\Repositories\SubordinationRepository;

class SubordinationService
{
    public function __construct(
        private SubordinationRepository $repository,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAllSubordinations(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * @return Collection<int, Subordination>
     */
    public function getActiveSubordinations(): Collection
    {
        return $this->repository->getActive();
    }

    public function getSubordinationById(int $id): ?Subordination
    {
        return $this->repository->findById($id);
    }

    public function getSubordinationByCode(string $code): ?Subordination
    {
        return $this->repository->findByCode($code);
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createSubordination(array $data): Subordination
    {
        $validated = $this->validateSubordinationData($data);

        return $this->repository->create($validated);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateSubordination(Subordination $subordination, array $data): Subordination
    {
        $validated = $this->validateSubordinationData($data, $subordination->id);

        return $this->repository->update($subordination, $validated);
    }

    public function deleteSubordination(Subordination $subordination): bool
    {
        return $this->repository->delete($subordination);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Validate subordination data. Throws ValidationException on failure.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateSubordinationData(array $data, ?int $ignoreId = null): array
    {
        $codeRules = ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'];
        if ($ignoreId) {
            $codeRules[] = Rule::unique('subordinations', 'code')->ignore($ignoreId);
        } else {
            $codeRules[] = 'unique:subordinations,code';
        }

        $rules = [
            'code' => $codeRules,
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        $messages = [
            'code.required' => __('subordinations.code_required'),
            'code.regex' => __('subordinations.code_regex'),
            'code.max' => __('subordinations.code_max'),
            'code.unique' => __('subordinations.code_unique'),
            'name_ar.required' => __('subordinations.name_ar_required'),
            'name_ar.max' => __('subordinations.name_ar_max'),
            'name_en.max' => __('subordinations.name_en_max'),
            'status.in' => __('subordinations.status_in'),
            'sort_order.integer' => __('subordinations.sort_order_integer'),
            'sort_order.min' => __('subordinations.sort_order_min'),
        ];

        return Validator::make($data, $rules, $messages)->validate();
    }
}
