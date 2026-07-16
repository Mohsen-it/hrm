# نظام جدولة المناوبات والدوام الدوري - تقسيم المهام (محدث)
# Employee Shift Scheduling Engine - Task Breakdown (Updated)

**الإصدار:** 1.1.0
**التاريخ:** 2026-07-16
**الحالة:** محدث ليعكس البنية الموجودة

---

## ملاحظات التحديث

تم تحديث المهام لتعكس البنية الموجودة في وحدة Shifts:
- **ShiftCategory**EXISTING: يجمع بين ShiftPattern و DutyCategory
- **EmployeeShiftCategory**EXISTING: يربط الموظفين بالفئات
- **CyclicScheduleCalculator**EXISTING: يحسب الجداول الدورية
- **ShiftCategoriesController**EXISTING: يدير فئات الدوام
- **ShiftCategoryAssignmentController**EXISTING: يدير إسناد الموظفين

---

## Phase 1: Setup

- [x] T001 Create migration for schedule_periods table in Modules/Shifts/database/migrations/2026_07_16_000010_create_schedule_periods_table.php
- [x] T002 Create migration for schedule_entries table in Modules/Shifts/database/migrations/2026_07_16_000011_create_schedule_entries_table.php
- [x] T003 Create migration for audit_logs table in Modules/Shifts/database/migrations/2026_07_16_000012_create_audit_logs_table.php
- [x] T004 Run migrations with php artisan migrate

---

## Phase 2: Foundational

- [x] T005 Create SchedulePeriod model in Modules/Shifts/Models/SchedulePeriod.php
- [x] T006 Create ScheduleEntry model in Modules/Shifts/Models/ScheduleEntry.php
- [x] T007 Create AuditLog model in Modules/Shifts/Models/AuditLog.php
- [x] T008 Create SchedulePeriodRepository in Modules/Shifts/Repositories/SchedulePeriodRepository.php
- [x] T009 Create ScheduleEntryRepository in Modules/Shifts/Repositories/ScheduleEntryRepository.php

---

## Phase 3: User Story 1 - Extend Shift Category with Anchor Date (P1)

**Goal:** Admin can set independent cycle start date per shift category

**Independent Test:** Categories with different anchor dates calculate correct work/rest days

- [x] T010 [US1] Verify anchor_start_date field exists in att_shift_categories table
- [x] T011 [US1] Update ShiftCategory model to include anchor_start_date in fillable
- [x] T012 [US1] Update ShiftCategoryService to handle anchor_start_date validation
- [x] T013 [US1] Update ShiftCategoriesController to accept anchor_start_date
- [ ] T014 [US1] Add Arabic translations for anchor date in lang/ar/shifts.php
- [ ] T015 [US1] Add English translations for anchor date in lang/en/shifts.php

---

## Phase 4: User Story 2 - Schedule Generation Service (P1)

**Goal:** System can generate monthly schedules and store them

**Independent Test:** Schedule generated with correct work/rest days per category

- [x] T016 [US2] Create ScheduleGenerationService in Modules/Shifts/Services/ScheduleGenerationService.php
- [x] T017 [US2] Implement generateMonthlySchedule method
- [x] T018 [US2] Implement publishSchedule method
- [x] T019 [US2] Implement regenerateSchedule method
- [x] T020 [US2] Create GenerateScheduleRequest in Modules/Shifts/Http/Requests/GenerateScheduleRequest.php
- [x] T021 [US2] Create PublishScheduleRequest in Modules/Shifts/Http/Requests/PublishScheduleRequest.php
- [x] T022 [US2] Create SchedulesController in Modules/Shifts/Http/Controllers/SchedulesController.php
- [x] T023 [US2] Add schedule routes in Modules/Shifts/routes/web.php
- [x] T024 [US2] Add Arabic translations for schedules in lang/ar/shifts.php
- [x] T025 [US2] Add English translations for schedules in lang/en/shifts.php

---

## Phase 5: User Story 3 - Schedule Versioning (P1)

**Goal:** System supports schedule versioning and historical preservation

**Independent Test:** Regenerating a schedule creates new version without deleting old

- [x] T026 [US3] Implement version increment logic in ScheduleGenerationService
- [x] T027 [US3] Implement schedule comparison for versioning
- [x] T028 [US3] Add version display in schedule views
- [x] T029 [US3] Add Arabic translations for versioning in lang/ar/shifts.php
- [x] T030 [US3] Add English translations for versioning in lang/en/shifts.php

---

## Phase 6: User Story 4 - Schedule Calendar Views (P1)

**Goal:** Admin can view generated schedules in calendar format

**Independent Test:** Calendar displays correct work/rest days for each employee

