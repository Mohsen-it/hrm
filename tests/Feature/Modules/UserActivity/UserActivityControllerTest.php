<?php

namespace Tests\Feature\Modules\UserActivity;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Settings\Models\Setting;
use Modules\UserActivity\Http\Middleware\RecordUserActivity;
use Modules\UserActivity\Models\UserActivityLog;
use Modules\UserActivity\Services\UserActivityService;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Feature coverage for the user activity monitoring pages and the
 * RecordUserActivity middleware.
 */
class UserActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The index page renders for the super-admin.
     */
    public function test_index_renders_for_super_admin(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('user-activity.index'))->assertOk();
    }

    /**
     * Users without the permission are rejected.
     */
    public function test_index_rejects_users_without_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('user-activity.index'))->assertForbidden();
    }

    /**
     * Unauthenticated visitors are redirected to the login page.
     */
    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('user-activity.index'))->assertRedirect();
    }

    /**
     * The user detail page renders for the super-admin.
     */
    public function test_show_renders_for_super_admin(): void
    {
        $this->actAsSuperAdmin();

        $employee = User::factory()->create();

        $this->get(route('user-activity.show', $employee->id))->assertOk();
    }

    /**
     * Missing users yield a 404.
     */
    public function test_show_returns_404_for_missing_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('user-activity.show', 999_999))->assertNotFound();
    }

    /**
     * The middleware records every authenticated request.
     */
    public function test_middleware_records_authenticated_requests(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('users.index'))->assertOk();

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => User::SUPER_ADMIN_ID,
            'action' => 'view',
            'entity' => 'users',
        ]);
    }

    /**
     * The monitoring pages themselves are not recorded (no self-noise).
     */
    public function test_middleware_skips_the_monitoring_pages(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('user-activity.index'))->assertOk();

        $this->assertDatabaseCount('user_activity_logs', 0);
    }

    /**
     * Opening a form page is recorded distinctly from submitting it, so a
     * report can tell "opened the add page" apart from an actual create.
     */
    public function test_middleware_records_opening_the_create_page_separately(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('users.create'))->assertOk();

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => User::SUPER_ADMIN_ID,
            'action' => 'open_create',
            'entity' => 'users',
        ]);
    }

    /**
     * The idle gap is persisted through the dedicated endpoint and read
     * back as a typed integer setting.
     */
    public function test_idle_gap_can_be_updated_and_persisted(): void
    {
        $this->actAsSuperAdmin();

        $this->post(route('user-activity.idle-gap'), ['idle_gap_minutes' => 5])
            ->assertRedirect();

        try {
            $this->assertSame(5, Setting::get('useractivity.idle_gap_minutes'));
        } finally {
            // The array cache outlives the per-test DB rollback; drop the
            // cached value so later tests fall back to their own config.
            Setting::forget('useractivity.idle_gap_minutes');
        }
    }

    /**
     * The persisted idle gap drives the active-time aggregation, so the
     * threshold can be changed dynamically from the page.
     */
    public function test_overview_uses_the_persisted_idle_gap(): void
    {
        $employee = User::factory()->create();

        $day = now()->startOfDay();

        // Two actions 3 minutes apart count as 3 minutes only when the
        // idle gap is at least 3 minutes.
        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'view', 'entity' => 'users', 'created_at' => $day->copy()->addHours(9)]);
        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'view', 'entity' => 'users', 'created_at' => $day->copy()->addHours(9)->addMinutes(3)]);

        Setting::set('useractivity.idle_gap_minutes', 5, ['type' => 'integer']);

        try {
            $overview = app(UserActivityService::class)->overview($day->toDateString(), $day->toDateString(), '', 1, 15);

            $row = collect($overview['users']['data'])->firstWhere('id', $employee->id);

            $this->assertNotNull($row);
            $this->assertSame(3, $row['active_minutes']);
        } finally {
            Setting::forget('useractivity.idle_gap_minutes');
        }
    }

    /**
     * The user detail page splits the totals into real operations
     * (create/edit/delete/approve/...) and views, so a report answers
     * "how many operations were actually performed" instead of lumping
     * page opens together with submitted actions.
     */
    public function test_user_detail_splits_real_actions_from_views(): void
    {
        $employee = User::factory()->create();

        $day = now()->startOfDay();

        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'view', 'entity' => 'vacations.requests', 'created_at' => $day->copy()->addHours(9)]);
        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'open_create', 'entity' => 'vacations.requests', 'created_at' => $day->copy()->addHours(9)->addMinutes(5)]);
        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'create', 'entity' => 'vacations.requests', 'created_at' => $day->copy()->addHours(9)->addMinutes(10)]);
        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'approve', 'entity' => 'vacations.requests', 'created_at' => $day->copy()->addHours(9)->addMinutes(15)]);
        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'delete', 'entity' => 'vacations.requests', 'created_at' => $day->copy()->addHours(9)->addMinutes(20)]);

        $service = app(UserActivityService::class);

        $detail = $service->userDetail($employee, $day->toDateString(), $day->toDateString());

        $this->assertSame(5, $detail['kpis']['total_actions']);
        $this->assertSame(3, $detail['kpis']['real_actions']);
        $this->assertSame(2, $detail['kpis']['views']);
    }

    /**
     * Route suffixes resolve to granular actions: form pages are recorded
     * as open_create / open_edit, and decision verbs (cancel, grant-all)
     * keep their own action instead of falling back to generic method
     * based labels.
     */
    public function test_action_mapping_is_granular(): void
    {
        $middleware = new RecordUserActivity(app(UserActivityService::class));

        $resolve = new \ReflectionMethod($middleware, 'resolveAction');
        $resolve->setAccessible(true);

        $request = Request::create('/vacations/my/5/cancel', 'POST');

        $this->assertSame(['cancel', 'vacations.my'], $resolve->invoke($middleware, 'vacations.my.cancel', $request));
        $this->assertSame(['open_create', 'users'], $resolve->invoke($middleware, 'users.create', $request));
        $this->assertSame(['open_edit', 'vacations.my'], $resolve->invoke($middleware, 'vacations.my.edit', $request));
        $this->assertSame(['create', 'vacations.my'], $resolve->invoke($middleware, 'vacations.my.store', $request));
        $this->assertSame(['edit', 'vacations.my'], $resolve->invoke($middleware, 'vacations.my.update', $request));
        $this->assertSame(['delete', 'vacations.my'], $resolve->invoke($middleware, 'vacations.my.destroy', $request));
        $this->assertSame(['view', 'users'], $resolve->invoke($middleware, 'users.index', $request));
        $this->assertSame(['grant', 'vacations.balances'], $resolve->invoke($middleware, 'vacations.balances.grant-all', $request));
    }

    /**
     * A successful sign-in is recorded via the Login event.
     */
    public function test_login_event_records_sign_in(): void
    {
        $this->actAsSuperAdmin();

        event(new Login('web', User::find(User::SUPER_ADMIN_ID), false));

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => User::SUPER_ADMIN_ID,
            'action' => 'login',
            'entity' => 'auth',
        ]);
    }

    /**
     * The overview aggregates actions and active minutes per user.
     */
    public function test_overview_computes_usage_and_active_minutes(): void
    {
        $employee = User::factory()->create();

        $day = now()->startOfDay();

        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'view', 'entity' => 'vacations.requests', 'created_at' => $day->copy()->addHours(9)]);
        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'create', 'entity' => 'vacations.requests', 'created_at' => $day->copy()->addHours(9)->addMinutes(20)]);
        UserActivityLog::create(['user_id' => $employee->id, 'action' => 'view', 'entity' => 'vacations.requests', 'created_at' => $day->copy()->addHours(9)->addMinutes(45)]);

        // The three actions sit 20 / 25 minutes apart, which splits under the
        // default two-minute idle gap; widen the gap so this test exercises
        // the session-merging path of the overview aggregation.
        Setting::forget('useractivity.idle_gap_minutes');
        config(['useractivity.idle_gap_minutes' => 30]);

        try {
            $service = app(UserActivityService::class);

            $overview = $service->overview(
                $day->toDateString(),
                $day->copy()->addDay()->toDateString(),
                '',
                1,
                15
            );

            $row = collect($overview['users']['data'])->firstWhere('id', $employee->id);

            $this->assertNotNull($row);
            $this->assertSame(3, $row['actions']);
            $this->assertSame(45, $row['active_minutes']);
            $this->assertSame(1, $row['active_days']);
            $this->assertSame(3, $overview['totals']['total_actions']);
            $this->assertSame(45, $overview['totals']['total_active_minutes']);
        } finally {
            config(['useractivity.idle_gap_minutes' => 2]);
        }
    }

    /**
     * Rows recorded late in the local day are included in the range even
     * when the app timezone is ahead of UTC.
     *
     * Logs are persisted with `now()` formatted in the app timezone (naive
     * local datetimes, e.g. `22:07` in Asia/Riyadh). The range must
     * therefore stay in app-local time — previously the bounds were shifted
     * to UTC (end-of-day = `20:59:59Z`), which silently dropped every row
     * recorded after UTC midnight of the `to` day and emptied the reports.
     */
    public function test_overview_includes_late_local_day_rows_in_non_utc_timezone(): void
    {
        $employee = User::factory()->create();

        $originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'Asia/Riyadh']);
        date_default_timezone_set('Asia/Riyadh');

        try {
            $day = now()->startOfDay(); // Asia/Riyadh midnight

            UserActivityLog::create([
                'user_id' => $employee->id,
                'action' => 'create',
                'entity' => 'vacations.requests',
                'created_at' => $day->copy()->addHours(22)->addMinutes(7),
            ]);

            $service = app(UserActivityService::class);

            $overview = $service->overview($day->toDateString(), $day->toDateString(), '', 1, 15);

            $row = collect($overview['users']['data'])->firstWhere('id', $employee->id);

            $this->assertNotNull($row);
            $this->assertSame(1, $row['actions']);
            $this->assertSame(1, $overview['totals']['total_actions']);
        } finally {
            config(['app.timezone' => $originalTimezone]);
            date_default_timezone_set($originalTimezone);
        }
    }
}
