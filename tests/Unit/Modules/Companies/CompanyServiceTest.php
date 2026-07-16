<?php

namespace Tests\Unit\Modules\Companies;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Companies\Models\Company;
use Modules\Companies\Services\CompanyService;
use Tests\TestCase;

/**
 * Unit coverage for {@see CompanyService}.
 *
 * The tests cover the happy CRUD path plus the rules that are unique to
 * the service: the `company_code`/`email` uniqueness checks, the
 * `is_default` exclusivity invariant and validation failures.
 */
class CompanyServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The service under test.
     */
    private CompanyService $service;

    /**
     * Initialise a fresh service for every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CompanyService::class);
    }

    /**
     * Create a company with a unique code and return it.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeCompany(array $overrides = []): Company
    {
        return Company::factory()->create($overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_code' => 'TST'.fake()->unique()->numberBetween(100, 999),
            'company_name' => 'Test Company '.fake()->word(),
            'email' => fake()->unique()->companyEmail(),
            'status' => 1,
        ], $overrides);
    }

    /**
     * Service can persist a new company with the bare minimum data.
     */
    public function test_creates_a_company_with_minimum_data(): void
    {
        $payload = $this->validPayload();

        $company = $this->service->createCompany($payload);

        $this->assertInstanceOf(Company::class, $company);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'company_code' => $payload['company_code'],
            'company_name' => $payload['company_name'],
            'status' => 1,
        ]);
    }

    /**
     * Service rejects a payload with a duplicate `company_code`.
     */
    public function test_duplicate_company_code_is_rejected(): void
    {
        $this->makeCompany(['company_code' => 'DUP001']);

        $this->expectException(ValidationException::class);

        $this->service->createCompany($this->validPayload(['company_code' => 'DUP001']));
    }

    /**
     * Service rejects a payload with a duplicate `email`.
     */
    public function test_duplicate_email_is_rejected(): void
    {
        $email = 'dupe@example.com';
        $this->makeCompany(['email' => $email]);

        $this->expectException(ValidationException::class);

        $this->service->createCompany($this->validPayload(['email' => $email]));
    }

    /**
     * Service allows updating a company to keep the same `company_code`.
     */
    public function test_update_keeps_company_code_unchanged(): void
    {
        $company = $this->makeCompany(['company_code' => 'KEEP1', 'company_name' => 'Original']);

        $updated = $this->service->updateCompany($company, [
            'company_code' => 'KEEP1',
            'company_name' => 'Updated',
            'status' => 1,
        ]);

        $this->assertSame('Updated', $updated->company_name);
        $this->assertSame('KEEP1', $updated->company_code);
    }

    /**
     * Service rejects a status that is not in {0, 1}.
     */
    public function test_invalid_status_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createCompany($this->validPayload(['status' => 9]));
    }

    /**
     * Marking a company as default removes the flag from any other company.
     */
    public function test_only_one_company_can_be_default(): void
    {
        $first = $this->makeCompany(['is_default' => true]);
        $second = $this->makeCompany(['is_default' => true]);

        $this->service->createCompany($this->validPayload(['is_default' => true]));

        $this->assertDatabaseHas('companies', ['id' => $first->id, 'is_default' => false]);
        $this->assertDatabaseHas('companies', ['id' => $second->id, 'is_default' => false]);
        $this->assertEquals(1, Company::where('is_default', true)->count());
    }

    /**
     * `getAllCompanies` paginates and applies the supplied filters.
     */
    public function test_get_all_paginates_with_filters(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeCompany(['status' => 1]);
        }
        $this->makeCompany(['status' => 0]);

        $page = $this->service->getAllCompanies(['status' => 1], perPage: 10);

        $this->assertSame(10, $page->perPage());
        $this->assertSame(25, $page->total());
    }

    /**
     * `getActiveCompanies` returns only the companies flagged as active.
     */
    public function test_get_active_companies_filters_inactive(): void
    {
        $this->makeCompany(['status' => 1]);
        $this->makeCompany(['status' => 1]);
        $this->makeCompany(['status' => 0]);

        $this->assertCount(2, $this->service->getActiveCompanies());
    }

    /**
     * Service soft-deletes a company.
     */
    public function test_delete_company_soft_deletes(): void
    {
        $company = $this->makeCompany();

        $this->assertTrue($this->service->deleteCompany($company));
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }
}
