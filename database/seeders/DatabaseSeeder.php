<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\FingerprintDevices\Database\Seeders\FingerprintDeviceTypesSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The order matters:
     *  1. {@see PermissionSeeder} materialises the permission
     *     catalogue that the rest of the seeders reference.
     *  2. {@see UserSeeder} creates the canonical super-admin user
     *     (id = 10000) and grants it every permission.
     *  3. {@see RamadanDatesSeeder} inserts the multi-year Ramadan
     *     schedule used by the attendance calculations.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            UserSeeder::class,
            RamadanDatesSeeder::class,
            FingerprintDeviceTypesSeeder::class,
        ]);
    }
}
