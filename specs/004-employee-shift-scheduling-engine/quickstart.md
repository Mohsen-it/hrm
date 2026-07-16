# Quickstart Validation Guide - Employee Shift Scheduling Engine

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-16

---

## Prerequisites

1. PHP 8.3+
2. Laravel 13
3. MySQL 8.0+ or SQLite
4. Node.js 18+
5. Composer
6. npm

---

## Setup Commands

```bash
# 1. Install dependencies
composer install
npm install

# 2. Copy environment file
cp .env.example .env

# 3. Generate application key
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Seed shift patterns
php artisan db:seed --class=Modules\Shifts\Database\Seeders\ShiftPatternSeeder

# 6. Start development server
php artisan serve
```

---

## Validation Scenarios

### Scenario 1: Create Shift Pattern 1+3

**Steps:**
1. Login as admin
2. Navigate to Shift Patterns
3. Click "Create Pattern"
4. Fill form:
   - Name (AR): يوم دوام واحد / 3 أيام راحة
   - Name (EN): 1 Day Work / 3 Days Off
   - Code: 1W3R
   - Work Days: 1
   - Rest Days: 3
5. Click "Save"

**Expected:**
- Pattern created successfully
- Cycle length = 4 (auto-calculated)
- Redirected to patterns list

---

### Scenario 2: Create 4 Duty Categories

**Steps:**
1. Navigate to Duty Categories
2. Create Category 1:
   - Name: الفئة 1
   - Code: CAT1
   - Pattern: 1W3R
   - Cycle Start Date: 2026-08-01
3. Create Category 2:
   - Name: الفئة 2
   - Code: CAT2
   - Pattern: 1W3R
   - Cycle Start Date: 2026-08-02
4. Create Category 3:
   - Name: الفئة 3
   - Code: CAT3
   - Pattern: 1W3R
   - Cycle Start Date: 2026-08-03
5. Create Category 4:
   - Name: الفئة 4
   - Code: CAT4
   - Pattern: 1W3R
   - Cycle Start Date: 2026-08-04

**Expected:**
- All 4 categories created
- Each has unique start date
- All linked to 1W3R pattern

---

### Scenario 3: Assign Employees

**Steps:**
1. Navigate to Assignments
2. Assign Employee A to Category 1 (effective 2026-08-01)
3. Assign Employee B to Category 2 (effective 2026-08-01)
4. Assign Employee C to Category 3 (effective 2026-08-01)
5. Assign Employee D to Category 4 (effective 2026-08-01)

**Expected:**
- All assignments created
- Each employee linked to one category
- Shift pattern inherited from category

---

### Scenario 4: Generate August 2026 Schedule

**Steps:**
1. Navigate to Schedules
2. Click "Generate Schedule"
3. Select Year: 2026
4. Select Month: August
5. Click "Generate"

**Expected:**
- Schedule generated with draft status
- 31 entries per employee
- Category 1 works: 1, 5, 9, 13, 17, 21, 25, 29
- Category 2 works: 2, 6, 10, 14, 18, 22, 26, 30
- Category 3 works: 3, 7, 11, 15, 19, 23, 27, 31
- Category 4 works: 4, 8, 12, 16, 20, 24, 28

---

### Scenario 5: Publish Schedule

**Steps:**
1. View August 2026 schedule
2. Click "Publish Schedule"
3. Confirm publication

**Expected:**
- Status changes to "published"
- published_by and published_at recorded
- Schedule cannot be modified (only regenerated)

---

### Scenario 6: Verify Cross-Month Continuity

**Steps:**
1. Generate September 2026 schedule
2. Check Category 1 work days

**Expected:**
- September continues from August
- Category 1 works: 2, 6, 10, 14, 18, 22, 26, 30
- No reset at month boundary

---

### Scenario 7: Test 7+21 Pattern

**Steps:**
1. Create pattern "7W21R"
2. Create 4 categories with different start dates
3. Assign employees
4. Generate schedule for August-October

**Expected:**
- 7 consecutive work days
- 21 consecutive rest days
- Cycle continues across months

---

### Scenario 8: Transfer Employee

**Steps:**
1. View Employee A (currently Category 1)
2. Transfer to Category 3 (effective 2026-09-01)

**Expected:**
- Previous assignment closed (effective_to = 2026-08-31)
- New assignment created (effective_from = 2026-09-01)
- August schedule unchanged
- September schedule uses Category 3

---

### Scenario 9: Bulk Assignment

**Steps:**
1. Navigate to Bulk Assign
2. Select 10 employees
3. Filter by Department
4. Select target Category
5. Preview changes
6. Confirm

**Expected:**
- All 10 employees assigned
- Transaction ensures atomicity
- Audit log created

---

### Scenario 10: Test Leave Integration

**Steps:**
1. Create leave request for Employee A
2. Period: 2026-08-01 to 2026-08-05

**Expected:**
- Calendar duration: 5 days
- Scheduled work days: 2 (Aug 1, 5)
- Scheduled rest days: 3 (Aug 2, 3, 4)
- days_charged = 5 (calendar duration per current rules)

---

## Performance Validation

### Test with 1000 Employees

**Steps:**
1. Create 1000 employee records
2. Assign to various categories
3. Generate schedule for one month
4. Measure time

**Expected:**
- Generation completes in < 20 seconds
- No N+1 queries
- Memory usage stable

---

## API Validation

### Test Permissions

**Steps:**
1. Login as regular user (no permissions)
2. Try to access:
   - POST /shifts/patterns
   - POST /shifts/categories
   - POST /shifts/schedules/generate

**Expected:**
- 403 Forbidden for all unauthorized actions

---

## Database Validation

### Check Relationships

**SQL Queries:**
```sql
-- Verify all categories have valid patterns
SELECT dc.id, dc.name, dc.shift_pattern_id
FROM duty_categories dc
LEFT JOIN shift_patterns sp ON sp.id = dc.shift_pattern_id
WHERE sp.id IS NULL;

-- Verify all assignments have valid categories
SELECT esa.id, esa.employee_id, esa.duty_category_id
FROM employee_shift_assignments esa
LEFT JOIN duty_categories dc ON dc.id = esa.duty_category_id
WHERE dc.id IS NULL;

-- Verify no overlapping assignments
SELECT employee_id, COUNT(*) as overlap_count
FROM employee_shift_assignments
WHERE effective_to IS NULL
GROUP BY employee_id
HAVING COUNT(*) > 1;
```

**Expected:**
- No orphan records
- No overlapping assignments

---

*آخر تحديث: 2026-07-16*
