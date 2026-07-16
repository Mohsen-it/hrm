<?php

namespace Tests\Feature\Modules\Branches;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branches\Http\Controllers\BranchesController;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Tests\TestCase;

/**
 * Feature coverage for {@see BranchesController}.
 */
class BranchesControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Index returns 200 for an authorized user.
     */
    public function test_index_renders_for_super_admin(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('branches.index'))->assertOk();
    }

    /**
     * Create form is rendered for users with the create permission.
     */
    public function test_create_form_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('branches.create'))->assertOk();
    }

    /**
     * `store` persists a new branch and redirects to the index.
     */
    public function test_store_creates_branch(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create();

        $this->post(route('branches.store'), [
            'company_id' => $company->id,
            'branch_code' => 'B1',
            'branch_name' => 'Branch 1',
            'status' => 1,
        ])->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', [
            'company_id' => $company->id,
            'branch_code' => 'B1',
        ]);
    }

    /**
     * `store` rejects duplicate `branch_code` within the same company.
     */
    public function test_store_rejects_duplicate_branch_code(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create();
        Branch::factory()->create(['company_id' => $company->id, 'branch_code' => 'DUP']);

        $this->post(route('branches.store'), [
            'company_id' => $company->id,
            'branch_code' => 'DUP',
            'branch_name' => 'Duplicate',
            'status' => 1,
        ])->assertSessionHasErrors('branch_code');
    }

    /**
     * `show` renders an existing branch.
     */
    public function test_show_renders_branch(): void
    {
        $this->actAsSuperAdmin();
        $branch = Branch::factory()->create();

        $this->get(route('branches.show', $branch->id))->assertOk();
    }

    /**
     * `show` returns 404 for a missing branch.
     */
    public function test_show_returns_404(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('branches.show', 99999))->assertNotFound();
    }

    /**
     * `update` modifies an existing branch.
     */
    public function test_update_persists_changes(): void
    {
        $this->actAsSuperAdmin();
        $branch = Branch::factory()->create(['branch_name' => 'Old']);

        $this->put(route('branches.update', $branch->id), [
            'company_id' => $branch->company_id,
            'branch_code' => $branch->branch_code,
            'branch_name' => 'New Name',
            'status' => 1,
        ])->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'branch_name' => 'New Name',
        ]);
    }

    /**
     * `destroy` soft-deletes the branch.
     */
    public function test_destroy_soft_deletes(): void
    {
        $this->actAsSuperAdmin();
        $branch = Branch::factory()->create();

        $this->delete(route('branches.destroy', $branch->id))
            ->assertRedirect(route('branches.index'));

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
    }

    /**
     * Unauthenticated visitors cannot hit any branch route.
     */
    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('branches.index'))->assertRedirect();
    }
}
