<?php

namespace Tests\Feature\Modules\Vacations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Users\Models\User;
use Modules\Vacations\Http\Controllers\VacationBalancesController;
use Modules\Vacations\Jobs\RecalculateVacationBalances;
use Modules\Vacations\Models\UserVacationBalance;
use Modules\Vacations\Models\VacationType;
use Modules\Vacations\Services\VacationBalanceService;
use Tests\TestCase;

/**
 * Feature coverage for the HR balances matrix {@see VacationBalancesController}.
 */
class BalancesManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The balances matrix page renders.
     */
    public function test_index_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('vacations.balances.index'))->assertOk();
    }

    /**
     * Setting an absolute value creates the ledger row with the exact days.
     */
    public function test_set_creates_balance_with_absolute_days(): void
    {
        $this->withoutMiddleware();
        $this->actAsSuperAdmin();

        $employee = User::factory()->create();
        $type = $this->makeType('annual');

        $this->post(route('vacations.balances.set'), [
            'user_id' => $employee->id,
            'vacation_type_id' => $type->id,
            'year' => now()->year,
            'days' => 30,
        ])->assertRedirect();

        $balance = UserVacationBalance::query()
            ->forUser($employee->id)
            ->forType($type->id)
            ->forYear((int) now()->year)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame(30, (int) $balance->days_entitled);
        $this->assertSame(30, $balance->daysRemaining());
    }

    /**
     * Setting an absolute value overrides an existing entitlement and every
     * change lands in the immutable audit trail.
     */
    public function test_set_overrides_existing_entitlement_with_audit(): void
    {
        $this->withoutMiddleware();
        $this->actAsSuperAdmin();

        $employee = User::factory()->create();
        $type = $this->makeType('sick');
        $year = (int) now()->year;

        app(VacationBalanceService::class)->grant($employee->id, $type, $year, 10);

        $this->post(route('vacations.balances.set'), [
            'user_id' => $employee->id,
            'vacation_type_id' => $type->id,
            'year' => $year,
            'days' => 25,
        ])->assertRedirect();

        $balance = UserVacationBalance::query()
            ->forUser($employee->id)
            ->forType($type->id)
            ->forYear($year)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame(25, (int) $balance->days_entitled);
        $this->assertSame(25, $balance->daysRemaining());
        $this->assertSame(2, $balance->transactions()->count()); // grant + set
    }

    /**
     * The bulk grant endpoint dispatches the recalculation job.
     */
    public function test_grant_all_dispatches_job(): void
    {
        $this->withoutMiddleware();
        Queue::fake();
        $this->actAsSuperAdmin();

        $this->post(route('vacations.balances.grant-all'), [
            'year' => now()->year,
        ])->assertRedirect();

        Queue::assertPushed(RecalculateVacationBalances::class, fn ($job) => $job->year === (int) now()->year);
    }

    /**
     * Users without the view permission cannot open the matrix page.
     */
    public function test_index_denied_without_permission(): void
    {
        $this->seedPermissions();

        $this->actingAs(User::factory()->create())
            ->get(route('vacations.balances.index'))
            ->assertForbidden();
    }

    /**
     * Setting an entitlement below the already consumed days is rejected.
     */
    public function test_set_rejects_entitlement_below_consumed_days(): void
    {
        $this->withoutMiddleware();
        $this->actAsSuperAdmin();

        $employee = User::factory()->create();
        $type = $this->makeType('maternity');
        $year = (int) now()->year;

        app(VacationBalanceService::class)->grant($employee->id, $type, $year, 10);

        UserVacationBalance::query()
            ->forUser($employee->id)
            ->forType($type->id)
            ->forYear($year)
            ->update(['days_used' => 8]);

        $this->post(route('vacations.balances.set'), [
            'user_id' => $employee->id,
            'vacation_type_id' => $type->id,
            'year' => $year,
            'days' => 5,
        ])->assertRedirect();

        // The guard rejected the change — the entitlement is untouched.
        $this->assertSame(10, (int) UserVacationBalance::query()
            ->forUser($employee->id)
            ->forType($type->id)
            ->forYear($year)
            ->value('days_entitled'));
    }

    /**
     * The matrix exports a valid Excel file.
     */
    public function test_export_returns_excel(): void
    {
        $this->actAsSuperAdmin();

        $response = $this->get(route('vacations.balances.export', ['year' => now()->year]));

        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /**
     * Build a minimal active vacation type.
     */
    protected function makeType(string $code): VacationType
    {
        return VacationType::create([
            'code' => $code,
            'name_ar' => "إجازة {$code}",
            'name_en' => ucfirst($code),
            'default_days_per_year' => 21,
            'is_active' => true,
            'deducts_from_balance' => true,
        ]);
    }
}
