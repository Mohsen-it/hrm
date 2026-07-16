<?php

namespace Tests\Unit\Modules\Branches;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Branches\Models\Branch;
use Modules\Branches\Services\BranchService;
use Modules\Companies\Models\Company;
use Tests\TestCase;

/**
 * Unit coverage for {@see BranchService}.
 */
class BranchServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The service under test.
     */
    private BranchService $service;

    /**
     * Initialise a fresh service for every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BranchService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Company $company, array $overrides = []): array
    {
        return array_merge([
            'company_id' => $company->id,
            'branch_code' => 'B'.fake()->unique()->numberBetween(100, 999),
            'branch_name' => 'Branch '.fake()->word(),
            'status' => 1,
        ], $overrides);
    }

    /**
     * Service creates a branch tied to the supplied company.
     */
    public function test_creates_a_branch(): void
    {
        $company = Company::factory()->create();
        $payload = $this->validPayload($company);

        $branch = $this->service->createBranch($payload);

        $this->assertInstanceOf(Branch::class, $branch);
        $this->assertSame($company->id, $branch->company_id);
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'branch_code' => $payload['branch_code'],
        ]);
    }

    /**
     * Duplicate `branch_code` inside the same company is rejected.
     */
    public function test_duplicate_branch_code_per_company_is_rejected(): void
    {
        $company = Company::factory()->create();
        $this->service->createBranch($this->validPayload($company, ['branch_code' => 'SAME01']));

        $this->expectException(ValidationException::class);

        $this->service->createBranch($this->validPayload($company, ['branch_code' => 'SAME01']));
    }

    /**
     * Same branch code is allowed across two different companies.
     */
    public function test_branch_code_can_repeat_across_companies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $first = $this->service->createBranch($this->validPayload($companyA, ['branch_code' => 'CROSS']));
        $second = $this->service->createBranch($this->validPayload($companyB, ['branch_code' => 'CROSS']));

        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('branches', 2);
    }

    /**
     * Update flow keeps the branch row consistent.
     */
    public function test_update_branch(): void
    {
        $company = Company::factory()->create();
        $branch = $this->service->createBranch($this->validPayload($company));

        $updated = $this->service->updateBranch($branch, [
            'company_id' => $company->id,
            'branch_code' => $branch->branch_code,
            'branch_name' => 'Renamed Branch',
            'status' => 0,
        ]);

        $this->assertSame('Renamed Branch', $updated->branch_name);
        $this->assertSame(0, (int) $updated->status);
    }

    /**
     * `getAllBranches` paginates and accepts filters.
     */
    public function test_get_all_paginates(): void
    {
        $company = Company::factory()->create();
        for ($i = 0; $i < 15; $i++) {
            $this->service->createBranch($this->validPayload($company));
        }

        $page = $this->service->getAllBranches(['company_id' => $company->id], perPage: 5);

        $this->assertSame(5, $page->perPage());
        $this->assertSame(15, $page->total());
    }

    /**
     * Soft delete works on branches.
     */
    public function test_delete_branch_soft_deletes(): void
    {
        $company = Company::factory()->create();
        $branch = $this->service->createBranch($this->validPayload($company));

        $this->assertTrue($this->service->deleteBranch($branch));
        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
    }

    /**
     * Service rejects a payload that is missing the `company_id`.
     */
    public function test_missing_company_id_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createBranch([
            'branch_code' => 'NOID',
            'branch_name' => 'No Company',
            'status' => 1,
        ]);
    }
}
