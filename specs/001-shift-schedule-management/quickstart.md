# Quickstart Validation Guide: Shift Categories & Time Schedules

**Feature**: إدارة فئات النوبات وجداول الوقت
**Date**: 2026-07-15
**Module**: `Modules/Shifts`

---

## Prerequisites

1. Database migrated with new tables (`php artisan migrate`)
2. Permissions seeded (`php artisan db:seed --class=ShiftCategoryPermissionSeeder`)
3. At least 1 company, 1 department, and 2 active employees exist
4. At least 1 official holiday defined in `att_holiday`

---

## Scenario 1: Create Cyclic Shift Category (AC1)

**Goal**: Verify cyclic shift category creation and auto-continuation across months.

**Steps**:
1. Run: `POST /shift-categories` with:
   ```json
   {
     "name": "دوام 3+9",
     "type": "cyclic",
     "work_days": 3,
     "rest_days": 9,
     "overtime_enabled": true,
     "fingerprint_enabled": true,
     "color": "#4CAF50"
   }
   ```
2. Assign employee #1 to this category with `start_date = 2026-07-01`
3. Call `GET /schedule-calendar/1?month=2026-07` (employee calendar API)
4. Call `GET /schedule-calendar/1?month=2026-08`

**Expected**:
- July response shows work days: 1,2,3 then rest 4-12, work 13,14,15, rest 16-24, work 25,26,27, rest 28-31+ (continues)
- August response shows work days auto-continuing: rest 1-5, work 6,7,8, rest 9-17, work 18,19,20, rest 21-29, work 30,31+...
- Calendar displays green for work days, gray for rest days

---

## Scenario 2: Create Weekly Fixed (Admin) Category & Absence Report (AC2)

**Goal**: Verify that admin employees only show absent on their work days.

**Steps**:
1. Create time schedule "دوام إداري" with `in_time=08:00`, `out_time=16:00`, `late_margin=15`
2. Create shift category "إداري" with:
   ```json
   {
     "name": "إداري",
     "type": "weekly",
     "work_days_json": ["sunday", "monday", "tuesday", "wednesday", "thursday"],
     "weekend_days_json": ["friday", "saturday"],
     "work_on_holidays": false
   }
   ```
3. Link category to time schedule (1:1)
4. Assign employee #2 with `start_date = 2026-07-01`
5. Simulate: record punch for Sun, Mon (07-05, 07-06), no punch for Tue (07-07)
6. Run absence report for Friday 07-10: `GET /attendance/absences?date=2026-07-10`
7. Run absence report for Tuesday 07-07: `GET /attendance/absences?date=2026-07-07`

**Expected**:
- Friday report: employee #2 does NOT appear (not a work day)
- Tuesday report: employee #2 appears as absent (work day, no punch)

---

## Scenario 3: Employee Transfer Between Categories (AC3)

**Goal**: Verify transfer preserves history and uses correct category for each period.

**Steps**:
1. Employee #1 is on "دوام 3+9" (cyclic) since 07-01
2. On 08-01, transfer to "إداري" (weekly fixed)
3. Check `att_employee_shift_categories` records for employee #1
4. Run absence report for July 2026
5. Run absence report for August 2026

**Expected**:
- Two records exist: one with start=07-01, end=07-31 (cyclic); one with start=08-01, end=NULL (admin)
- July report uses 3+9 pattern for absence calculation
- August report uses Sun-Thu pattern for absence calculation
- July record has `snapshot_data` containing the 3+9 category data
- August record has `snapshot_data` containing the admin category data

---

## Scenario 4: Smart Absence for Cyclic Pattern (AC4)

**Goal**: Verify employees on cyclic patterns are only absent on their work days.

**Steps**:
1. Employee #1 on "دوام 1+3" (1 work, 3 rest) with `start_date = 2026-07-04` (Saturday)
2. Employee has no punches on any day
3. Run absence report for: Sat 07-04, Sun 07-05, Mon 07-06, Tue 07-07

**Expected**:
- 07-04 (Sat): absent (first work day, no punch)
- 07-05 (Sun): NOT absent (first rest day)
- 07-06 (Mon): NOT absent (second rest day)
- 07-07 (Tue): NOT absent (third rest day)

---

## Scenario 5: Multi-Day Continuous Shift Attendance (Edge Case #8)

**Goal**: Verify auto-attendance for intermediate days in multi-day continuous shifts.

**Steps**:
1. Create time schedule "دوام متواصل 3 أيام" with `in_time=07:00`, `out_time=07:00`, `is_multi_day=true`
2. Link to "دوام 3+9" category
3. Employee #1 starts cycle on 07-01
4. Record punch IN on 07-01 at 07:05
5. Record punch OUT on 07-04 at 07:10
6. Check attendance status for 07-02 and 07-03

**Expected**:
- 07-01: Present (punch IN recorded)
- 07-02: Auto-present (intermediate day, no punch needed)
- 07-03: Auto-present (intermediate day, no punch needed)
- 07-04: Out-punch recorded (end of work block)
- Total work block spans 3 calendar days

---

## Scenario 6: Hours-Based Deficit (FR1.4, BR3.2)

**Goal**: Verify deficit calculation for hours-based categories.

**Steps**:
1. Create category "ساعات أسبوعية" with `type=hours`, `required_hours=40`, `period_type=weekly`
2. Assign employee #2 with `start_date = 2026-07-06` (Monday)
3. Employee records punches totaling 30 hours for week 07-06 to 07-12
4. Run hours tracking calculation for that week

**Expected**:
- `att_hours_tracking` record created for 07-06 to 07-12
- `actual_hours = 30`, `deficit_hours = 10`, `status = deficit`
- Next week starts fresh with no carryover of the 10-hour deficit

---

## Scenario 7: CRUD Operations (AC5)

**Goal**: Verify full CRUD with permissions.

**Steps**:
1. Login as user WITHOUT `create-shift-categories` permission → attempt POST
2. Login as user WITH the permission → create, view, update a category
3. Attempt to delete a category with active employees assigned
4. Delete after reassigning all employees

**Expected**:
- Unauthorized: 403 Forbidden
- Authorized: CRUD succeeds
- Delete with active employees: validation error
- Delete after reassignment: succeeds (hard delete)

---

## Scenario 8: Performance (AC6)

**Goal**: Verify performance targets.

**Steps**:
1. Seed 200 employees assigned to various categories (cyclic, weekly, hours)
2. Call `GET /schedule-calendar/{id}?days=30` for a cyclic employee
3. Call `GET /attendance/absences?date=2026-07-15&department_id=X` for a department of 200

**Expected**:
- Calendar response < 500ms
- Absence report < 3 seconds
- N+1 queries should be 0 (check Laravel Debugbar or query log)

---

## Prerequisite Data Setup

```bash
# Run migrations
php artisan migrate

# Seed permissions
php artisan db:seed --class=ShiftCategoryPermissionSeeder

# Create test data (via Tinker or seeder)
php artisan tinker
```

Example Tinker setup:
```php
// Ensure at least one company exists
$company = \Modules\Companies\Models\Company::first();

// Ensure test employees exist
$emp1 = \Modules\Users\Models\User::where('emp_code', 'EMP001')->first();
$emp2 = \Modules\Users\Models\User::where('emp_code', 'EMP002')->first();

// Ensure a holiday exists for testing
\Modules\Holidays\Models\Holiday::create([
    'alias' => 'Test Holiday',
    'start_date' => '2026-07-20',
    'end_date' => '2026-07-20',
    'duration_day' => 1,
    'company_id' => $company->id,
]);
```
