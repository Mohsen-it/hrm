# Data Model: Shift Categories & Time Schedules

**Feature**: إدارة فئات النوبات وجداول الوقت
**Date**: 2026-07-15
**Module**: `Modules/Shifts`

---

## Entity Relationship Diagram

```
┌──────────────────────────┐       ┌──────────────────────────┐
│   att_shift_categories   │       │    att_time_schedules    │
│──────────────────────────│       │──────────────────────────│
│ id (PK)                  │       │ id (PK)                  │
│ company_id (FK)          │ 1   1 │ company_id (FK)          │
│ name                     │◄─────►│ name                     │
│ type (enum)              │       │ in_time                  │
│ work_days                │       │ out_time                 │
│ rest_days                │       │ is_multi_day             │
│ work_days_json (JSON)    │       │ late_margin              │
│ weekend_days_json (JSON) │       │ early_margin             │
│ required_hours           │       │ timestamps               │
│ period_type (enum)       │       └──────────┬───────────────┘
│ overtime_enabled         │                   │
│ fingerprint_enabled      │                   │ 1
│ work_on_holidays         │                   │
│ work_on_weekends         │                   │
│ color                    │                   ▼
│ timestamps               │       ┌──────────────────────────┐
└──────────┬───────────────┘       │ att_time_schedule_breaks │
           │                       │──────────────────────────│
           │ 1                     │ id (PK)                  │
           │                       │ schedule_id (FK)         │
           ▼                       │ break_start              │
┌──────────────────────────┐       │ duration                 │
│ att_category_time_schedule│      │ break_end                │
│──────────────────────────│       │ timestamps               │
│ id (PK)                  │       └──────────────────────────┘
│ shift_category_id (FK,U) │
│ time_schedule_id (FK)    │
│ timestamps               │
└──────────────────────────┘

┌──────────────────────────┐       ┌──────────────────────────┐
│att_employee_shift_       │       │   att_hours_tracking     │
│     categories           │       │──────────────────────────│
│──────────────────────────│       │ id (PK)                  │
│ id (PK)                  │       │ employee_id (FK)         │
│ employee_id (FK)         │       │ period_start             │
│ shift_category_id (FK)   │       │ period_end               │
│ start_date               │       │ period_type (enum)       │
│ end_date (nullable)      │       │ required_hours           │
│ snapshot_data (JSON)     │       │ actual_hours             │
│ timestamps               │       │ surplus_hours            │
└──────────────────────────┘       │ deficit_hours            │
                                   │ status (enum)            │
                                   │ timestamps               │
                                   └──────────────────────────┘
```

---

## Table: `att_shift_categories`

Shift category definitions (cyclic, weekly fixed, hours-based).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `company_id` | BIGINT UNSIGNED | FK → personnel_company.id, NOT NULL | Owning company |
| `name` | VARCHAR(100) | NOT NULL | Category name |
| `type` | ENUM('cyclic', 'weekly', 'hours') | NOT NULL | Category type |
| `work_days` | SMALLINT UNSIGNED | NULL (cyclic only), > 0 | Number of consecutive work days |
| `rest_days` | SMALLINT UNSIGNED | NULL (cyclic only), >= 0 | Number of consecutive rest days |
| `work_days_json` | JSON | NULL (weekly only) | Array of weekday names e.g. `["sunday","monday"]` |
| `weekend_days_json` | JSON | NULL (weekly only) | Array of weekend day names |
| `required_hours` | DECIMAL(6,2) | NULL (hours only), > 0 | Required hours per period |
| `period_type` | ENUM('daily', 'weekly', 'monthly') | NULL (hours only) | Period for hours calculation |
| `overtime_enabled` | BOOLEAN | DEFAULT FALSE | Enable overtime for this category |
| `fingerprint_enabled` | BOOLEAN | DEFAULT TRUE | Require fingerprint punches |
| `work_on_holidays` | BOOLEAN | DEFAULT FALSE | Work during official holidays |
| `work_on_weekends` | BOOLEAN | DEFAULT FALSE | Work during weekend days |
| `color` | VARCHAR(7) | NULL | Hex color for calendar display |
| `created_at` | TIMESTAMP | NOT NULL | Creation timestamp |
| `updated_at` | TIMESTAMP | NOT NULL | Last update timestamp |

