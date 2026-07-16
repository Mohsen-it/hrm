<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Settings\Http\Requests\BulkUpdateSettingsRequest;
use Modules\Settings\Http\Requests\StoreSettingRequest;
use Modules\Settings\Http\Requests\UpdateSettingRequest;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingService;

/**
 * SettingsController — manage the key-value configuration catalogue.
 */
class SettingsController extends Controller
{
    public function __construct(
        private SettingService $service,
    ) {}

    /**
     * Display the full catalogue grouped by `group`.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-settings');

        $groups = $this->service->getGroups();
        $all = $this->service->getAllSettings();
        $grouped = $all->groupBy('group');

        $payload = [];
        foreach ($groups as $group) {
            $payload[$group] = $grouped->get($group, collect())->values()->all();
        }

        return Inertia::render('Settings/Index', [
            'groups' => fn () => $groups,
            'settingsByGroup' => fn () => $payload,
        ]);
    }

    /**
     * Render the general settings group.
     */
    public function general(Request $request): Response
    {
        $this->authorize('view-settings');

        return Inertia::render('Settings/General', [
            'settings' => fn () => $this->service->getSettingsByGroup('general'),
        ]);
    }

    /**
     * Render the attendance settings group.
     */
    public function attendance(Request $request): Response
    {
        $this->authorize('view-settings');

        return Inertia::render('Settings/Attendance', [
            'settings' => fn () => $this->service->getSettingsByGroup('attendance'),
        ]);
    }

    /**
     * Persist a new setting.
     */
    public function store(StoreSettingRequest $request): RedirectResponse
    {
        $this->authorize('create-settings');

        $this->service->createSetting($request->validated());

        return redirect()->route('settings.index')
            ->with('success', __('settings.created_successfully'));
    }

    /**
     * Update the supplied setting.
     */
    public function update(UpdateSettingRequest $request, int $setting): RedirectResponse
    {
        $this->authorize('edit-settings');

        $row = $this->service->getSettingById($setting);
        if (! $row) {
            abort(404);
        }

        $this->service->updateSetting($row, $request->validated());

        return redirect()->route('settings.index')
            ->with('success', __('settings.updated_successfully'));
    }

    /**
     * Soft delete the supplied setting.
     */
    public function destroy(int $setting): RedirectResponse
    {
        $this->authorize('delete-settings');

        $row = $this->service->getSettingById($setting);
        if (! $row) {
            abort(404);
        }

        $this->service->deleteSetting($row);

        return redirect()->route('settings.index')
            ->with('success', __('settings.deleted_successfully'));
    }

    /**
     * Bulk update a group of settings in a single request.
     */
    public function bulkUpdate(BulkUpdateSettingsRequest $request): RedirectResponse
    {
        $this->authorize('edit-settings');

        foreach ($request->validated('settings') as $row) {
            $key = (string) $row['key'];
            $value = $row['value'] ?? null;
            $type = $row['type'] ?? null;

            $existing = $this->service->getSettingByKey($key);
            $attributes = $existing ? $existing->only(['group', 'name_ar', 'name_en', 'description', 'is_public', 'is_encrypted', 'sort_order']) : [];
            if ($type) {
                $attributes['type'] = $type;
            }
            $this->service->setValue($key, $value, $attributes);
        }

        return redirect()->back()
            ->with('success', __('settings.bulk_updated_successfully'));
    }

    /**
     * Flush the cache for the supplied key.
     */
    public function flushCache(Request $request, int $setting): RedirectResponse
    {
        $this->authorize('edit-settings');

        $row = $this->service->getSettingById($setting);
        if (! $row) {
            abort(404);
        }

        $this->service->flushCache($row->key);

        return redirect()->back()
            ->with('success', __('settings.cache_flushed'));
    }
}
