# Data Model - Employee Shift Scheduling Engine

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-16

---

## Entity Relationship Diagram

```
┌─────────────────────┐
│    shift_patterns    │
├─────────────────────┤
│ id (PK)             │
│ name_ar             │
│ name_en             │
│ code (UNIQUE)       │
│ work_days           │
│ rest_days           │
│ cycle_length        │
│ description         │
│ is_active           │
│ created_at          │
│ updated_at          │
└─────────┬───────────┘
          │ 1:N
          ▼
┌─────────────────────┐
│   duty_categories   │
├─────────────────────┤
│ id (PK)             │
│ name                │
│ code (UNIQUE)       │
│ shift_pattern_id (FK)│
│ cycle_start_date    │
│ display_order       │
│ is_active           │
│ created_at          │
│ updated_at          │
└─────────┬───────────┘
          │ 1:N
          ▼
┌─────────────────────────────┐
│ employee_shift_assignments  │
├─────────────────────────────┤
│ id (PK)                     │
│ employee_id (FK)            │
│ duty_category_id (FK)       │
│ effective_from              │
│ effective_to                │
│ assigned_by (FK)            │
│ created_at                  │
└─────────┬───────────────────┘
          │
          ▼
┌─────────────────────┐
│   personnel_employee │
├─────────────────────┤
│ id (PK)             │
│ name                │
│ department_id (FK)  │
│ ...                 │
└─────────────────────┘

┌─────────────────────┐
│  schedule_periods   │
├─────────────────────┤
│ id (PK)             │
│ year                │
│ month               │
│ schedule_period_start│
│ schedule_period_end │
│ status              │
│ generated_by (FK)   │
│ generated_at        │
│ published_by (FK)   │
│ published_at        │
│ schedule_version    │
│ created_at          │
│ updated_at          │
└─────────┬───────────┘
          │ 1:N
          ▼
┌─────────────────────┐
│  schedule_entries   │
├─────────────────────┤
│ id (PK)             │
│ schedule_period_id (FK)│
│ employee_id (FK)    │
│ duty_category_id (FK)│
│ date                │
│ day_status          │
│ created_at          │
└─────────────────────┘

┌─────────────────────┐
│    audit_logs       │
├─────────────────────┤
│ id (PK)             │
│ actor_id (FK)       │
│ action              │
│ entity_type         │
│ entity_id           │
│ old_values (JSON)   │
│ new_values (JSON)   │
│ created_at          │
└─────────────────────┘
```

---

## Entity Details

### 1. shift_patterns

**Description:** Defines the cyclic work/rest pattern (e.g., 1 day work + 3 days rest)

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| name_ar | VARCHAR(255) | NOT NULL | Arabic name |
| name_en | VARCHAR(255) | NOT NULL | English name |
| code | VARCHAR(50) | NOT NULL, UNIQUE | Unique pattern code |
| work_days | INT UNSIGNED | NOT NULL | Number of consecutive work days |
| rest_days | INT UNSIGNED | NOT NULL | Number of consecutive rest days |
| cycle_length | INT UNSIGNED | NOT NULL | Calculated: work_days + rest_days |
| description | TEXT | NULLABLE | Pattern description |
| is_active | BOOLEAN | DEFAULT TRUE | Active status |
| created_at | TIMESTAMP | DEFAULT CURRENT | Creation timestamp |
| updated_at | TIMESTAMP | AUTO UPDATE | Last update timestamp |

**Business Rules:**
- `cycle_length` must equal `work_days + rest_days`
- Cannot delete pattern with active duty categories
- Disabling pattern prevents new categories but doesn't affect existing ones

### 2. duty_categories

**Description:** Categories within a pattern, each with independent cycle start date

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| name | VARCHAR(255) | NOT NULL | Category name |
| code | VARCHAR(50) | NOT NULL, UNIQUE | Unique category code |
| shift_pattern_id | BIGINT UNSIGNED | FK → shift_patterns.id, NOT NULL | Parent pattern |
| cycle_start_date | DATE | NOT NULL | Independent cycle start date |
| display_order | INT UNSIGNED | DEFAULT 0 | Display order |
| is_active | BOOLEAN | DEFAULT TRUE | Active status |
| created_at | TIMESTAMP | DEFAULT CURRENT | Creation timestamp |
| updated_at | TIMESTAMP | AUTO UPDATE | Last update timestamp |

**Business Rules:**
- `cycle_start_date` must be unique within the same pattern
- Changing `cycle_start_date` requires `effective_from` date
- Does not affect published historical schedules

**Unique Constraint:**
```sql
UNIQUE(shift_pattern_id, cycle_start_date)
```

### 3. employee_shift_assignments

