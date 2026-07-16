<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Models\PayCode;

class PayCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => 'WO', 'name' => 'Work', 'code_type' => 0, 'is_work' => true, 'is_paid' => true, 'is_display' => true, 'is_default' => true, 'display_order' => 1],
            ['code' => 'AL', 'name' => 'Annual Leave', 'code_type' => 1, 'is_work' => false, 'is_paid' => true, 'is_benefit' => true, 'is_display' => true, 'display_order' => 2],
            ['code' => 'SL', 'name' => 'Sick Leave', 'code_type' => 1, 'is_work' => false, 'is_paid' => true, 'is_benefit' => true, 'is_display' => true, 'display_order' => 3],
            ['code' => 'OT', 'name' => 'Overtime', 'code_type' => 2, 'is_work' => true, 'is_paid' => true, 'is_benefit' => true, 'is_display' => true, 'display_order' => 4],
            ['code' => 'AB', 'name' => 'Absent', 'code_type' => 3, 'is_work' => false, 'is_paid' => false, 'is_display' => true, 'display_order' => 5],
            ['code' => 'HD', 'name' => 'Holiday', 'code_type' => 3, 'is_work' => false, 'is_paid' => true, 'is_benefit' => true, 'is_display' => true, 'display_order' => 6],
            ['code' => 'WOFF', 'name' => 'Week Off', 'code_type' => 3, 'is_work' => false, 'is_paid' => true, 'is_benefit' => true, 'is_display' => true, 'display_order' => 7],
        ];

        foreach ($codes as $code) {
            PayCode::updateOrCreate(
                ['code' => $code['code']],
                $code
            );
        }
    }
}
