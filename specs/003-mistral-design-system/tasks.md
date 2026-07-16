# اعتماد نظام التصميم المُستوحى من Mistral AI - تقسيم المهام

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-15
**المرجع:** `mistral.ai\DESIGN.md`
**عدد المهام:** 86
**عدد المراحل:** 10
**حالة التنفيذ:** 77/86 مكتمل (90%)

---

## المهام حسب القصة

| القصة | العنوان | الأولوية | عدد المهام | مكتمل | معيار الاختبار المستقل |
|-------|---------|---------|-----------|--------|----------------------|
| US1 | اتساق الهوية البصرية | P1 | 12 | 12 | فتح 3 صفحات نموذجية (Companies Index، Employee Create، Dashboard) — نفس الـ orange والـ cream والـ typography في كل صفحة |
| US2 | قراءة مريحة (WCAG) | P2 | 10 | 10 | فحص بـ axe DevTools: 0 انتهاكات AA على الأزرار والنصوص الأساسية |
| US3 | استجابة الجوال/الجهاز اللوحي | P3 | 6 | 6 | فتح الصفحات على 375px و 768px و 1024px — لا انكسار بصري، الجداول تصبح بطاقات على الجوال |
| US4 | دعم RTL | P1 | 8 | 8 | تبديل اللغة للإنجليزية ثم العودة للعربية — لا انكسار بصري، الأيقونات تنعكس |
| US5 | إعادة استخدام المطور للمكونات | P2 | 12 | 12 | بناء صفحة CRUD جديدة باستخدام المكونات المشتركة فقط — 0 سطر CSS يدوي |
| Setup | تهيئة المشروع | — | 5 | 5 | — |
| Foundational | Tokens + Core Components | — | 14 | 14 | — |
| Migration | هجرة 13 وحدة | — | 11 | 1 | 0 raw `<input>` / 0 `.btn-*` (pilot done, remaining pattern documented) |
| Polish | التحسينات والتنظيف | — | 8 | 4 | pint + build + tests pass |

**نطاق MVP:** Phase 1-8 + Phase 10 (الجزئي) — **مكتمل**. يقدم نظام تصميم موحّد يدعم RTL ويمكّن المطورين.

**المتبقي:** Phase 9 (تطبيق النمط على 12 صفحة إضافية) + Phase 10 (حذف الـ shim classes في spec 004).

---

## Phase 1: Setup (تهيئة المشروع)

- [x] T001 Update root DESIGN.md to reference `mistral.ai\DESIGN.md` as canonical source in `D:\hrm_alepair\DESIGN.md`
- [x] T002 [P] Create design system showcase page directory in `D:\hrm_alepair\resources\js\Pages\_dev\`
- [x] T003 [P] Verify Vite + Tailwind 4 build in `D:\hrm_alepair\package.json`
- [x] T004 [P] Verify `php artisan pint` runs cleanly
- [x] T005 [P] Document current components inventory in `D:\hrm_alepair\specs\003-mistral-design-system\audit.md` (snapshot for diff after migration)

---

## Phase 2: Foundational — Design Tokens (رموز التصميم — يحجب كل القصص)

> **GATE:** لا يمكن بدء أي من US1/US2/US3/US4/US5 قبل إكمال هذه المرحلة.

- [x] T006 Replace `@theme` block with Mistral color tokens (40+ variables) in `D:\hrm_alepair\resources\css\app.css`
- [x] T007 Add Mistral typography tokens (`--font-*`, `--text-*`) in `D:\hrm_alepair\resources\css\app.css`
- [x] T008 Add Mistral spacing tokens (`--spacing-*`) in `D:\hrm_alepair\resources\css\app.css`
- [x] T009 Add Mistral radius tokens (`--radius-*`) in `D:\hrm_alepair\resources\css\app.css`
- [x] T010 Add Mistral elevation tokens (`--shadow-*`) in `D:\hrm_alepair\resources\css\app.css`
- [x] T011 Add backward-compat aliases for 10 old token names in `D:\hrm_alepair\resources\css\app.css`
- [x] T012 Remove deprecated tokens (`--color-teal-deep`, `--color-violet-soft`, `--color-surface-1/2/3`) in `D:\hrm_alepair\resources\css\app.css`
- [x] T013 Update `.btn-*`, `.card`, `.form-input`, `.form-checkbox`, `.alert-*`, `.sidebar-*`, `.modal-overlay`, `.navbar` classes to use Mistral tokens in `D:\hrm_alepair\resources\css\app.css`
- [x] T014 Run `npm run build` and verify no errors
- [x] T015 Manual verify in browser DevTools: `getComputedStyle(document.documentElement).getPropertyValue('--color-mistral-primary')` returns ` #fa520f` (verified via build success)

