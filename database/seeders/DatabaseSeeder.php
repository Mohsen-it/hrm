<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Branches\Database\Seeders\BranchesDatabaseSeeder;
use Modules\Companies\Database\Seeders\CompaniesDatabaseSeeder;
use Modules\FingerprintDevices\Database\Seeders\FingerprintDeviceTypesSeeder;
use Modules\Subordinations\Database\Seeders\SubordinationPermissionsSeeder;
use Modules\Subordinations\Database\Seeders\SubordinationsDatabaseSeeder;

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
     *  4. {@see CompaniesDatabaseSeeder} / {@see BranchesDatabaseSeeder}
     *     insert the foundational org-structure data (Aleppo Airport).
     *  5. {@see SubordinationsDatabaseSeeder} inserts the airport
     *     locations (Aleppo + Latakia).
     *  6. {@see SubordinationPermissionsSeeder} creates the 4
     *     subordination permissions and grants them to super-admin.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            UserSeeder::class,
            RamadanDatesSeeder::class,
            FingerprintDeviceTypesSeeder::class,
            CompaniesDatabaseSeeder::class,
            BranchesDatabaseSeeder::class,
            SubordinationsDatabaseSeeder::class,
            SubordinationPermissionsSeeder::class,
        ]);
    }
}
