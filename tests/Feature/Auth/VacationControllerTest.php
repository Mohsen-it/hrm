<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\VacationController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for the cross-cutting {@see VacationController}.
 */
class VacationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The dashboard returns 200 for an authorised user.
     */
    public function test_dashboard_returns_200_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('vacations.dashboard'))->assertOk();
    }

    /**
     * Unauthenticated visitors are redirected.
     */
    public function test_dashboard_redirects_unauthenticated_visitors(): void
    {
        $this->seedPermissions();

        $this->get(route('vacations.dashboard'))->assertRedirect();
    }
}
