# Research & Technical Decisions: Shift Categories & Time Schedules

**Feature**: إدارة فئات النوبات وجداول الوقت
**Date**: 2026-07-15
**Status**: Complete

---

## Decision 1: Module Placement

**Decision**: Extend existing `Shifts` module (`Modules/Shifts`)

**Rationale**:
- The Shifts module already manages `att_attshift` and `att_shiftdetail`
- Shift categories are a higher-level grouping of shift concepts
- Time schedules complement existing shift structures
- Avoids module proliferation (constitution Article X: simplicity first)
- Employee assignment logic naturally extends existing `att_attschedule` pattern

**Alternatives considered**:
- New `ShiftCategories` module — rejected: too granular, Shifts already exists
- Place in `Attendance` module — rejected: Attendance is for processing/calculations, not management

---

## Decision 2: Database Table Design & Prefixes

**Decision**: New tables use `att_` prefix (matching attendance domain), stored in `Modules/Shifts/database/migrations/`

**Tables**:
| Table | Purpose | Prefix |
|-------|---------|--------|
| `att_shift_categories` | Shift category definitions | att_ |
| `att_time_schedules` | Time schedule definitions | att_ |
| `att_time_schedule_breaks` | Break periods within schedules | att_ |
| `att_employee_shift_categories` | Employee-category assignments | att_ |
| `att_category_time_schedule` | 1:1 category-to-schedule link | att_ |

**Rationale**: Constitution Article IV specifies `att_` prefix for attendance-related tables. Shift categories directly feed into attendance calculations.

---

## Decision 3: Cyclic Shift Calculation Algorithm

**Decision**: Use modular arithmetic on day difference from cycle start date

```
isWorkDay(date) = ((date - cycleStartDate) % (workDays + restDays)) < workDays
```

**Key properties**:
- Stateless — no need to pre-generate dates
- Infinite (runs forever across months/years)
- Handles leap years and month boundaries automatically through `Carbon` date library
- Cached per-employee calculation results (TTL: 12 hours per constitution)

**Rationale**: Simple, testable, no state management. Same algorithm used in maritime/aviation shift schedulers.

---

## Decision 4: Multi-Day Continuous Shift Attendance

**Decision**: For cyclic patterns where employees stay multiple days (e.g., 3+9 pattern), the system:
1. Uses the `is_multi_day` flag on TimeSchedule to mark continuous schedules
2. Only requires punch IN on first work day and punch OUT on last work day
3. Intermediate work days are marked as "auto-attendance" (present by default)
4. Auto-attendance days can be manually overridden if an incident occurs

**Implementation approach**: The `AttendanceCalculationService` checks the category type. For cyclic multi-day:
- Group consecutive work days into "work blocks"
- First day of block: requires punch IN
- Last day of block: requires punch OUT
- Days between: auto-attended unless a punch event is recorded (e.g., for a break-out/break-in)

**Rationale**: Matches real-world airport operations where staff sleep on-premises during multi-day shifts.

---

## Decision 5: Historical Snapshot Strategy

**Decision**: Store a JSON snapshot of the category + schedule data in `att_employee_shift_categories.snapshot_data` at assignment time

**Snapshot contents**:
```json
{
  "category": {
    "id": 1,
    "name": "دوام 3+9",
    "type": "cyclic",
    "work_days": 3,
    "rest_days": 9,
    "overtime_enabled": true,
    "fingerprint_enabled": true
  },
  "schedule": {
    "id": 1,
    "name": "وردية A",
    "in_time": "07:00:00",
    "out_time": "07:00:00",
    "is_multi_day": true,
    "late_margin": 15,
    "early_margin": 15
  }
}
```

**Rationale**:
- Enables hard-delete of categories without breaking historical reports (per clarification Q3)
- Simpler and more reliable than maintaining historical versions of category data
- JSON column type in MySQL 8.0+ supports querying if needed

---

## Decision 6: Permission Integration

**Decision**: Register 10 new permissions through Spatie, following existing pattern

**Permission format**: `{action}-{resource}` per constitution Article V

Permissions:
- `view-shift-categories`, `create-shift-categories`, `edit-shift-categories`, `delete-shift-categories`
- `view-time-schedules`, `create-time-schedules`, `edit-time-schedules`, `delete-time-schedules`
- `assign-employees-to-category`, `view-attendance-by-schedule`

Register in `Modules/Shifts/config/permissions.php` and merge with main `config/permissions.php`.

---

## Decision 7: Frontend Architecture

**Decision**: Vue 3 SPA pages under `Modules/Shifts/Resources/js/Pages/` using shared components per constitution Article VII

**Pages**:
| Page | Route | Components |
|------|-------|------------|
| `ShiftCategories/Index.vue` | `/shift-categories` | DataTable, PageHeader, ConfirmDialog |
| `ShiftCategories/Create.vue` | `/shift-categories/create` | FormModal, FormInput, FormSelect |
| `ShiftCategories/Edit.vue` | `/shift-categories/{id}/edit` | (same as Create, reuse partial) |
| `TimeSchedules/Index.vue` | `/time-schedules` | DataTable, PageHeader, ConfirmDialog |
| `TimeSchedules/Create.vue` | `/time-schedules/create` | FormModal, FormInput (time), FormSelect |
| `TimeSchedules/Edit.vue` | `/time-schedules/{id}/edit` | (same as Create, reuse partial) |
| `Assignments/Index.vue` | `/shift-assignments` | DataTable, FormModal for bulk assign |
| `Calendar/Employee.vue` | `/schedule-calendar/{employee}` | Custom calendar grid |

**Shared partials**:
- `Partials/ShiftCategoryForm.vue` — reused in Create + Edit
- `Partials/TimeScheduleForm.vue` — reused in Create + Edit
- `Partials/CyclicDaysDisplay.vue` — visualizes work/rest pattern

**Rationale**: Follows constitution Partials pattern (Article XIV.2.3).

---

## Decision 8: Caching Strategy

**Decision**: Cache shift category cycle calculations with 12-hour TTL

| Data | TTL | Invalidation Trigger |
|------|-----|---------------------|
| Employee work-day calculation | 12 hours | Category change, reassignment |
| Category definitions | 1 hour | Category create/update/delete |
| Time schedule definitions | 1 hour | Schedule create/update/delete |
| Today's expected employees | 5 minutes | New assignment, date change |

Cache tags: `['shift_categories', 'time_schedules', 'employee_assignments']`

**Rationale**: Per constitution Article VI.1.3. Work-day calculations are expensive (loop over date range × number of employees) and change infrequently.

---

## Decision 9: Weekly Fixed Days Storage

**Decision**: Store weekly work days and weekend days as JSON columns in `att_shift_categories`

```json
// work_days_json: ["sunday", "monday", "tuesday", "wednesday", "thursday"]
// weekend_days_json: ["friday", "saturday"]
```

**Rationale**:
- MySQL 8.0+ has native JSON support with query functions
- Avoids separate day-table or bitmask (which has poor readability)
- Easy to validate and display in Vue forms

---

## Decision 10: Hours-based Deficit Reporting

**Decision**: Calculate deficit/surplus at period end. Store period results in a dedicated `att_hours_tracking` table

**Table**: `att_hours_tracking`
- employee_id, period_start, period_end, period_type (daily/weekly/monthly)
- required_hours, actual_hours, surplus_hours, deficit_hours
- status (on_track, deficit, surplus)

**Rationale**:
- Enables historical trend analysis
- Separates tracking from assignment for clean data model
- Period results are immutable once calculated (prevents recalculation errors)
