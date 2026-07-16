<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\FingerprintDevices\Http\Requests\StoreFingerprintDeviceTypeRequest;
use Modules\FingerprintDevices\Http\Requests\UpdateFingerprintDeviceTypeRequest;
use Modules\FingerprintDevices\Http\Resources\FingerprintDeviceTypeResource;
use Modules\FingerprintDevices\Services\FingerprintDeviceTypeService;

/**
 * FingerprintDeviceTypesController — CRUD for device type catalog.
 */
class FingerprintDeviceTypesController extends Controller
{
    public function __construct(
        private FingerprintDeviceTypeService $typeService,
    ) {}

    public function index(): Response
    {
        $this->authorize('view-fingerprint-device-types');

        $filters = $this->cleanFilters(request()->only([
            'search', 'manufacturer', 'is_active', 'supports_fingerprint',
        ]));

        return Inertia::render('FingerprintDevices/Types/Index', [
            'filters' => fn () => $filters,
            'deviceTypes' => fn () => $this->typeService->getAllDeviceTypes($filters)
                ->through(fn ($dt) => (new FingerprintDeviceTypeResource($dt))->toArray(request())),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create-fingerprint-device-types');

        return Inertia::render('FingerprintDevices/Types/Create');
    }

    public function store(StoreFingerprintDeviceTypeRequest $request): RedirectResponse
    {
        $this->typeService->createDeviceType($request);

        return redirect()->route('fingerprint-device-types.index')
            ->with('success', __('fingerprint_devices.type_created'));
    }

    public function edit(int $id): Response
    {
        $this->authorize('edit-fingerprint-device-types');

        $type = $this->typeService->getDeviceTypeById($id);

        if (! $type) {
            abort(404);
        }

        return Inertia::render('FingerprintDevices/Types/Edit', [
            'deviceType' => fn () => (new FingerprintDeviceTypeResource($type))->toArray(request()),
        ]);
    }

    public function update(UpdateFingerprintDeviceTypeRequest $request, int $id): RedirectResponse
    {
        $type = $this->typeService->getDeviceTypeById($id);

        if (! $type) {
            abort(404);
        }

        $this->typeService->updateDeviceType($request, $type);

        return redirect()->route('fingerprint-device-types.index')
            ->with('success', __('fingerprint_devices.type_updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-fingerprint-device-types');

        $type = $this->typeService->getDeviceTypeById($id);

        if (! $type) {
            abort(404);
        }

        $this->typeService->deleteDeviceType($type);

        return redirect()->route('fingerprint-device-types.index')
            ->with('success', __('fingerprint_devices.type_deleted'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter($filters, fn ($v) => $v !== null && $v !== '');
    }
}
