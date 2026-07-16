<?php

namespace Tests\Feature\Modules\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Settings\Http\Controllers\SettingsController;
use Modules\Settings\Models\Setting;
use Tests\TestCase;

/**
 * Feature coverage for {@see SettingsController}.
 */
class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'app.'.fake()->unique()->word(),
            'value' => 'value',
            'type' => 'string',
            'group' => 'general',
            'name_ar' => 'إعداد',
            'name_en' => 'Setting',
        ], $overrides);
    }

    /**
     * Index returns 200 for an authorized user.
     */
    public function test_index_renders_for_super_admin(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('settings.index'))->assertOk();
    }

    /**
     * The general settings sub-page renders successfully.
     */
    public function test_general_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('settings.general'))->assertOk();
    }

    /**
     * The attendance settings sub-page renders successfully.
     */
    public function test_attendance_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('settings.attendance'))->assertOk();
    }

    /**
     * `store` persists a new setting and redirects.
     */
    public function test_store_creates_setting(): void
    {
        $this->actAsSuperAdmin();

        $this->post(route('settings.store'), $this->validPayload())
            ->assertRedirect(route('settings.index'));

        $this->assertDatabaseCount('settings', 1);
    }

    /**
     * `store` rejects a duplicate `key` with a validation error.
     */
    public function test_store_rejects_duplicate_key(): void
    {
        $this->actAsSuperAdmin();
        Setting::create($this->validPayload(['key' => 'app.dup']));

        $this->post(route('settings.store'), $this->validPayload(['key' => 'app.dup']))
            ->assertSessionHasErrors('key');
    }

    /**
     * `store` rejects an unsupported type.
     */
    public function test_store_rejects_unsupported_type(): void
    {
        $this->actAsSuperAdmin();

        $this->post(route('settings.store'), $this->validPayload(['type' => 'xml']))
            ->assertSessionHasErrors('type');
    }

    /**
     * `update` modifies an existing setting.
     */
    public function test_update_persists_changes(): void
    {
        $this->actAsSuperAdmin();
        $setting = Setting::create($this->validPayload());

        $this->put(route('settings.update', $setting->id), [
            'key' => $setting->key,
            'value' => 'updated',
            'type' => 'string',
            'group' => 'general',
        ])->assertRedirect(route('settings.index'));

        $this->assertDatabaseHas('settings', [
            'id' => $setting->id,
            'value' => 'updated',
        ]);
    }

    /**
     * `destroy` removes the setting from the database.
     */
    public function test_destroy_deletes_setting(): void
    {
        $this->actAsSuperAdmin();
        $setting = Setting::create($this->validPayload());

        $this->delete(route('settings.destroy', $setting->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);
    }

    /**
     * Bulk update processes a list of settings in one shot.
     */
    public function test_bulk_update_persists_settings(): void
    {
        $this->actAsSuperAdmin();
        $existing = Setting::create($this->validPayload(['key' => 'app.bulk1']));
        Setting::create($this->validPayload(['key' => 'app.bulk2']));

        $this->post(route('settings.bulk-update'), [
            'settings' => [
                ['key' => $existing->key, 'value' => 'one', 'type' => 'string'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'id' => $existing->id,
            'value' => 'one',
        ]);
    }

    /**
     * Unauthenticated visitors are redirected to the login page.
     */
    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('settings.index'))->assertRedirect();
    }
}