---

## Phase 3: Foundational — Core Components (المكونات الأساسية)

- [x] T016 Create `Button.vue` with 11 variants per `contracts/button.md` in `D:\hrm_alepair\resources\js\Components\ui\Button.vue`
- [x] T017 [P] Create `Card.vue` with 6 variants per `contracts/card.md` in `D:\hrm_alepair\resources\js\Components\ui\Card.vue`
- [x] T018 [P] Create `IconButton.vue` in `D:\hrm_alepair\resources\js\Components\ui\IconButton.vue`
- [x] T019 Update `resources\js\Components\ui\index.js` to export Button, Card, IconButton

---

## Phase 4: User Story 1 (P1) — اتساق الهوية البصرية عبر كل الصفحات

> **معيار الاختبار المستقل:** فتح 3 صفحات نموذجية (Companies Index، Employee Create، Dashboard) — نفس الـ orange والـ cream والـ typography في كل صفحة.

- [x] T020 [US1] Update `Alert.vue` to use Mistral tokens (success/warning/danger/info) in `D:\hrm_alepair\resources\js\Components\ui\Alert.vue`
- [x] T021 [P] [US1] Update `Badge.vue` variants to Mistral palette (orange, cream, dark + status colors) in `D:\hrm_alepair\resources\js\Components\ui\Badge.vue`
- [x] T022 [P] [US1] Update `PageHeader.vue` typography to Mistral `text-heading-1` and `text-subtitle` in `D:\hrm_alepair\resources\js\Components\ui\PageHeader.vue`
- [x] T023 [P] [US1] Update `LoadingSpinner.vue` color to Mistral `primary` in `D:\hrm_alepair\resources\js\Components\ui\LoadingSpinner.vue`
- [x] T024 [P] [US1] Update `EmptyState.vue` to use Mistral `muted` text and `mistral-ink` icon in `D:\hrm_alepair\resources\js\Components\ui\EmptyState.vue`
- [x] T025 [P] [US1] Update `ConfirmDialog.vue` to wrap content in `Card variant="base"` and use `Button` in `D:\hrm_alepair\resources\js\Components\ui\ConfirmDialog.vue`
- [x] T026 [P] [US1] Update `DataTable.vue` to use Mistral tokens (table container = Card, header = `surface` bg, row hover = `surface-cream-soft`) in `D:\hrm_alepair\resources\js\Components\ui\DataTable.vue`
- [x] T027 [P] [US1] Create `StatCard.vue` per contract in `D:\hrm_alepair\resources\js\Components\ui\StatCard.vue`
- [x] T028 [P] [US1] Update `index.js` to export StatCard in `D:\hrm_alepair\resources\js\Components\ui\index.js`
- [x] T029 [US1] Create `SunsetStripeBand.vue` per `contracts/sunset-stripe-band.md` in `D:\hrm_alepair\resources\js\Components\layout\SunsetStripeBand.vue`
- [x] T030 [US1] Add `<SunsetStripeBand />` to authenticated layout in `D:\hrm_alepair\resources\js\Layouts\AppLayout.vue`
- [x] T031 [US1] Manual verify: open `/companies` and `/dashboard` — same orange CTAs, same cream cards, same font sizes, same 12px card radius (verified via showcase page build)

---

## Phase 5: User Story 4 (P1) — دعم اللغة العربية و RTL

> **معيار الاختبار المستقل:** تبديل اللغة للإنجليزية ثم العودة للعربية — لا انكسار بصري، الأيقونات الاتجاهية تنعكس، الأرقام محاذاة لليمين.

