# 007 — Aleppo Airport + Subordinations — تقسيم المهام (Tasks)

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-20
**الحالة:** جاهز للتنفيذ
**المواصفة:** [spec.md](./spec.md)
**الخطة:** [plan.md](./plan.md)

---

## ملخص المهام حسب القصة

| القصة | العنوان | الأولوية | عدد المهام | معيار الاختبار المستقل |
|------|---------|---------|-----------|-------------------------|
| Phase 1 | Setup (تهيئة المشروع) | — | 4 | `Modules/Subordinations/` skeleton + module مفعّل |
| Phase 2 | Foundational (migrations + model + repo + service + lang) | — | 8 | `migrate` ينجح + `Subordination::count() === 0` |
| **US1** | Seed Company + Branches | **P1** (MVP) | 6 | `migrate:fresh --seed` ينتج 1 شركة + 2 فرع |
| **US2** | Subordination Domain + User Form Integration | **P1** (MVP) | 18 | إضافة موظف + اختيار "مطار حلب" ينجح؛ تعديل تبعية ينجح |
| **US3** | Display in User Pages (Show + Index column) | **P2** | 5 | بطاقة Show تعرض اسم التبعية؛ DataTable يعرض عمود |
| **US4** | Filter by Subordination | **P3** | 2 | تطبيق filter يُعيد تحميل الجدول بنتائج مفلترة |
| **US5** | Subordinations CRUD UI + Sidebar | **P2** | 10 | `/subordinations` يعمل؛ CRUD كامل؛ sidebar link ظاهر |
| Phase 8 | Polish (توثيق + pint + تحقق) | — | 6 | `php artisan pint` نظيف + `quickstart.md` يجتاز |
| **المجموع** | | | **59** | |

---

## ترتيب التنفيذ (Story Completion Order)

```
Phase 1 (Setup) ──► Phase 2 (Foundational) ──► US1 ──► US2 ──┬──► US3 ──► US4
                                                         ├──► US5
                                                         ▼
                                                       Polish
```

- **US1 يعتمد على:** Phase 1 + Phase 2 (يحتاج `companies` و `branches` migrations الموجودة).
- **US2 يعتمد على:** US1 + Phase 2 (يحتاج migrations الجاهزة + Models).
- **US3 يعتمد على:** US2 (يحتاج `subordination_id` مخزّن + Resource).
- **US4 يعتمد على:** US3 (يحتاج عرض في Index أولاً).
- **US5 يعتمد على:** Phase 2 فقط (CRUD UI مستقل — يمكن بناؤه بالتوازي مع US2/US3).
- **MVP = US1 + US2 فقط** (تغطية كاملة لـ 5 من 8 user stories في spec.md).

---

## Phase 1: Setup (تهيئة المشروع)

> لا تصنيف قصة. هذه مهام بنية تحتية.

- [X] T001 Create Subordinations module skeleton with `Modules/Subordinations/composer.json` and `Modules/Subordinations/module.json`
- [X] T002 Enable Subordinations module by adding `"Subordinations": true` to `modules_statuses.json`
- [X] T003 Create Subordinations app entry stubs at `Modules/Subordinations/app/Providers/SubordinationsServiceProvider.php` and `Modules/Subordinations/app/Providers/RouteServiceProvider.php`
- [X] T004 Create Subordinations config file at `Modules/Subordinations/config/config.php`

---

## Phase 2: Foundational (الأساس — يجب أن تكتمل قبل أي US)

> لا تصنيف قصة. هذه مهام حجب.

