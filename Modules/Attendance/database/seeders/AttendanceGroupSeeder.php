<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Models\AttendanceGroup;
use Modules\Companies\Models\Company;

class AttendanceGroupSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            AttendanceGroup::updateOrCreate(
                ['company_id' => $company->id, 'code' => 'DEFAULT'],
                [
                    'name' => 'افتراضي',
                    'status' => 1,
                ]
            );
        }
    }
}