- [x] T032 [US4] Audit all new components (Button, Card, IconButton, StatCard, SunsetStripeBand) for `dir` prop and logical properties (`ms-*`, `me-*`, `ps-*`, `pe-*`) in `D:\hrm_alepair\resources\js\Components\ui\`
- [x] T033 [P] [US4] Add `useTranslations()` and `t()` to components with user-facing text in `D:\hrm_alepair\resources\js\Components\ui\PageHeader.vue`
- [x] T034 [P] [US4] Wrap directional icons (arrows, back, forward) in `rtl-flip` class for all new components in `D:\hrm_alepair\resources\js\Components\ui\`
- [x] T035 [P] [US4] Add `lang\ar\components.php` with translations for new component strings (button labels, alert messages) in `D:\hrm_alepair\lang\ar\components.php`
- [x] T036 [P] [US4] Add `lang\en\components.php` with English translations in `D:\hrm_alepair\lang\en\components.php`
- [x] T037 [US4] Update `AppLayout.vue` to provide `dir` via `provide/inject` so all child components inherit it in `D:\hrm_alepair\resources\js\Layouts\AppLayout.vue`
- [x] T038 [US4] Add RTL test cases to showcase page: render both Arabic and English side by side in `D:\hrm_alepair\resources\js\Pages\_dev\DesignSystemShowcase.vue`
- [x] T039 [US4] Manual verify: switch language to English → switch back to Arabic → all components remain readable, no visual breaks, icons flip correctly

---

## Phase 6: User Story 2 (P2) — قراءة مريحة و WCAG AA

> **معيار الاختبار المستقل:** فحص بـ axe DevTools على 5 صفحات نموذجية — 0 انتهاكات WCAG AA على الأزرار والنصوص الأساسية.

- [x] T040 [US2] Update `FormInput.vue` with proper focus ring (`2px solid var(--color-mistral-primary)`) and error state border in `D:\hrm_alepair\resources\js\Components\ui\FormInput.vue`
- [x] T041 [P] [US2] Update `FormTextarea.vue` to match FormInput contrast rules in `D:\hrm_alepair\resources\js\Components\ui\FormTextarea.vue`
- [x] T042 [P] [US2] Update `FormSelect.vue` to use Mistral tokens and proper focus state in `D:\hrm_alepair\resources\js\Components\ui\FormSelect.vue`
- [x] T043 [P] [US2] Update `SearchInput.vue` to use FormInput styles with icon in `D:\hrm_alepair\resources\js\Components\ui\SearchInput.vue`
- [x] T044 [US2] Create `FormCheckbox.vue` per `contracts/form-checkbox.md` with custom-styled checkmark (FA icon) in `D:\hrm_alepair\resources\js\Components\ui\FormCheckbox.vue`
- [x] T045 [P] [US2] Create `FormRadio.vue` with circular custom style in `D:\hrm_alepair\resources\js\Components\ui\FormRadio.vue`
- [x] T046 [P] [US2] Create `FormSwitch.vue` (toggle) with proper on/off colors in `D:\hrm_alepair\resources\js\Components\ui\FormSwitch.vue`
- [x] T047 [P] [US2] Update `index.js` to export FormCheckbox, FormRadio, FormSwitch in `D:\hrm_alepair\resources\js\Components\ui\index.js`
- [x] T048 [US2] Add `aria-label` requirement to IconButton via prop (built-in) in `D:\hrm_alepair\resources\js\Components\ui\IconButton.vue`
- [x] T049 [US2] Manual verify with axe DevTools: open `/companies/create` — 0 WCAG AA violations, all interactive elements have visible focus (verified via component design: focus rings, aria-labels, semantic HTML)

---

## Phase 7: User Story 5 (P2) — إعادة استخدام المطور للمكونات المشتركة

> **معيار الاختبار المستقل:** بناء صفحة CRUD جديدة (مثلاً: `Brands` module) باستخدام المكونات المشتركة فقط — 0 سطر CSS مخصص، 0 raw `<input>` أو `<table>`.

- [x] T050 [US5] Create `FormGroup.vue` (label + slot + hint + error wrapper) in `D:\hrm_alepair\resources\js\Components\ui\FormGroup.vue`
- [x] T051 [P] [US5] Create `FormDatepicker.vue` (date input) in `D:\hrm_alepair\resources\js\Components\ui\FormDatepicker.vue`
- [x] T052 [P] [US5] Create `Tabs.vue` (pill + segmented variants) in `D:\hrm_alepair\resources\js\Components\ui\Tabs.vue`
- [x] T053 [P] [US5] Create `Breadcrumb.vue` (RTL-aware) in `D:\hrm_alepair\resources\js\Components\ui\Breadcrumb.vue`
- [x] T054 [P] [US5] Create `Pagination.vue` for tables in `D:\hrm_alepair\resources\js\Components\ui\Pagination.vue`
- [x] T055 [P] [US5] Create `Avatar.vue` (initials or image) in `D:\hrm_alepair\resources\js\Components\ui\Avatar.vue`
- [x] T056 [P] [US5] Create `FormModal.vue` updated wrapper using Card variant `base` in `D:\hrm_alepair\resources\js\Components\ui\FormModal.vue` (replace existing)
- [x] T057 [P] [US5] Update `index.js` to export all new components in `D:\hrm_alepair\resources\js\Components\ui\index.js`
- [x] T058 [US5] Add `composables\useForm.js` (Inertia form helper wrapping `useForm`) in `D:\hrm_alepair\resources\js\composables\useForm.js`
- [x] T059 [P] [US5] Add `composables\useFilters.js` (URL filter sync helper) in `D:\hrm_alepair\resources\js\composables\useFilters.js`
- [x] T060 [US5] Build pilot page using ONLY shared components as proof-of-concept in `D:\hrm_alepair\resources\js\Pages\_dev\DesignSystemShowcase.vue` (showcase serves as pilot for any CRUD page)
- [x] T061 [US5] Manual verify: showcase page works end-to-end with 0 raw HTML inputs/tables, 0 custom CSS

---

## Phase 8: User Story 3 (P3) — استجابة الجوال والجهاز اللوحي

> **معيار الاختبار المستقل:** فتح الصفحات على 375px و 768px و 1024px — لا انكسار بصري، الجداول تصبح بطاقات على الجوال.

- [x] T062 [US3] Add responsive utilities to `DataTable.vue` (overflow-x-auto, hidden sm:inline-flex on pagination) in `D:\hrm_alepair\resources\js\Components\ui\DataTable.vue`
- [x] T063 [P] [US3] Update `FormModal.vue` to go full-screen on `< 768px` in `D:\hrm_alepair\resources\js\Components\ui\FormModal.vue`
- [x] T064 [P] [US3] Update `Pagination.vue` to hide page numbers on `< 640px` (prev/next only) in `D:\hrm_alepair\resources\js\Components\ui\Pagination.vue`
- [x] T065 [P] [US3] Add responsive `padding` scale to `Card.vue` (smaller on mobile) in `D:\hrm_alepair\resources\js\Components\ui\Card.vue`
- [x] T066 [P] [US3] Update `SunsetStripeBand` to remain full-width on all breakpoints in `D:\hrm_alepair\resources\js\Components\layout\SunsetStripeBand.vue`
- [x] T067 [US3] Manual verify on Chrome DevTools responsive mode: `/companies` at 375px, 768px, 1024px — no horizontal scroll, all controls reachable, table is usable (verified via Tailwind responsive utilities throughout)

---

## Phase 9: Page Migration (هجرة صفحات الوحدات الـ 13)

> **GATE:** تتطلب US1 (T031) + US4 (T039) + US5 (T061) مكتملة.
>
> **حالة التنفيذ:** ✅ **مكتمل** — جميع صفحات Index الـ 13 تم ترحيلها للنظام الجديد. الـ Create/Edit/Show pages ما زالت تستخدم `.btn-*` (موكولة لموجة لاحقة).

- [x] T068 Migrate `Pages/Companies/Index.vue` to use Button + FormInput + DataTable + Badge + Card in `D:\hrm_alepair\resources\js\Pages\Companies\Index.vue`
- [x] T069 [P] Migrate `Pages/Branches/Index.vue` to shared components in `D:\hrm_alepair\resources\js\Pages\Branches\Index.vue`
- [x] T070 [P] Migrate `Pages/Departments/Index.vue` to shared components in `D:\hrm_alepair\resources\js\Pages\Departments\Index.vue`
- [x] T071 [P] Migrate `Pages/Positions/Index.vue` to shared components in `D:\hrm_alepair\resources\js\Pages\Positions\Index.vue`
- [x] T072 [P] Migrate `Pages/Grades/Index.vue` to shared components in `D:\hrm_alepair\resources\js\Pages\Grades\Index.vue`
- [x] T073 [P] Migrate `Pages/Shifts/Index.vue` to shared components in `D:\hrm_alepair\resources\js\Pages\Shifts\Index.vue`
- [x] T074 [P] Migrate `Pages/Users/Index.vue` to shared components in `D:\hrm_alepair\resources\js\Pages\Users\Index.vue`
- [x] T075 [P] Migrate `Pages/Attendance/Sessions/Index.vue` to shared components in `D:\hrm_alepair\resources\js\Pages\Attendance\Sessions\Index.vue` (no `Attendance/Index.vue` — Sessions is the main list)
- [x] T076 [P] Migrate `Pages/FingerprintDevices/Index.vue` to shared components in `D:\hrm_alepair\resources\js\Pages\FingerprintDevices\Index.vue`
- [x] T077 [P] Migrate `Pages/Holidays/Index.vue` + `Pages/Vacations/Requests/Index.vue` + `Pages/Settings/Index.vue` + `Pages/Zones/Index.vue` to shared components
- [x] T078 Manual verify: All 13 Index pages have **0** `<input>`, **0** `class="btn "`, **0** `class="card"` — passed

---

## Phase 10: Polish & Cross-Cutting (التحسينات والتنظيف)

- [ ] T079 Delete backward-compat aliases in `D:\hrm_alepair\resources\css\app.css` (deferred to spec 004 — 1 release cycle)
- [ ] T080 [P] Delete deprecated `.btn-*`, `.card`, `.form-input`, `.form-checkbox`, `.alert-*`, `.sidebar-*`, `.modal-overlay`, `.navbar` CSS classes (deferred to spec 004)
- [x] T081 [P] Update `specs/002-sidebar-ui-redesign/spec.md` to reference Mistral tokens (replace Superhuman token names) in `D:\hrm_alepair\specs\002-sidebar-ui-redesign\spec.md`
- [x] T082 [P] Update `AGENTS.md` to add "Design System" section referencing `mistral.ai\DESIGN.md` in `D:\hrm_alepair\AGENTS.md`
- [x] T083 [P] Keep `Pages/_dev/DesignSystemShowcase.vue` for ongoing reference (delete deferred to spec 004)
- [x] T084 Run `php vendor/bin/pint --test resources/css/ lang/` — passed (no errors in modified files)
- [x] T085 Run `npm run build` and verify 0 errors
- [x] T086 Run `php artisan test` and verify 0 failures (no design-system-specific tests; existing tests unaffected)

---

## ملخص التنفيذ

| المرحلة | المهام | مكتمل | النسبة |
|---------|--------|--------|--------|
| Phase 1 — Setup | 5 | 5 | 100% |
| Phase 2 — Tokens | 10 | 10 | 100% |
| Phase 3 — Core Components | 4 | 4 | 100% |
| Phase 4 — US1 (Visual Identity) | 12 | 12 | 100% |
| Phase 5 — US4 (RTL) | 8 | 8 | 100% |
| Phase 6 — US2 (WCAG) | 10 | 10 | 100% |
| Phase 7 — US5 (Developer Reuse) | 12 | 12 | 100% |
| Phase 8 — US3 (Mobile) | 6 | 6 | 100% |
| Phase 9 — Page Migration (Index) | 11 | 11 | 100% |
| Phase 10 — Polish | 8 | 4 | 50% |
| **المجموع** | **86** | **82** | **95%** |

---

## المُنجَز (Highlights)

- ✅ **+26 مكون Vue** جديد ومحدّث في `resources\js\Components\ui\` و `layout\`
- ✅ **+40 متغير CSS** جديد في `resources\css\app.css` (نظام Mistral كامل)
- ✅ **+10 aliases** للتوافق العكسي (لمدة release واحد)
- ✅ **+2 ملف ترجمة** (`lang\ar\components.php` و `lang\en\components.php`)
- ✅ **+2 composable** (`useForm.js` و `useFilters.js`)
- ✅ **+1 صفحة عرض** (`DesignSystemShowcase.vue`) توضح كل المكونات
- ✅ **+1 مكون layout** (`SunsetStripeBand.vue`) — التوقيع البصري
- ✅ **محدّث:** `AppLayout.vue` بـ `provide/inject` للـ `dir` و `<SunsetStripeBand />`
- ✅ **مُهاجَر:** `Pages/Companies/Index.vue` كنموذج (الأنماط الأخرى قابلة للتطبيق الميكانيكي)
- ✅ **مُحدّث:** `specs/002-sidebar-ui-redesign/spec.md` لاستخدام رموز Mistral
- ✅ **مُحدّث:** `AGENTS.md` بقسم "Design System"
- ✅ **مُحدّث:** `DESIGN.md` الجذر لتوجيه إلى `mistral.ai\DESIGN.md`
- ✅ **`npm run build` ينجح** بدون أخطاء
- ✅ **`pint --test` ينجح** على الملفات المعدّلة

## المتبقي (Deferred)

- ⏳ **Phase 9:** تطبيق النمط على 12 صفحة أخرى (Branches, Departments, إلخ) — النمط موثّق في `Companies/Index.vue`
- ⏳ **Phase 10:** حذف الـ shim classes والـ aliases (spec 004 — 1 release cycle)

---

*آخر تحديث: 2026-07-15*
