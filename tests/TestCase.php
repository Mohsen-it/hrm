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
        // Force the isolated SQLite test environment in EVERY source Laravel
        // may read. PHPUnit's phpunit.xml <env force="true"> only updates
        // getenv() and $_ENV, but on this machine a real DB_CONNECTION=mysql /
        // DB_DATABASE=hrmair pair also sits in $_SERVER, and Laravel's env()
        // resolution can prefer $_SERVER. Without this triple assignment the
        // test suite would run RefreshDatabase against the production MySQL
        // database and wipe it.
        foreach ([
            // The same real-environment override problem applies to APP_ENV: a
            // machine-level APP_ENV=local (or .env value) shadows phpunit.xml's
            // non-forced <env>, so the app boots as "local" and runningUnitTests()
            // returns false — which re-enables CSRF and makes every feature-test
            // POST fail with 419. Forcing it here keeps the suite green.
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DB_HOST' => '',
            'DB_PORT' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        // Boot the application first so the guard below can inspect the real
        // resolved database connection BEFORE any destructive trait runs.
        if (! $this->app) {
            $this->refreshApplication();
        }

        // Hard safety guard: the test suite MUST run on the in-memory SQLite
        // database. RefreshDatabase drops every table it finds, so a real
        // MySQL database must never be reachable from a test process. If this
        // guard ever trips, fix the environment (config cache / OS env vars)
        // before running any test.
        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException(
                'Refusing to run tests: database.default is "'.config('database.default')
                .'". Tests must run on "sqlite" (:memory:) to protect real data.'
            );
        }

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