- [X] T005 [P] Create migration `Modules/Subordinations/database/migrations/2026_07_20_100000_create_subordinations_table.php` with the schema from `data-model.md § 2`
- [X] T006 [P] Create migration `database/migrations/2026_07_20_100100_add_subordination_id_to_users_table.php` with FK + index from `data-model.md § 3`
- [X] T007 Run `php artisan migrate` and verify `subordinations` table exists + `users.subordination_id` column added
- [X] T008 Create `Subordination` Eloquent model at `Modules/Subordinations/app/Models/Subordination.php` with HasFactory + SoftDeletes + `scopeActive` + `scopeOrdered` + `getDisplayNameAttribute` + `findByCode` static method
- [X] T009 Create `SubordinationRepository` at `Modules/Subordinations/app/Repositories/SubordinationRepository.php` with `query`, `getAll`, `findById`, `findByCode`, `getActive`, `create`, `update`, `delete`, `applyFilters`
- [X] T010 Create `SubordinationService` at `Modules/Subordinations/app/Services/SubordinationService.php` with DI for `SubordinationRepository` and 8 public methods (CRUD + getActive + getByCode + validateSubordinationData)
- [X] T011 [P] Create Arabic lang file at `Modules/Subordinations/lang/ar/subordinations.php` with 30+ keys from `contracts/http-and-ui.md § 5`
- [X] T012 [P] Create English lang file at `Modules/Subordinations/lang/en/subordinations.php` (mirror of Arabic)

---

## Phase 3: User Story 1 (P1) — Seed Company + Branches

> **القصة:** كمدير نظام، أرى شركة مطار حلب الدولي في قائمة الشركات، وأرى فرعي الطيران المدني والخطوط الجوية السورية مربوطين بها.
> **معيار الاختبار المستقل:** `php artisan migrate:fresh --seed` ينتج 1 سجل في `companies` و 2 سجل في `branches` (مرتبطان بالشركة).

- [X] T013 [US1] Modify `Modules/Companies/database/seeders/CompaniesDatabaseSeeder.php` to use `Company::updateOrCreate(['company_code' => 'AIRPORT-ALEPPO'], [...])` per `data-model.md § 11.3`
- [X] T014 [US1] Modify `Modules/Branches/database/seeders/BranchesDatabaseSeeder.php` to insert CIVIL-AVIATION + SYRIAN-AIR linked to the airport company, with defensive check for missing company per `data-model.md § 11.4`
- [X] T015 [US1] Update `database/seeders/DatabaseSeeder.php` to call Companies seeder FIRST, then Branches seeder, in `$this->call([...])`
- [X] T016 [US1] Run `php artisan migrate:fresh --seed` and verify counts via tinker: `Company::count() === 1`, `Branch::count() === 2`
- [X] T017 [US1] Run each seeder twice (idempotency) and confirm counts stay 1 and 2 respectively
- [X] T018 [US1] Verify out-of-order safety: `migrate:fresh` then run Branches seeder before Companies seeder → must log warning, not throw

---

## Phase 4: User Story 2 (P1) — Subordination Domain + User Form Integration

> **القصة:** كمدير نظام، أرى سجلَي التبعية "مطار حلب" و"مطار اللاذقية" في قائمة منسدلة، أستطيع اختيار تبعية لموظف جديد أو تعديلها لموظف موجود.
> **معيار الاختبار المستقل:** إنشاء موظف مع `subordination_id = 1` ينجح ويظهر في DB؛ تعديل `subordination_id` لموظف موجود ينجح.

