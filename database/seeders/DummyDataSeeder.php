<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds dummy data across the application for development/testing.
 *
 * Creates: company, branch, department, position, grade, time schedules,
 *          employees, a rotation with groups, and assigns the rotation.
 */
class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        // ── Company ──────────────────────────────────────────────
        DB::table('companies')->updateOrInsert(
            ['company_code' => 'HRM001'],
            [
                'company_name' => 'شركة الموارد البشرية',
                'email' => 'info@hrm.local',
                'phone' => '+966500000000',
                'city' => 'Riyadh',
                'country' => 'SA',
                'status' => 1,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $companyId = DB::table('companies')->where('company_code', 'HRM001')->first()->id;

        // ── Branch ───────────────────────────────────────────────
        DB::table('branches')->updateOrInsert(
            ['branch_code' => 'BR001'],
            [
                'company_id' => $companyId,
                'branch_name' => 'الفرع الرئيسي',
                'email' => 'riyadh@hrm.local',
                'city' => 'Riyadh',
                'country' => 'SA',
                'is_main' => 1,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $branchId = DB::table('branches')->where('branch_code', 'BR001')->first()->id;

        // ── Department ───────────────────────────────────────────
        DB::table('departments')->updateOrInsert(
            ['department_code' => 'DEPT001'],
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'department_name' => 'قسم العمليات',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $deptId = DB::table('departments')->where('department_code', 'DEPT001')->first()->id;

        // ── Position ─────────────────────────────────────────────
        DB::table('positions')->updateOrInsert(
            ['position_code' => 'POS001'],
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'department_id' => $deptId,
                'position_name' => 'مشغل',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $positionId = DB::table('positions')->where('position_code', 'POS001')->first()->id;

        // ── Grade ────────────────────────────────────────────────
        DB::table('grades')->updateOrInsert(
            ['grade_code' => 'GRD001'],
            [
                'company_id' => $companyId,
                'grade_name' => 'الدرجة الأولى',
                'level' => 1,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $gradeId = DB::table('grades')->where('grade_code', 'GRD001')->first()->id;

        // ── Time Schedules ───────────────────────────────────────
        DB::table('att_time_schedules')->updateOrInsert(
            ['name' => 'صباحي 07:00-15:00'],
            [
                'company_id' => $companyId,
                'in_time' => '07:00',
                'out_time' => '15:00',
                'is_multi_day' => 0,
                'late_margin' => 15,
                'early_margin' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $scheduleId = DB::table('att_time_schedules')->where('name', 'صباحي 07:00-15:00')->first()->id;

        DB::table('att_time_schedules')->updateOrInsert(
            ['name' => 'مسائي 15:00-23:00'],
            [
                'company_id' => $companyId,
                'in_time' => '15:00',
                'out_time' => '23:00',
                'is_multi_day' => 0,
                'late_margin' => 15,
                'early_margin' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        // ── Employees ────────────────────────────────────────────
        $employees = [
            ['employee_code' => 'EMP001', 'first_name' => 'أحمد', 'last_name' => 'محمد', 'name' => 'أحمد محمد', 'full_name_ar' => 'أحمد محمد', 'full_name_en' => 'Ahmed Mohammed'],
            ['employee_code' => 'EMP002', 'first_name' => 'خالد', 'last_name' => 'علي', 'name' => 'خالد علي', 'full_name_ar' => 'خالد علي', 'full_name_en' => 'Khalid Ali'],
            ['employee_code' => 'EMP003', 'first_name' => 'سعيد', 'last_name' => 'عبدالله', 'name' => 'سعيد عبدالله', 'full_name_ar' => 'سعيد عبدالله', 'full_name_en' => 'Saeed Abdullah'],
            ['employee_code' => 'EMP004', 'first_name' => 'فهد', 'last_name' => 'الشمري', 'name' => 'فهد الشمري', 'full_name_ar' => 'فهد الشمري', 'full_name_en' => 'Fahad Al-Shammari'],
            ['employee_code' => 'EMP005', 'first_name' => 'عمر', 'last_name' => 'العتيبي', 'name' => 'عمر العتيبي', 'full_name_ar' => 'عمر العتيبي', 'full_name_en' => 'Omar Al-Otaibi'],
        ];

        foreach ($employees as $i => $emp) {
            DB::table('users')->updateOrInsert(
                ['employee_code' => $emp['employee_code']],
                array_merge($emp, [
                    'email' => strtolower($emp['employee_code']) . '@hrm.local',
                    'password' => Hash::make('password'),
                    'status' => 1,
                    'is_active_employee' => 1,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'department_id' => $deptId,
                    'position_id' => $positionId,
                    'grade_id' => $gradeId,
                    'gender' => 'male',
                    'nationality' => 'SA',
                    'hire_date' => '2024-01-01',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }
    }
}
