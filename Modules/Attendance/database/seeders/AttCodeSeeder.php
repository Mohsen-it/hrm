<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Models\AttCode;

class AttCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => 'P', 'alias' => 'Present', 'symbol' => 'P', 'display_format' => 0, 'order' => 1],
            ['code' => 'A', 'alias' => 'Absent', 'symbol' => 'A', 'display_format' => 0, 'order' => 2],
            ['code' => 'L', 'alias' => 'Late', 'symbol' => 'L', 'display_format' => 0, 'order' => 3],
            ['code' => 'EL', 'alias' => 'Early Leave', 'symbol' => 'EL', 'display_format' => 0, 'order' => 4],
            ['code' => 'MP', 'alias' => 'Missing Punch', 'symbol' => 'MP', 'display_format' => 0, 'order' => 5],
            ['code' => 'HD', 'alias' => 'Holiday', 'symbol' => 'HD', 'display_format' => 0, 'order' => 6],
            ['code' => 'V', 'alias' => 'Vacation', 'symbol' => 'V', 'display_format' => 0, 'order' => 7],
            ['code' => 'WO', 'alias' => 'Week Off', 'symbol' => 'WO', 'display_format' => 0, 'order' => 8],
        ];

        foreach ($codes as $code) {
            AttCode::updateOrCreate(
                ['code' => $code['code']],
                $code
            );
        }
    }
}
