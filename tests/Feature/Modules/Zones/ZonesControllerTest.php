<?php

namespace Tests\Feature\Modules\Zones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Models\Company;
use Modules\Zones\Enums\ZoneType;
use Modules\Zones\Http\Controllers\ZonesController;
use Modules\Zones\Models\Zone;
use Tests\TestCase;

/**
 * Feature coverage for {@see ZonesController}.
 */
class ZonesControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(?Company $company = null, array $overrides = []): array
    {
        $company ??= Company::factory()->create();

        return array_merge([
            'company_id' => $company->id,
            'code' => 'Z'.fake()->unique()->numberBetween(100, 999),
            'name_ar' => 'منطقة '.fake()->word(),
            'name_en' => fake()->word().' Zone',
            'zone_type' => ZoneType::Geographic->value,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * Index returns 200 for an authorized user.
     */
    public function test_index_renders_for_super_admin(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('zones.index'))->assertOk();
    }

    /**
     * The zones module uses Inertia-only forms (no create route),
     * so we hit the index page and assert that the controller renders
     * the listing successfully.
     */
    public function test_index_lists_zones(): void
    {
        $this->actAsSuperAdmin();
        Zone::factory()->create();

        $this->get(route('zones.index'))->assertOk();
    }

    /**
     * `store` persists a new zone and redirects to the show page.
     */
    public function test_store_creates_zone(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create();
        $payload = $this->validPayload($company);

        $response = $this->post(route('zones.store'), $payload);

        $zone = Zone::where('code', $payload['code'])->first();
        $response->assertRedirect(route('zones.show', $zone->id));

        $this->assertDatabaseHas('zones', [
            'company_id' => $company->id,
            'code' => $payload['code'],
        ]);
    }

    /**
     * `store` rejects a duplicate `code`.
     */
    public function test_store_rejects_duplicate_code(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create();
        Zone::factory()->create(['company_id' => $company->id, 'code' => 'DUP1']);

        $this->post(route('zones.store'), $this->validPayload($company, ['code' => 'DUP1']))
            ->assertSessionHasErrors('code');
    }

    /**
     * `store` rejects an invalid `zone_type`.
     */
    public function test_store_rejects_invalid_zone_type(): void
    {
        $this->actAsSuperAdmin();

        $this->post(route('zones.store'), $this->validPayload(null, ['zone_type' => 'invalid']))
            ->assertSessionHasErrors('zone_type');
    }

    /**
     * `show` renders an existing zone.
     */
    public function test_show_renders_zone(): void
    {
        $this->actAsSuperAdmin();
        $zone = Zone::factory()->create();

        $this->get(route('zones.show', $zone->id))->assertOk();
    }

    /**
     * `show` returns 404 for a missing zone.
     */
    public function test_show_returns_404(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('zones.show', 9999))->assertNotFound();
    }

    /**
     * `update` persists the changes to an existing zone.
     */
    public function test_update_persists_changes(): void
    {
        $this->actAsSuperAdmin();
        $zone = Zone::factory()->create();

        $this->put(route('zones.update', $zone->id), [
            'company_id' => $zone->company_id,
            'code' => $zone->code,
            'name_ar' => 'منطقة معدلة',
            'name_en' => 'Updated',
            'zone_type' => ZoneType::Security->value,
            'is_active' => false,
        ])->assertRedirect(route('zones.show', $zone->id));

        $this->assertDatabaseHas('zones', [
            'id' => $zone->id,
            'name_ar' => 'منطقة معدلة',
            'is_active' => 0,
        ]);
    }

    /**
     * `destroy` soft-deletes the zone.
     */
    public function test_destroy_soft_deletes(): void
    {
        $this->actAsSuperAdmin();
        $zone = Zone::factory()->create();

        $this->delete(route('zones.destroy', $zone->id))
            ->assertRedirect(route('zones.index'));

        $this->assertSoftDeleted('zones', ['id' => $zone->id]);
    }

    /**
     * Unauthenticated visitors are redirected to the login page.
     */
    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('zones.index'))->assertRedirect();
    }
}
