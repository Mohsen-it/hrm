<?php

namespace Tests;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Modules\Users\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base TestCase for the HRM project.
 *
 * Each test class that extends this case runs against an in-memory SQLite
 * database that is migrated once per class. The PermissionSeeder is run
 * during setUp so permission-aware routes work out of the box; the
 * UserSeeder is invoked lazily via {@see self::seedSuperAdmin()} when a
 * test needs the canonical super-admin user (id = 10000).
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Bootstrap the test environment.
     *
     * Clears the Spatie permission cache so freshly seeded roles are
     * visible to the first request the test class makes.
     */
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create the application instance for testing.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Run the PermissionSeeder against the in-memory database.
     */
    protected function seedPermissions(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
    }

    /**
     * Create the canonical super-admin user (id = 10000) and return it.
     *
     * The method runs {@see PermissionSeeder} first so the role picks
     * up the full permission catalogue; otherwise `syncAllPermissionsTo`
     * in {@see UserSeeder} would have nothing to attach.
     */
    protected function seedSuperAdmin(): User
    {
        $this->seedPermissions();
        Artisan::call('db:seed', ['--class' => UserSeeder::class, '--force' => true]);

        return User::find(UserSeeder::SUPER_ADMIN_ID);
    }

    /**
     * Authenticate the super-admin user for HTTP tests.
     */
    protected function actAsSuperAdmin(): User
    {
        $user = $this->seedSuperAdmin();

        $this->actingAs($user);

        return $user;
    }
}