- [x] T031 [US4] Create Schedules/Index.vue in resources/js/Pages/Shifts/Schedules/Index.vue
- [x] T032 [US4] Create Schedules/Show.vue in resources/js/Pages/Shifts/Schedules/Show.vue
- [x] T033 [US4] Update ScheduleCalendarController to use stored schedules
- [x] T034 [US4] Add Arabic translations for calendar views in lang/ar/shifts.php
- [x] T035 [US4] Add English translations for calendar views in lang/en/shifts.php

---

## Phase 7: User Story 5 - Audit Logging (P2)

**Goal:** System logs all critical scheduling operations

**Independent Test:** All CRUD operations create audit log entries

- [x] T036 [US5] Create AuditService in Modules/Shifts/Services/AuditService.php
- [x] T037 [US5] Add audit logging to ShiftCategoryService CRUD operations
- [x] T038 [US5] Add audit logging to ShiftCategoryAssignmentService operations
- [x] T039 [US5] Add audit logging to ScheduleGenerationService operations
- [x] T040 [US5] Add Arabic translations for audit in lang/ar/shifts.php
- [x] T041 [US5] Add English translations for audit in lang/en/shifts.php

---

## Phase 8: User Story 6 - Leave Integration (P2)

**Goal:** System calculates scheduled work days for leave requests

**Independent Test:** Leave request shows correct work/rest day counts

- [x] T042 [US6] Create LeaveCalculationService in Modules/Shifts/Services/LeaveCalculationService.php
- [x] T043 [US6] Integrate with Vacations module leave creation
- [x] T044 [US6] Add Arabic translations for leave integration in lang/ar/shifts.php
- [x] T045 [US6] Add English translations for leave integration in lang/en/shifts.php

---

## Phase 9: User Story 7 - Attendance Integration (P2)

**Goal:** Attendance system uses scheduling engine for expected work days

**Independent Test:** Absence only recorded on scheduled work days

- [x] T046 [US7] Update SmartAbsenceController to use stored schedules
- [x] T047 [US7] Integrate with Attendance module absence calculation
- [x] T048 [US7] Add Arabic translations for attendance integration in lang/ar/shifts.php
- [x] T049 [US7] Add English translations for attendance integration in lang/en/shifts.php

---

## Phase 10: User Story 8 - Employee Self-Service (P3)

**Goal:** Employee can view their own schedule and next work day

**Independent Test:** Employee sees correct personal schedule

- [x] T050 [US8] Update myCalendar route to use stored schedules
- [x] T051 [US8] Create employee self-service Vue page in resources/js/Pages/Shifts/Schedules/MySchedule.vue
- [x] T052 [US8] Add Arabic translations for self-service in lang/ar/shifts.php
- [x] T053 [US8] Add English translations for self-service in lang/en/shifts.php

---

## Phase 11: Polish & Cross-Cutting Concerns

- [x] T054 Run php artisan pint for code formatting
- [x] T055 Run php artisan test for unit tests
- [x] T056 Verify all permissions are correctly assigned
- [x] T057 Verify RTL support on all pages
- [x] T058 Verify Arabic translations are complete
- [x] T059 Verify English translations are complete

---

## Dependencies

### Story Completion Order

```
Phase 1 (Setup) → Phase 2 (Foundational) → Phase 3 (US1) → Phase 4 (US2) → Phase 5 (US3) → Phase 6 (US4) → Phase 7 (US5) → Phase 8 (US6) → Phase 9 (US7) → Phase 10 (US8) → Phase 11 (Polish)
```

### Parallel Execution Opportunities

**Within Phase 3 (US1):**
- T014, T015 can run in parallel (different translation files)

**Within Phase 4 (US2):**
- T024, T025 can run in parallel (different translation files)

**Within Phase 5 (US3):**
- T029, T030 can run in parallel (different translation files)

**Within Phase 6 (US4):**
- T034, T035 can run in parallel (different translation files)

**Cross-Story Parallel:**
- After Phase 6 (US4) completes, Phase 7 (US5), Phase 8 (US6), Phase 9 (US7) can run in parallel

---

## Implementation Strategy

### MVP Scope (Phase 1-6)
- Setup and foundational work
- Extend existing ShiftCategory with anchor date
- Schedule generation service
- Schedule versioning
- Schedule calendar views

### Incremental Delivery
1. **Sprint 1:** Phase 1-2 (Setup + Foundational)
2. **Sprint 2:** Phase 3-4 (US1 + US2)
3. **Sprint 3:** Phase 5-6 (US3 + US4)
4. **Sprint 4:** Phase 7-10 (US5-US8)
5. **Sprint 5:** Phase 11 (Polish)

---

## Summary

| Metric | Value |
|--------|-------|
| Total Tasks | 59 |
| Setup Tasks | 4 |
| Foundational Tasks | 5 |
| User Story Tasks | 44 |
| Polish Tasks | 6 |
| User Stories | 8 |
| Parallel Opportunities | 8 |

---

*آخر تحديث: 2026-07-16*
