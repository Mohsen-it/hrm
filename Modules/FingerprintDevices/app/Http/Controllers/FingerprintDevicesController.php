<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branches\Services\BranchService;
use Modules\Companies\Services\CompanyService;
use Modules\FingerprintDevices\Http\Requests\StoreFingerprintDeviceRequest;
use Modules\FingerprintDevices\Http\Requests\UpdateFingerprintDeviceRequest;
use Modules\FingerprintDevices\Http\Resources\FingerprintDeviceResource;
use Modules\FingerprintDevices\Services\DevicePushPreviewService;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;
use Modules\FingerprintDevices\Services\FingerprintDeviceTypeService;
use Modules\Subordinations\Services\SubordinationService;

/**
 * FingerprintDevicesController — CRUD for fingerprint devices.
 */
class FingerprintDevicesController extends Controller
{
    public function __construct(
        private FingerprintDeviceService $deviceService,
        private FingerprintDeviceTypeService $typeService,
        private BranchService $branchService,
        private CompanyService $companyService,
        private SubordinationService $subordinationService,
        private DevicePushPreviewService $previewService,
    ) {}

    /**
     * @return array<int, array{id:int,branch_name:string}>
     */
    protected function branchOptions(): array
    {
        return $this->branchService->getActiveBranches()
            ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,company_name:string}>
     */
    protected function companyOptions(): array
    {
        return $this->companyService->getActiveCompanies()
            ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,name_ar:string,code:?string}>
     */
    protected function subordinationOptions(): array
    {
        return $this->subordinationService->getActiveSubordinations()
            ->map(fn ($s) => ['id' => $s->id, 'name_ar' => $s->name_ar, 'code' => $s->code])
            ->values()
            ->all();
    }

    public function index(): Response
    {
        $this->authorize('view-fingerprint-devices');

        $filters = $this->cleanFilters(request()->only([
            'search', 'status', 'device_type_id', 'branch_id', 'connection_type',
        ]));

        return Inertia::render('FingerprintDevices/Index', [
            'filters' => fn () => $filters,
            'devices' => fn () => $this->deviceService->getAllDevices($filters)
                ->through(fn ($d) => (new FingerprintDeviceResource($d))->toArray(request())),
            'deviceTypes' => fn () => $this->typeService->getActiveDeviceTypes()
                ->map(fn ($dt) => ['id' => $dt->id, 'name' => $dt->name])
                ->values(),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name])
                ->values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create-fingerprint-devices');

        return Inertia::render('FingerprintDevices/Create', [
            'deviceTypes' => fn () => $this->typeService->getActiveDeviceTypes()
                ->map(fn ($dt) => [
                    'id' => $dt->id,
                    'name' => $dt->name,
                    'manufacturer' => $dt->manufacturer,
                    'default_port' => $dt->default_port,
                ])
                ->values(),
            'branches' => fn () => $this->branchOptions(),
            'companies' => fn () => $this->companyOptions(),
            'subordinations' => fn () => $this->subordinationOptions(),
        ]);
    }

    public function store(StoreFingerprintDeviceRequest $request): RedirectResponse
    {
        $this->deviceService->createDevice($request);

        return redirect()->route('fingerprint-devices.index')
            ->with('success', __('fingerprint_devices.device_created'));
    }

    public function show(int $id): Response
    {
        $this->authorize('view-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        return Inertia::render('FingerprintDevices/Show', [
            'device' => fn () => (new FingerprintDeviceResource($device))->toArray(request()),
            'branches' => fn () => $this->branchOptions(),
        ]);
    }

    public function edit(int $id): Response
    {
        $this->authorize('edit-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        return Inertia::render('FingerprintDevices/Edit', [
            'device' => fn () => (new FingerprintDeviceResource($device))->toArray(request()),
            'deviceTypes' => fn () => $this->typeService->getActiveDeviceTypes()
                ->map(fn ($dt) => [
                    'id' => $dt->id,
                    'name' => $dt->name,
                    'manufacturer' => $dt->manufacturer,
                    'default_port' => $dt->default_port,
                ])
                ->values(),
            'branches' => fn () => $this->branchOptions(),
            'companies' => fn () => $this->companyOptions(),
            'subordinations' => fn () => $this->subordinationOptions(),
        ]);
    }

    public function update(UpdateFingerprintDeviceRequest $request, int $id): RedirectResponse
    {
        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        $this->deviceService->updateDevice($request, $device);

        return redirect()->route('fingerprint-devices.index')
            ->with('success', __('fingerprint_devices.device_updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        $this->deviceService->deleteDevice($device);

        return redirect()->route('fingerprint-devices.index')
            ->with('success', __('fingerprint_devices.device_deleted'));
    }

    public function testConnection(int $id): RedirectResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        $connected = $this->deviceService->testConnection($device);

        return redirect()->back()->with(
            $connected ? 'success' : 'error',
            $connected
                ? __('fingerprint_devices.connection_success')
                : __('fingerprint_devices.connection_failed')
        );
    }

    public function syncAttendance(int $id): RedirectResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            abort(404);
        }

        $result = $this->deviceService->syncAttendance($device);

        return redirect()->back()->with(
            'success',
            __('fingerprint_devices.sync_complete', [
                'count' => $result['pulled'],
                'imported' => $result['imported'],
                'sessions' => $result['sessions'],
            ])
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter($filters, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Read-only preview of a push operation.
     *
     * Validates the same options as the real push, but performs no writes:
     * no DB inserts, no device commands other than getUsers (a GET).
     */
    public function pushPreview(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'push_users' => ['nullable', 'boolean'],
            'push_fingerprints' => ['nullable', 'boolean'],
            'push_face_photos' => ['nullable', 'boolean'],
            'user_ids' => ['nullable', 'string', 'max:10000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'select_mode' => ['nullable', 'string', 'in:all,specific,branch,missing'],
        ]);

        $userIdsRaw = $request->query('user_ids');
        $userIds = [];
        if (is_string($userIdsRaw) && $userIdsRaw !== '') {
            $userIds = array_values(array_filter(array_map('intval', explode(',', $userIdsRaw))));
        }

        $options = [
            'push_users' => $request->boolean('push_users', true),
            'push_fingerprints' => $request->boolean('push_fingerprints', true),
            'push_face_photos' => $request->boolean('push_face_photos', false),
            'select_mode' => $request->query('select_mode', 'all'),
        ];
        if ($request->filled('branch_id')) {
            $options['branch_id'] = (int) $request->query('branch_id');
        }
        if (! empty($userIds)) {
            $options['user_ids'] = $userIds;
        }

        $device = $this->deviceService->getDeviceById($id);

        if (! $device) {
            return response()->json([
                'success' => false,
                'read_only' => true,
                'error' => 'Device not found',
            ], 404);
        }

        $preview = $this->previewService->preview($device, $options);

        return response()->json($preview);
    }
}