**Indexes**:
- UNIQUE `idx_category_name_company` (`name`, `company_id`)
- INDEX `idx_category_type` (`type`)
- INDEX `idx_category_company` (`company_id`)

**Validation Rules** (from BR1):
- `name`: required, unique per company
- `type`: required, one of cyclic/weekly/hours
- If type=cyclic: `work_days` > 0 required, `rest_days` >= 0 required
- If type=weekly: `work_days_json` required, at least 1 day
- If type=hours: `required_hours` > 0 required, `period_type` required
- Hard delete only — cannot delete if employees are actively assigned (check `att_employee_shift_categories` where `end_date IS NULL`)

---

## Table: `att_time_schedules`

Time schedule definitions defining in/out times, breaks, and margins.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `company_id` | BIGINT UNSIGNED | FK → personnel_company.id, NOT NULL | Owning company |
| `name` | VARCHAR(100) | NOT NULL | Schedule name |
| `in_time` | TIME | NOT NULL | Clock-in time |
| `out_time` | TIME | NOT NULL | Clock-out time |
| `is_multi_day` | BOOLEAN | DEFAULT FALSE | Schedule spans multiple days |
| `late_margin` | SMALLINT UNSIGNED | DEFAULT 0 | Allowed late minutes |
| `early_margin` | SMALLINT UNSIGNED | DEFAULT 0 | Allowed early departure minutes |
| `created_at` | TIMESTAMP | NOT NULL | Creation timestamp |
| `updated_at` | TIMESTAMP | NOT NULL | Last update timestamp |

**Indexes**:
- UNIQUE `idx_schedule_name_company` (`name`, `company_id`)
- INDEX `idx_schedule_company` (`company_id`)

**Validation Rules** (from BR4):
- `name`: required, unique per company
- `in_time`, `out_time`: required (no in_time < out_time constraint — allows night/multi-day)
- `late_margin`, `early_margin`: >= 0
- Cannot delete if linked via `att_category_time_schedule`
- `is_multi_day` = true when schedule spans >24h (e.g., 3-day continuous airport shift)

---

## Table: `att_time_schedule_breaks`

Break periods within a time schedule.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `schedule_id` | BIGINT UNSIGNED | FK → att_time_schedules.id, NOT NULL | Parent schedule |
| `break_start` | TIME | NOT NULL | Break start time |
| `duration` | SMALLINT UNSIGNED | NOT NULL | Duration in minutes |
| `break_end` | TIME | NOT NULL | Break end time (computed) |
| `created_at` | TIMESTAMP | NOT NULL | Creation timestamp |
| `updated_at` | TIMESTAMP | NOT NULL | Last update timestamp |

**Indexes**:
- INDEX `idx_break_schedule` (`schedule_id`)

---

## Table: `att_category_time_schedule`

1:1 link between shift category and time schedule.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `shift_category_id` | BIGINT UNSIGNED | FK → att_shift_categories.id, UNIQUE, NOT NULL | Category |
| `time_schedule_id` | BIGINT UNSIGNED | FK → att_time_schedules.id, NOT NULL | Schedule |
| `created_at` | TIMESTAMP | NOT NULL | Creation timestamp |
| `updated_at` | TIMESTAMP | NOT NULL | Last update timestamp |

**Indexes**:
- UNIQUE `idx_link_category` (`shift_category_id`) — ensures 1:1

---

## Table: `att_employee_shift_categories`

Employee assignment to shift categories with historical snapshot.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `employee_id` | BIGINT UNSIGNED | FK → personnel_employee.id, NOT NULL | Employee |
| `shift_category_id` | BIGINT UNSIGNED | FK → att_shift_categories.id, NOT NULL | Category |
| `start_date` | DATE | NOT NULL | Assignment start date |
| `end_date` | DATE | NULL | Assignment end date (NULL = active) |
| `snapshot_data` | JSON | NOT NULL | Snapshot of category + schedule at assignment time |
| `created_at` | TIMESTAMP | NOT NULL | Creation timestamp |
| `updated_at` | TIMESTAMP | NOT NULL | Last update timestamp |

