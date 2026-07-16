<?php

namespace Tests\Feature\Modules\Companies;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Http\Controllers\CompaniesController;
use Modules\Companies\Models\Company;
use Tests\TestCase;

/**
 * Feature coverage for {@see CompaniesController}.
 *
 * Every test seeds the permission catalog and authenticates as the
 * super-admin so Spatie's Gate resolves the `view-companies` /
 * `create-companies` / `edit-companies` / `delete-companies` abilities
 * without any extra setup.
 */
class CompaniesControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Hit the `companies.index` route and assert the response is 200
     * for a super-admin.
     */
    public function test_index_returns_successful_response_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $response = $this->get(route('companies.index'));

        $response->assertOk();
    }

    /**
     * The create form is rendered for users with the create permission.
     */
    public function test_create_renders_form_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('companies.create'))->assertOk();
    }

    /**
     * `store` persists the supplied company and redirects to the index.
     */
    public function test_store_persists_company_and_redirects(): void
    {
        $this->actAsSuperAdmin();

        $payload = [
            'company_code' => 'NEW001',
            'company_name' => 'New Company',
            'email' => 'new@company.com',
            'status' => 1,
        ];

        $response = $this->post(route('companies.store'), $payload);

        $response->assertRedirect(route('companies.index'));
        $this->assertDatabaseHas('companies', ['company_code' => 'NEW001']);
    }

    /**
     * `store` rejects a duplicate `company_code` with a validation error.
     */
    public function test_store_rejects_duplicate_company_code(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['company_code' => 'DUP1']);

        $this->post(route('companies.store'), [
            'company_code' => 'DUP1',
            'company_name' => 'Dup',
            'status' => 1,
        ])->assertSessionHasErrors('company_code');
    }

    /**
     * `show` displays the supplied company.
     */
    public function test_show_renders_supplied_company(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create();

        $this->get(route('companies.show', $company->id))->assertOk();
    }

    /**
     * `show` returns 404 for a missing company.
     */
    public function test_show_returns_404_for_missing_company(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('companies.show', 9999))->assertNotFound();
    }

    /**
     * `edit` renders the edit form for an existing company.
     */
    public function test_edit_renders_form(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create();

        $this->get(route('companies.edit', $company->id))->assertOk();
    }

    /**
     * `update` modifies the supplied company and redirects back.
     */
    public function test_update_persists_changes(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create(['company_name' => 'Original']);

        $this->put(route('companies.update', $company->id), [
            'company_code' => $company->company_code,
            'company_name' => 'Updated Name',
            'status' => 1,
        ])->assertRedirect(route('companies.index'));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'company_name' => 'Updated Name',
        ]);
    }

    /**
     * `destroy` soft-deletes the company and redirects back.
     */
    public function test_destroy_soft_deletes_company(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create();

        $this->delete(route('companies.destroy', $company->id))
            ->assertRedirect(route('companies.index'));

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    /**
     * Unauthenticated visitors are redirected to the login page.
     */
    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('companies.index'))->assertRedirect();
    }
}
