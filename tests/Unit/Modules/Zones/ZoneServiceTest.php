<?php

namespace Tests\Unit\Modules\Zones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Modules\Zones\Enums\ZoneType;
use Modules\Zones\Models\Zone;
use Modules\Zones\Services\ZoneService;
use Tests\TestCase;

/**
 * Unit coverage for {@see ZoneService}.
 */
class ZoneServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The service under test.
     */
    private ZoneService $service;

    /**
     * Initialise a fresh service for every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ZoneService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(?Company $company = null, array $overrides = []): array
    {
        if (is_array($company)) {
            $overrides = $company;
            $company = null;
        }

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
     * Service can persist a new zone.
     */
    public function test_creates_a_zone(): void
    {
        $zone = $this->service->createZone($this->validPayload());

        $this->assertInstanceOf(Zone::class, $zone);
        $this->assertSame(ZoneType::Geographic, $zone->zone_type);
        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
    }

    /**
     * Duplicate `code` is rejected.
     */
    public function test_duplicate_zone_code_is_rejected(): void
    {
        $this->service->createZone($this->validPayload(null, ['code' => 'DUPE']));

        $this->expectException(ValidationException::class);

        $this->service->createZone($this->validPayload(null, ['code' => 'DUPE']));
    }

    /**
     * Invalid `zone_type` is rejected.
     */
    public function test_invalid_zone_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createZone($this->validPayload(null, ['zone_type' => 'imaginary']));
    }

    /**
     * `getAllZones` paginates and applies filters.
     */
    public function test_get_all_paginates_with_filters(): void
    {
        $company = Company::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            $this->service->createZone($this->validPayload($company));
        }
        $this->service->createZone($this->validPayload($company, ['is_active' => false]));

        $page = $this->service->getAllZones([
            'company_id' => $company->id,
            'is_active' => 1,
        ], perPage: 5);

        $this->assertSame(5, $page->perPage());
        $this->assertSame(12, $page->total());
    }

    /**
     * `getActiveZones` returns only active rows.
     */
    public function test_get_active_zones_filters_inactive(): void
    {
        $company = Company::factory()->create();
        $this->service->createZone($this->validPayload($company));
        $this->service->createZone($this->validPayload($company, ['is_active' => false]));

        $this->assertCount(1, $this->service->getActiveZones());
    }

    /**
     * `getZonesByCompany` returns only zones tied to the supplied company.
     */
    public function test_get_zones_by_company(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();

        $this->service->createZone($this->validPayload($a));
        $this->service->createZone($this->validPayload($a));
        $this->service->createZone($this->validPayload($b));

        $this->assertCount(2, $this->service->getZonesByCompany($a->id));
    }

    /**
     * Service updates a zone row.
     */
    public function test_update_zone(): void
    {
        $zone = $this->service->createZone($this->validPayload());

        $updated = $this->service->updateZone($zone, [
            'company_id' => $zone->company_id,
            'code' => $zone->code,
            'name_ar' => 'منطقة معدلة',
            'name_en' => 'Updated Zone',
            'zone_type' => ZoneType::Security->value,
            'is_active' => false,
        ]);

        $this->assertSame('منطقة معدلة', $updated->name_ar);
        $this->assertFalse($updated->is_active);
        $this->assertSame(ZoneType::Security, $updated->zone_type);
    }

    /**
     * Service soft-deletes a zone.
     */
    public function test_delete_zone_soft_deletes(): void
    {
        $zone = $this->service->createZone($this->validPayload());

        $this->assertTrue($this->service->deleteZone($zone));
        $this->assertSoftDeleted('zones', ['id' => $zone->id]);
    }

    /**
     * Service can attach a branch to a zone via {@see ZoneBranchService}.
     */
    public function test_attach_branch_creates_pivot_row(): void
    {
        $zone = $this->service->createZone($this->validPayload());
        $branch = Branch::factory()->create();

        $branchService = app(\Modules\Zones\Services\ZoneBranchService::class);
        $branchService->attachBranch($zone->id, $branch->id, isPrimary: true, priority: 10);

        $this->assertDatabaseHas('zone_branches', [
            'zone_id' => $zone->id,
            'branch_id' => $branch->id,
            'is_primary' => 1,
            'priority' => 10,
        ]);
    }

    /**
     * ZoneType enum exposes every value used by the migration enum.
     */
    public function test_zone_type_enum_covers_migration_values(): void
    {
        $values = array_map(fn (ZoneType $t) => $t->value, ZoneType::cases());

        $this->assertContains('geographic', $values);
        $this->assertContains('operational', $values);
        $this->assertContains('security', $values);
        $this->assertContains('sales', $values);
        $this->assertContains('logistics', $values);
    }
}