**Indexes**:
- INDEX `idx_assignment_employee` (`employee_id`)
- INDEX `idx_assignment_category` (`shift_category_id`)
- INDEX `idx_assignment_dates` (`start_date`, `end_date`)
- INDEX `idx_assignment_active` (`employee_id`, `end_date`) — for quick "is currently assigned" lookups

**Validation Rules** (from BR2):
- `start_date`: required
- `end_date`: optional (NULL = indefinite)
- No overlapping assignments for same employee (unique constraint on `employee_id` where `end_date IS NULL` at application level)
- Employee must be active (`personnel_employee.status = 1 / is_active = true`)
- On new assignment, auto-close previous assignment: `end_date = new_start_date - 1 day`

**State Transitions**:
```
[No Assignment] ──assign──► [Active] (end_date = NULL)
[Active] ──transfer──► [Closed] (end_date set) + [Active] (new record)
[Active] ──unassign──► [Closed] (end_date set)
```

---

## Table: `att_hours_tracking`

Periodic tracking for hours-based categories.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Primary key |
| `employee_id` | BIGINT UNSIGNED | FK → personnel_employee.id, NOT NULL | Employee |
| `shift_category_id` | BIGINT UNSIGNED | FK → att_shift_categories.id, NOT NULL | Category (for reference) |
| `period_start` | DATE | NOT NULL | Period start date |
| `period_end` | DATE | NOT NULL | Period end date |
| `period_type` | ENUM('daily', 'weekly', 'monthly') | NOT NULL | Period type |
| `required_hours` | DECIMAL(6,2) | NOT NULL | Required hours for this period |
| `actual_hours` | DECIMAL(6,2) | DEFAULT 0 | Actual hours recorded |
| `surplus_hours` | DECIMAL(6,2) | DEFAULT 0 | Hours above requirement |
| `deficit_hours` | DECIMAL(6,2) | DEFAULT 0 | Hours below requirement |
| `status` | ENUM('on_track', 'deficit', 'surplus') | DEFAULT 'on_track' | Period status |
| `created_at` | TIMESTAMP | NOT NULL | Creation timestamp |
| `updated_at` | TIMESTAMP | NOT NULL | Last update timestamp |

**Indexes**:
- UNIQUE `idx_tracking_employee_period` (`employee_id`, `period_start`, `period_end`, `period_type`)
- INDEX `idx_tracking_employee` (`employee_id`)
- INDEX `idx_tracking_period` (`period_start`, `period_end`)

---

## Relationships Summary

| From | To | Type | FK Column |
|------|----|------|-----------|
| `att_shift_categories` | `personnel_company` | BelongsTo | `company_id` |
| `att_shift_categories` | `att_category_time_schedule` | HasOne | — |
| `att_shift_categories` | `att_employee_shift_categories` | HasMany | — |
| `att_time_schedules` | `personnel_company` | BelongsTo | `company_id` |
| `att_time_schedules` | `att_time_schedule_breaks` | HasMany | — |
| `att_time_schedules` | `att_category_time_schedule` | HasOne | — |
| `att_category_time_schedule` | `att_shift_categories` | BelongsTo | `shift_category_id` |
| `att_category_time_schedule` | `att_time_schedules` | BelongsTo | `time_schedule_id` |
| `att_employee_shift_categories` | `personnel_employee` | BelongsTo | `employee_id` |
| `att_employee_shift_categories` | `att_shift_categories` | BelongsTo | `shift_category_id` |
| `att_time_schedule_breaks` | `att_time_schedules` | BelongsTo | `schedule_id` |
| `att_hours_tracking` | `personnel_employee` | BelongsTo | `employee_id` |
| `att_hours_tracking` | `att_shift_categories` | BelongsTo | `shift_category_id` |
