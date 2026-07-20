<?php

namespace Modules\Companies\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Companies\Models\Company;

class CompaniesDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Company::updateOrCreate(
            ['company_code' => 'AIRPORT-ALEPPO'],
            [
                'company_name' => 'مطار حلب الدولي',
                'city' => 'حلب',
                'country' => 'SY',
                'is_default' => true,
                'status' => 1,
                'description' => 'المطار الدولي في مدينة حلب',
            ]
        );
    }
}