**Description:** Links employees to duty categories with effective dates

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| employee_id | BIGINT UNSIGNED | FK → personnel_employee.id, NOT NULL | Assigned employee |
| duty_category_id | BIGINT UNSIGNED | FK → duty_categories.id, NOT NULL | Target category |
| effective_from | DATE | NOT NULL | Assignment start date |
| effective_to | DATE | NULLABLE | Assignment end date (NULL = ongoing) |
| assigned_by | BIGINT UNSIGNED | FK → users.id, NOT NULL | Who made the assignment |
| created_at | TIMESTAMP | DEFAULT CURRENT | Creation timestamp |

**Business Rules:**
- One employee = one active assignment at any time
- Cannot assign inactive employees
- Previous assignment automatically closes when new one starts

**Indexes:**
```sql
INDEX(employee_id, effective_from, effective_to)
INDEX(duty_category_id, effective_from)
```

### 4. schedule_periods

**Description:** Represents a generated monthly schedule period

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| year | INT UNSIGNED | NOT NULL | Schedule year |
| month | INT UNSIGNED | NOT NULL | Schedule month |
| schedule_period_start | DATE | NOT NULL | Period start date |
| schedule_period_end | DATE | NOT NULL | Period end date |
| status | ENUM | 'draft', 'published' | Current status |
| generated_by | BIGINT UNSIGNED | FK → users.id, NOT NULL | Who generated |
| generated_at | TIMESTAMP | NOT NULL | Generation timestamp |
| published_by | BIGINT UNSIGNED | FK → users.id, NULLABLE | Who published |
| published_at | TIMESTAMP | NULLABLE | Publication timestamp |
| schedule_version | INT UNSIGNED | DEFAULT 1 | Version number |
| created_at | TIMESTAMP | DEFAULT CURRENT | Creation timestamp |
| updated_at | TIMESTAMP | AUTO UPDATE | Last update timestamp |

**Business Rules:**
- Draft → Published workflow
- Regeneration creates new version (never deletes old)
- Unique constraint on (year, month, schedule_version)

**Unique Constraint:**
```sql
UNIQUE(year, month, schedule_version)
```

### 5. schedule_entries

**Description:** Individual day entries in a schedule period

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| schedule_period_id | BIGINT UNSIGNED | FK → schedule_periods.id, NOT NULL | Parent period |
| employee_id | BIGINT UNSIGNED | FK → personnel_employee.id, NOT NULL | Employee |
| duty_category_id | BIGINT UNSIGNED | FK → duty_categories.id, NOT NULL | Duty category |
| date | DATE | NOT NULL | Specific date |
| day_status | ENUM | 'WORK', 'REST' | Day status |
| created_at | TIMESTAMP | DEFAULT CURRENT | Creation timestamp |

**Indexes:**
```sql
INDEX(schedule_period_id, employee_id)
INDEX(employee_id, date)
INDEX(duty_category_id, date)
```

### 6. audit_logs

**Description:** Tracks all critical scheduling operations

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| actor_id | BIGINT UNSIGNED | FK → users.id, NOT NULL | Who performed action |
| action | VARCHAR(100) | NOT NULL | Action type |
| entity_type | VARCHAR(100) | NOT NULL | Entity class name |
| entity_id | BIGINT UNSIGNED | NOT NULL | Entity ID |
| old_values | JSON | NULLABLE | Previous values |
| new_values | JSON | NULLABLE | New values |
| created_at | TIMESTAMP | DEFAULT CURRENT | Action timestamp |

**Indexes:**
```sql
INDEX(entity_type, entity_id)
INDEX(actor_id, created_at)
```

---

## Relationships Summary

| Relationship | Type | Foreign Key | On Delete |
|--------------|------|-------------|-----------|
| ShiftPattern → DutyCategory | One-to-Many | shift_pattern_id | RESTRICT |
| DutyCategory → EmployeeShiftAssignment | One-to-Many | duty_category_id | RESTRICT |
| Employee → EmployeeShiftAssignment | One-to-Many | employee_id | CASCADE |
| SchedulePeriod → ScheduleEntry | One-to-Many | schedule_period_id | CASCADE |
| User → EmployeeShiftAssignment | One-to-Many | assigned_by | RESTRICT |
| User → SchedulePeriod | One-to-Many | generated_by, published_by | RESTRICT |

---

## State Transitions

### Schedule Period Status
```
Draft → Published
```

### Employee Assignment
```
Active (effective_to = NULL)
    ↓
Closed (effective_to = date)
```

---

## Data Volume Assumptions

| Entity | Estimated Volume | Growth Rate |
|--------|------------------|-------------|
| shift_patterns | 10-50 | Low |
| duty_categories | 50-200 | Low |
| employee_shift_assignments | 1,000-10,000 | Medium |
| schedule_periods | 100-500/year | Medium |
| schedule_entries | 30,000-300,000/year | High |
| audit_logs | 10,000-100,000/year | High |

---

*آخر تحديث: 2026-07-16*
