<?php

namespace Modules\Subordinations\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Subordinations\Models\Subordination;

class SubordinationsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Subordination::updateOrCreate(
            ['code' => 'ALEPPO-AIRPORT'],
            [
                'name_ar' => 'مطار حلب',
                'name_en' => 'Aleppo Airport',
                'description' => 'مطار حلب الدولي - المقر الرئيسي لشركة مطار حلب الدولي',
                'sort_order' => 1,
                'status' => 1,
            ]
        );

        Subordination::updateOrCreate(
            ['code' => 'LATTAKIA-AIRPORT'],
            [
                'name_ar' => 'مطار اللاذقية',
                'name_en' => 'Latakia Airport',
                'description' => 'مطار اللاذقية الدولي - تابع لشركة مطار حلب الدولي',
                'sort_order' => 2,
                'status' => 1,
            ]
        );
    }
}
