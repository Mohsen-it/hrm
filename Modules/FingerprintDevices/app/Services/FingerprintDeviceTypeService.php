<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\FingerprintDevices\Http\Requests\StoreFingerprintDeviceTypeRequest;
use Modules\FingerprintDevices\Http\Requests\UpdateFingerprintDeviceTypeRequest;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceTypeRepository;

/**
 * FingerprintDeviceTypeService — CRUD for device type catalog.
 */
class FingerprintDeviceTypeService
{
    public function __construct(
        private FingerprintDeviceTypeRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAllDeviceTypes(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * @return Collection<int, FingerprintDeviceType>
     */
    public function getActiveDeviceTypes(): Collection
    {
        return $this->repository->getActive();
    }

    public function getDeviceTypeById(int $id): ?FingerprintDeviceType
    {
        return $this->repository->findById($id);
    }

    public function createDeviceType(StoreFingerprintDeviceTypeRequest $request): FingerprintDeviceType
    {
        return $this->repository->create($request->validatedPayload());
    }

    public function updateDeviceType(UpdateFingerprintDeviceTypeRequest $request, FingerprintDeviceType $type): FingerprintDeviceType
    {
        return $this->repository->update($type, $request->validated());
    }

    public function deleteDeviceType(FingerprintDeviceType $type): bool
    {
        if ($type->devices()->exists()) {
            throw ValidationException::withMessages([
                'base' => __('fingerprint_devices.type_has_devices'),
            ]);
        }

        return $this->repository->delete($type);
    }
}
