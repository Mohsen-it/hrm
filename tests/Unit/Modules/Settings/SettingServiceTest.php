<?php

namespace Tests\Unit\Modules\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Settings\Models\Setting;
use Modules\Settings\Services\SettingService;
use Tests\TestCase;

/**
 * Unit coverage for {@see SettingService}.
 */
class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The service under test.
     */
    private SettingService $service;

    /**
     * Initialise a fresh service for every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SettingService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'app.'.fake()->unique()->word(),
            'value' => 'sample',
            'type' => 'string',
            'group' => 'general',
            'name_ar' => 'إعداد',
            'name_en' => 'Setting',
        ], $overrides);
    }

    /**
     * Service can persist a new setting row.
     */
    public function test_creates_a_setting(): void
    {
        $setting = $this->service->createSetting($this->validPayload());

        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertDatabaseHas('settings', ['key' => $setting->key]);
    }

    /**
     * Service rejects an unsupported `type`.
     */
    public function test_invalid_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createSetting($this->validPayload(['type' => 'xml']));
    }

    /**
     * Service rejects an unsupported `group`.
     */
    public function test_invalid_group_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createSetting($this->validPayload(['group' => 'unsupported']));
    }

    /**
     * Service rejects a `key` with invalid characters.
     */
    public function test_invalid_key_pattern_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createSetting($this->validPayload(['key' => 'has spaces']));
    }

    /**
     * Service rejects a duplicate `key`.
     */
    public function test_duplicate_key_is_rejected(): void
    {
        $this->service->createSetting($this->validPayload(['key' => 'app.dup']));

        $this->expectException(ValidationException::class);

        $this->service->createSetting($this->validPayload(['key' => 'app.dup']));
    }

    /**
     * Service encodes boolean and JSON values according to the type.
     */
    public function test_value_is_encoded_against_type(): void
    {
        $bool = $this->service->createSetting($this->validPayload([
            'key' => 'feature.enabled',
            'value' => true,
            'type' => 'bool',
        ]));

        $this->assertSame('1', $bool->value);

        $json = $this->service->createSetting($this->validPayload([
            'key' => 'feature.list',
            'value' => ['a', 'b'],
            'type' => 'json',
        ]));

        $this->assertSame('["a","b"]', $json->value);
    }

    /**
     * `getValue` reads through the model cache helper.
     */
    public function test_get_value_reads_through_static_helper(): void
    {
        $this->service->createSetting($this->validPayload([
            'key' => 'app.title',
            'value' => 'HRM',
        ]));

        $this->assertSame('HRM', $this->service->getValue('app.title'));
    }

    /**
     * `setValue` is a thin wrapper that creates/updates a row.
     */
    public function test_set_value_creates_or_updates(): void
    {
        $first = $this->service->setValue('app.locale', 'ar', ['type' => 'string']);
        $second = $this->service->setValue('app.locale', 'en', ['type' => 'string']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('en', $second->value);
    }

    /**
     * `getGroups` always returns the canonical group list.
     */
    public function test_get_groups_returns_canonical_list(): void
    {
        $groups = $this->service->getGroups();

        $this->assertContains('general', $groups);
        $this->assertContains('attendance', $groups);
        $this->assertContains('branding', $groups);
        $this->assertContains('security', $groups);
        $this->assertContains('integrations', $groups);
    }

    /**
     * `updateSetting` keeps the same `key` when the caller echoes it.
     */
    public function test_update_keeps_key_when_repeated(): void
    {
        $setting = $this->service->createSetting($this->validPayload());

        $updated = $this->service->updateSetting($setting, [
            'key' => $setting->key,
            'value' => 'updated value',
        ]);

        $this->assertSame($setting->key, $updated->key);
        $this->assertSame('updated value', $updated->value);
    }

    /**
     * `deleteSetting` removes the row from the settings table.
     */
    public function test_delete_setting_removes_row(): void
    {
        $setting = $this->service->createSetting($this->validPayload());

        $this->assertTrue($this->service->deleteSetting($setting));
        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);
    }

    /**
     * Static `Setting::get()` returns the casted typed value.
     */
    public function test_static_get_returns_typed_value(): void
    {
        $this->service->createSetting($this->validPayload([
            'key' => 'feature.enabled',
            'value' => true,
            'type' => 'bool',
        ]));

        $this->assertTrue(Setting::get('feature.enabled', false));
    }
}
