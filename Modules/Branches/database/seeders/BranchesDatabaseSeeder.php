<?php

namespace Modules\Branches\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;

class BranchesDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('company_code', 'AIRPORT-ALEPPO')->first();

        if (! $company) {
            $this->command?->warn('CompaniesDatabaseSeeder must run before BranchesDatabaseSeeder. Skipping branch seeding.');

            return;
        }

        Branch::updateOrCreate(
            ['company_id' => $company->id, 'branch_code' => 'CIVIL-AVIATION'],
            [
                'branch_name' => 'الطيران المدني',
                'city' => 'حلب',
                'country' => 'SY',
                'is_main' => true,
                'status' => 1,
                'description' => 'الهيئة العامة للطيران المدني السوري - مطار حلب',
            ]
        );

        Branch::updateOrCreate(
            ['company_id' => $company->id, 'branch_code' => 'SYRIAN-AIR'],
            [
                'branch_name' => 'الخطوط الجوية السورية',
                'city' => 'حلب',
                'country' => 'SY',
                'is_main' => false,
                'status' => 1,
                'description' => 'الخطوط الجوية السورية - مطار حلب',
            ]
        );
    }
}