- [X] T019 [P] [US2] Create `Modules/Subordinations/database/seeders/SubordinationsDatabaseSeeder.php` with two `Subordination::updateOrCreate(...)` calls per `data-model.md § 11.2`
- [X] T020 [P] [US2] Add `Modules\Subordinations\Services\SubordinationService` to the root `DatabaseSeeder.php` call list (after Branches seeder)
- [X] T021 [P] [US2] Create `StoreSubordinationRequest` at `Modules/Subordinations/app/Http/Requests/StoreSubordinationRequest.php` with rules + authorize = `create-subordinations`
- [X] T022 [P] [US2] Create `UpdateSubordinationRequest` at `Modules/Subordinations/app/Http/Requests/UpdateSubordinationRequest.php` with `Rule::unique(...)->ignore(...)` + authorize = `edit-subordinations`
- [X] T023 [P] [US2] Create `SubordinationResource` at `Modules/Subordinations/app/Http/Resources/SubordinationResource.php` with the 10 fields from `contracts/http-and-ui.md § 2.1`
- [X] T024 [P] [US2] Create `SubordinationsController` at `Modules/Subordinations/app/Http/Controllers/SubordinationsController.php` with 7 actions (index/create/store/show/edit/update/destroy) + `$this->authorize()` per action
- [X] T025 [US2] Create `Modules/Subordinations/routes/web.php` with 7 resource-style routes + 4 permission middleware per `contracts/http-and-ui.md § 1.1`
- [X] T026 [US2] Update `Modules/Users/app/Models/User.php`: add `subordination_id` to `$fillable` + add `subordination(): BelongsTo<Subordination>` method
- [X] T027 [US2] Update `Modules/Users/app/Http/Resources/UserResource.php`: add `subordination_id` (scalar, always) + `subordination` (whenLoaded object) per `contracts/http-and-ui.md § 2.2`
- [X] T028 [US2] Update `Modules/Users/app/Http/Requests/StoreUserRequest.php`: add `'subordination_id' => ['nullable', 'integer', 'exists:subordinations,id']` + `subordination_id_exists` message
- [X] T029 [US2] Update `Modules/Users/app/Http/Requests/UpdateUserRequest.php`: add same `subordination_id` rule + message
- [X] T030 [US2] Update `Modules/Users/app/Repositories/UserRepository.php`: add `'subordination'` to `$defaultWith` + add `subordination_id` filter to `applyFilters`
- [X] T031 [US2] Update `Modules/Users/app/Http/Controllers/UsersController.php`: inject `SubordinationService` + add `subordinations` to `formOptions()` per `contracts/http-and-ui.md § 6.3`
- [X] T032 [P] [US2] Update `Modules/Users/lang/ar/users.php`: add `subordination`, `select_subordination`, `subordination_id_exists` keys
- [X] T033 [P] [US2] Update `Modules/Users/lang/en/users.php`: mirror the new keys in English
- [X] T034 [US2] Run `php artisan migrate:fresh --seed` and verify `Subordination::count() === 2` + run a tinker test creating a user with `subordination_id = 1` then updating to `subordination_id = 2`
- [X] T035 [US2] Verify validation: attempt to create user with `subordination_id = 99999` (non-existent) via tinker → must throw `ValidationException` with `subordination_id_exists` key
- [X] T036 [US2] Verify FK SET NULL: in tinker, delete a subordination with attached users → users' `subordination_id` must become `NULL` (not cascade delete)

---

## Phase 5: User Story 3 (P2) — Display in User Pages

> **القصة:** كمدير موارد بشرية، أرى تبعية الموظف ظاهرة في بطاقة عرض بياناته وفي DataTable الخاص بقائمة الموظفين.
> **معيار الاختبار المستقل:** `/users/{id}` يعرض اسم التبعية؛ `/users` يعرض عمود "التبعية" في DataTable.

- [X] T037 [P] [US3] Update `resources/js/Pages/Users/Create.vue`: add `subordinations` to `defineProps` + add `subordination_id: ''` to `form` reactive + add `<FormSelect>` for subordination inside the "organizational_info" FormSection per `contracts/http-and-ui.md § 6.3`
- [X] T038 [P] [US3] Update `resources/js/Pages/Users/Edit.vue`: same as T037 + initialize `subordination_id: props.user.subordination_id || ''` + add optional `watch` on `form.company_id` for auto-suggest when company is `AIRPORT-ALEPPO`
- [X] T039 [P] [US3] Update `resources/js/Pages/Users/Show.vue`: display `user.subordination?.name_ar` (or `—` if null) in the organizational_info section
- [X] T040 [US3] Update `resources/js/Pages/Users/Index.vue`: add a new column "التبordination" (key: `subordination`) to the DataTable columns array, format = `row.subordination?.name_ar ?? '—'`
- [X] T041 [US3] Run `npm run build` and verify no Vue/JS errors

---

## Phase 6: User Story 4 (P3) — Filter by Subordination

> **القصة:** كمدير نظام، أستطيع تصفية قائمة الموظفين حسب التبعية.
> **معيار الاختبار المستقل:** اختيار "مطار حلب" من filter يُعيد تحميل الجدول ويعرض فقط الموظفين المعيّنين لها.

