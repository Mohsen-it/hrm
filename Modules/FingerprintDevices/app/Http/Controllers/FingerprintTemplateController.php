<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\FingerprintDevices\Http\Requests\UpdateUserFingerprintRequest;
use Modules\FingerprintDevices\Http\Resources\UserFingerprintResource;
use Modules\FingerprintDevices\Models\FingerprintTemplate;
use Modules\FingerprintDevices\Repositories\UserFingerprintRepository;
use Modules\FingerprintDevices\Services\MasterFingerprintService;

/**
 * FingerprintTemplateController — admin view over the fingerprint catalogue.
 *
 * This controller exposes the master-template operations as regular CRUD
 * endpoints so that operators can promote / demote templates, inspect
 * device-specific captures, and clean up stale rows.
 */
class FingerprintTemplateController extends Controller
{
    public function __construct(
        private MasterFingerprintService $service,
        private UserFingerprintRepository $repository,
    ) {}

    public function index(): Response
    {
        $this->authorize('view-fingerprint-devices');

        $filters = $this->cleanFilters(request()->only([
            'user_id', 'device_id', 'finger_id', 'is_master', 'search',
        ]));

        $query = FingerprintTemplate::query()->with(['user', 'device']);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['device_id'])) {
            $query->where('device_id', (int) $filters['device_id']);
        }
        if (($filters['finger_id'] ?? null) !== null) {
            $query->where('finger_id', (int) $filters['finger_id']);
        }
        if (isset($filters['is_master'])) {
            $query->where('is_master', (bool) $filters['is_master']);
        }
        if (! empty($filters['search'])) {
            $term = (string) $filters['search'];
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $templates = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('FingerprintDevices/Templates/Index', [
            'filters' => fn () => $filters,
            'templates' => fn () => $templates->through(
                fn ($t) => (new UserFingerprintResource($t))->toArray(request())
            ),
        ]);
    }

    public function show(int $id): Response
    {
        $this->authorize('view-fingerprint-devices');

        $template = $this->repository->findById($id);
        if (! $template) {
            abort(404);
        }

        return Inertia::render('FingerprintDevices/Templates/Show', [
            'template' => fn () => (new UserFingerprintResource($template))->toArray(request()),
        ]);
    }

    public function update(UpdateUserFingerprintRequest $request, int $id): RedirectResponse
    {
        $template = $this->repository->findById($id);
        if (! $template) {
            abort(404);
        }

        $data = $request->validated();

        if (($data['is_master'] ?? false) === true) {
            $this->service->setAsMaster($template->user_id, $template->id);
            unset($data['is_master']);
        }

        if (! empty($data)) {
            $template->update($data);
        }

        return redirect()->route('fingerprint-templates.index')
            ->with('success', __('fingerprint_devices.template_updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-fingerprint-devices');

        $template = $this->repository->findById($id);
        if (! $template) {
            abort(404);
        }

        $template->delete();

        return redirect()->route('fingerprint-templates.index')
            ->with('success', __('fingerprint_devices.template_deleted'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter(
            $filters,
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}