- [X] T042 [US4] Update `Modules/Users/app/Http/Controllers/UsersController.php`: pass `subordinations` to `Users/Index` (lazy prop) and add `subordination_id` to the `filters` list (request + repository)
- [X] T043 [US4] Update `resources/js/Pages/Users/Index.vue`: add `subordinations` to `defineProps` + add a `<FormSelect>` filter above the DataTable that triggers `applyFilters()` on change

---

## Phase 7: User Story 5 (P2) — Subordinations CRUD UI + Sidebar

> **القصة:** كمدير نظام، أستطيع إدارة سجلات التبعية (إضافة/تعديل/حذف) من واجهة المستخدم.
> **معيار الاختبار المستقل:** `/subordinations` يعرض القائمة؛ `/subordinations/create` ينشئ سجل جديد؛ تعديل/حذف يعملان.

- [X] T044 [P] [US5] Create `resources/js/Pages/Subordinations/Index.vue` using `DataTable` + `PageHeader` + `SearchInput` + `Button` + `ConfirmDialog` + `EmptyState` + `Pagination` per `contracts/http-and-ui.md § 6.1`
- [X] T045 [P] [US5] Create `resources/js/Pages/Subordinations/Create.vue` using `FormInput` + `FormTextarea` + `FormSelect` + `FormSection` + `FormActions` + `ErrorSummary`
- [X] T046 [P] [US5] Create `resources/js/Pages/Subordinations/Edit.vue` (same as Create + pre-filled from `props.subordination` + `_method: 'PUT'`)
- [X] T047 [P] [US5] Create `resources/js/Pages/Subordinations/Show.vue` (display all fields + back button)
- [X] T048 [P] [US5] Update `resources/js/Components/layout/Sidebar.vue`: add new `<NavLink href="subordinations.index" :can="'view-subordinations'">التبعية</NavLink>` under the "الهيكل التنظيمي" group
- [X] T049 [US5] Create `Modules/Subordinations/database/seeders/SubordinationPermissionsSeeder.php` to create the 4 permissions + grant them to `super-admin` role (idempotent)
- [X] T050 [US5] Add `SubordinationPermissionsSeeder` to root `DatabaseSeeder.php` call list
- [X] T051 [US5] Run `php artisan migrate:fresh --seed` and verify all 4 permissions exist + super-admin has them
- [X] T052 [US5] Run `npm run build` and verify no Vue/JS errors
- [X] T053 [US5] Manual UI test: log in as super-admin → navigate to `/subordinations` → create a test subordination → edit it → delete it → verify success flashes

---

## Phase 8: Polish & Cross-Cutting Concerns

> لا تصنيف قصة. تحسينات شاملة.

- [X] T054 [P] Update `AGENTS.md`: add `Subordinations` module to the module list (Section "Modules" / 13 modules → 14 modules) with description "إدارة التبعية الإدارية/الجغرافية للموظفين"
- [X] T055 [P] Update `.specify/memory/constitution.md`: update § II.2.4 module count from 13 to 14 (or whatever the actual count is after this addition)
- [X] T056 Run `php artisan pint` to auto-format all modified/created PHP files; verify no errors
- [X] T057 Run `php artisan test` to confirm no existing tests break (no new tests required per `research.md § D-7.1`)
- [X] T058 Execute `quickstart.md` end-to-end on a clean DB; tick all 21 acceptance items in § 9
- [X] T059 Run performance smoke test: `curl -w "%{time_total}" /users` and `/subordinations` 3 times each — both must be < 300ms

---

## Parallel Execution Examples

### Example 1: Phase 2 Foundational (all parallelizable)

Multiple developers can work simultaneously on the foundational layer:

```bash
# Developer A:
- T005 [P] Create subordinations migration
- T008 Create Subordination Model
- T009 Create SubordinationRepository
- T010 Create SubordinationService

# Developer B (parallel — different files):
- T006 [P] Create users.subordination_id migration
- T011 [P] Create Arabic lang file
- T012 [P] Create English lang file

# Wait for all T005-T012 to complete before starting US1.
```

### Example 2: US2 Implementation (split across 2-3 developers)

```bash
# Developer A (Subordination side):
- T019 [P] SubordinationsDatabaseSeeder
- T020 [P] Update root DatabaseSeeder
- T021 [P] StoreSubordinationRequest
- T022 [P] UpdateSubordinationRequest
- T023 [P] SubordinationResource
- T024 [P] SubordinationsController
- T025 Subordinations routes

# Developer B (User side — depends on T008 from Phase 2 being done):
- T026 Update User Model
- T027 Update UserResource
- T028 Update StoreUserRequest
- T029 Update UpdateUserRequest
- T030 Update UserRepository
- T031 Update UsersController
- T032 [P] Update Users lang/ar
- T033 [P] Update Users lang/en

# Both developers wait for T034-T036 verification.
```

### Example 3: US3 + US5 (US5 is independent of US3)

```bash
# Developer A (User pages):
- T037 [P] Update Users/Create.vue
- T038 [P] Update Users/Edit.vue
- T039 [P] Update Users/Show.vue
- T040 Update Users/Index.vue (column only)
- T041 npm run build

# Developer B (Subordinations CRUD UI — fully independent):
- T044 [P] Subordinations/Index.vue
- T045 [P] Subordinations/Create.vue
- T046 [P] Subordinations/Edit.vue
- T047 [P] Subordinations/Show.vue
- T048 [P] Update Sidebar.vue
- T049 SubordinationPermissionsSeeder
- T050 Update root DatabaseSeeder
- T051 Run seed + verify
- T052 npm run build
- T053 Manual UI test
```

---

## MVP Scope (الحد الأدنى القابل للتشغيل)

**التعريف:** الشريحة الأولى التي تغطي **جميع** user stories في P1.

**المهام الـ MVP (28 مهمة):**
- Phase 1: T001–T004
- Phase 2: T005–T012
- Phase 3 (US1): T013–T018
- Phase 4 (US2): T019–T036
- (اختياري في MVP) Phase 5 (US3): T037–T041
- (اختياري في MVP) Phase 7 (US5): T044–T053 (مع Sidebar)

**ما يغطيه MVP:**
- ✅ شركة مطار حلب الدولي + فرعيها (الطلب الأصلي #1)
- ✅ إنشاء/تعديل/حذف سجلات التبعية (الطلب الأصلي #2)
- ✅ إضافة موظف مع تبعية (الطلب الأصلي #3)
- ✅ تعديل تبعية موظف (الطلب الأصلي #4)

**ما لا يغطيه MVP (يُضاف في v1.1):**
- ❌ عرض التبعية في DataTable (US3 P2)
- ❌ تصفية حسب التبعية (US4 P3)

**الجهد المقدر:** ~6 ساعات لتنفيذ 24 مهمة في MVP.

---

## Strategy: Incremental Delivery

| Sprint | المهام | المخرجات |
|--------|--------|---------|
| **Sprint 0 (Setup)** | T001–T012 | module skeleton + migrations + model + repo + service + lang |
| **Sprint 1 (MVP - US1 + US2)** | T013–T036 | seeders جاهزة + تكامل user form + CRUD Subordinations (بدون UI حتى) |
| **Sprint 2 (UI Polish)** | T037–T053 | كل واجهات Vue + Sidebar + الصلاحيات |
| **Sprint 3 (Quality)** | T054–T059 | توثيق + pint + اختبار + قياس أداء |

كل sprint ينتهي بـ commit + run `php artisan pint` + run `quickstart.md` validation للمهام المكتملة.

---

## التحقق من اكتمال المهام (Task Format Validation)

✅ **Format Checklist (مطبَّق على كل مهمة):**
- [x] تبدأ بـ `- [ ]` (markdown checkbox)
- [x] لها Task ID تسلسلي (T001–T059)
- [x] `[P]` فقط للمهام المتوازية (مهام لا تعتمد على مهام غير مكتملة)
- [x] `[US1]`–`[US5]` فقط لمهام phase الـ user stories
- [x] لا story label لمهام Phase 1 (Setup) و Phase 2 (Foundational) و Phase 8 (Polish)
- [x] كل مهمة تحتوي على مسار ملف أو command واضح
- [x] الوصف بـ action verb في البداية (Create, Update, Modify, Run, Verify, Add)

---

*عدد المهام: 59*
*تاريخ الإنشاء: 2026-07-20*
*الإصدار: 1.0.0*
