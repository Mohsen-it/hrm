# خطة تحسين UI-UX — التوثيق والتقدم
**المسار:** `specs/012-ui-ux-improvement/PROGRESS.md`
**المرجع الوحيد:** `mistral.ai/DESIGN.md` + `specs/003-mistral-design-system/spec.md`
**النطاق المعتمد:** الأولويات الحرجة أولاً | الوضع الليلي مؤجل | البداية: التوحيد البصري
**الحالة:** مكتملة — المراحل 1–5 (2026-09-13)

## المرحلة 0 — خط الأساس ✅ مكتمل (فحص read-only)
- [x] فحص `DESIGN.md` + `app.css` + `ui/` + `layout/` + `151` صفحة + `AppLayout.vue`
- [x] توثيق 7 محاور مخالفات بمسارات وأسطر دقيقة (انظر القسم 2 في الرد السابق)

## المرحلة 1 — التوحيد البصري ✅ مكتمل (2026-09-13)
### 1.1 توحيد radius (القاعدة: أزرار/حقول `rounded-md=8px`، بطاقات/مودالات `rounded-lg=12px`، شارات/pilltabs `rounded-full`)
- [x] `ui/Button.vue:131` `rounded-lg` → `rounded-md`
- [x] `ui/FormInput.vue:67` + `app.css:380 (.form-input)` `rounded-lg` → `rounded-md`
- [x] `ui/FormModal.vue:93` + `ui/ConfirmDialog.vue:88` `rounded-2xl/t-3xl` → `rounded-lg`
- [x] `ui/Tabs.vue:33,44` حاوية pill `rounded-xl` → `rounded-full` + زر `rounded-lg` → `rounded-full`
- [x] `ui/EmptyState.vue:12` أيقونة `rounded-2xl` → `rounded-md` + حشو `py-16` → `py-10`
- [x] `ui/AvatarWithPreview.vue:124,132` `rounded-2xl/xl` → `rounded-lg/md`
- [x] `app.css`: `.card/.card-cream/.card-feature-product` → `radius-lg`، `.btn/.btn-icon/.form-input` → `radius-md`
- [x] إضافي: `ui/StatCard.vue:38` → `bg-mistral-canvas + rounded-lg`، `ui/DataTable.vue:370,372` → `py-10 + rounded-md`، `DataTableToolbar.vue:217,264,300` → `bg-mistral-canvas + rounded-lg`، `ui/FormActions.vue:40` → `bg-mistral-canvas/95`

### 1.2 توحيد الألوان على `mistral-*` (صفر ألوان خام)
- [x] `ui/Button.vue` danger/success/warning: `hover:bg-red/green/amber-700` → `hover:brightness-90`
- [x] `ui/IconButton.vue` info/success/warning/danger: `hover:text-*-700` → توكن `mistral-*` نفسه + `brightness-90`
- [x] `ui/ErrorSummary.vue`: `red-50/100/200/600/700/800/900` → `mistral-danger/bg`
- [x] `ui/AvatarWithPreview.vue`: `bg-white/95`, `ring-black/10` → `mistral-canvas/ink`
- [x] `Timeline.vue:32-41` → خريطة `mistral-success/info/warning/danger/status-overtime/status-vacation/primary/cream-deeper`
- [x] `Dashboard.vue:194-195` → `status-overtime/vacation`
- [x] `ManageAssignments.vue:164-174,470-477,593-600` → نفس الخريطة + `StatCard.vue:21` vacation → `status-vacation`
- [x] `AttendanceHeatmap.vue:25-28` → `bg-mistral-danger/success/warning/10` + `text-mistral-*`
- [x] `DashboardWidget.vue:30-31` → `bg-mistral-info/cream-deeper text-mistral-info/primary`
- [x] `Pagination.vue` → `hover:bg-mistral-canvas` (11 استبدال)
- [x] التحقق: grep صفر نتائج لـ `red/green/amber/blue-700` في `ui/*` وصفر `bg-red-*` / `bg-green-*` / `bg-amber-*` / `bg-emerald-*` / `bg-cyan-*` / `bg-purple-*` في المكونات

### 1.3 سلوك Tabs + Sidebar
- [x] `ui/Tabs.vue`: النشط `bg-white/text-ink` → `bg-mistral-ink/text-white` حسب `pill-tab-active` + `focus-visible` لأزرار pill و underline
- [x] `layout/SidebarItem.vue:64`: `aria-label="notifications"` → `t('common.notifications')` + وسم `DEPRECATED`

## المرحلة 2 — RTL والترجمة ✅ مكتمل (2026-09-13)
### 2.1 خصائص منطقية (RTL logical properties)
- [x] `DataTable.vue:320,340,408,429` → `sticky start-0` (4 مircases)
- [x] `DataTableToolbar.vue:218,265,301` → `start-0` (3 قوائم منسدلة) + بحث أيقونة/حشو `start-3/ps-9/pe-8/end-2`
- [x] `NavSidebar.vue:73` → `start-0` (إصلاح `isRtl ? 'right-0' : 'left-0'`)
- [x] `SearchInput.vue:48` → `start-3/ps-9/pe-8/end-2` + `bg-mistral-canvas` + `rounded-md`
- [x] `FormSearchableSelect.vue:153` → `start-3/end-2`
- [x] `FormMultiSelect.vue:227` → `start-3/end-2` + `bg-mistral-canvas`
- [x] `FormDatepicker.vue` → `start-3` + `bg-mistral-canvas` + `rounded-md`
- [x] `FormTextarea.vue` → `start-3` + `bg-mistral-canvas` + `rounded-md`
- [x] `FormSelect.vue` → `bg-mistral-canvas` + `rounded-md`
- [x] `Breadcrumb.vue` → `useTranslations` + `aria-label="t('common.navigation')"`
- [x] `LanguageSwitcher.vue` → `end-0` + `bg-mistral-canvas` + `rounded-lg`
- [x] `AppLayout.vue:77-82` → `ms-[68px/268px]` (تبسيط RTL/LTR)

### 2.2 توحيد ألوان إضافي
- [x] `DataTableToolbar.vue` → `bg-mistral-canvas` (5 مواقع) + `rounded-md` (كثافة)
- [x] `SearchInput.vue` → `bg-mistral-canvas` + `text-mistral-steel`
- [x] `FormMultiSelect.vue` → `bg-mistral-canvas`
- [x] `FormDatepicker.vue` → `bg-mistral-canvas`
- [x] `FormTextarea.vue` → `bg-mistral-canvas`
- [x] `FormSelect.vue` → `bg-mistral-canvas`
- [x] `FormInput.vue` → `bg-mistral-canvas`
- [x] `DashboardWidget.vue:39` → `bg-white` (بطاقة أساسية — صحيح)

### 2.3 ترجمة النصوص الصلبة
- [x] `Dashboard.vue timeAgo()` → `t('dashboard.time_ago_just/minutes/hours/days')` (4 مفاتيح جديدة في ar+en)
- [x] `AppLayout.vue:193-206` → `t('dashboard.live_punch_*')` (4 مفاتيح جديدة في ar+en)
- [x] `DataTable.vue:339` → `t('common.error_occurred')` (مفتاح جديد في ar+en)
- [x] `Attendance/Live/Index.vue:89-97` → `t('attendance.source.*')` (6 مفاتيح + device_push جديد)
- [x] `Attendance/DailyReport/Index.vue` → ترجمة كاملة: عنوان + وصف + حقول + أعمدة + إحصائيات + ملاحظات (38 مفتاح جديد في ar+en attendance.daily_report)
- [x] `DataTableToolbar.vue` → 14 استبدال `dir==='rtl' ? '...'` → `t('common.*')` (14 مفتاح جديد في ar+en)
- [x] `components.php` → إضافة `first_page/last_page/page_number` (ar+en)

### 2.4 إصلاح aria-label بالكامل
- [x] `Alert.vue` → `t('components.dismiss')`
- [x] `FormModal.vue` → `t('components.close')`
- [x] `LoadingSpinner.vue` → `t('components.loading')`
- [x] `Pagination.vue` → `t('components.pagination/first_page/previous/page_number/next/last_page')`
- [x] `Navbar.vue` → `t('components.breadcrumb')`
- [x] `FormMultiSelect.vue` → `t('common.remove_item')`
- [x] التحقق: grep صفر نتائج `aria-label="[^\"]*[^\x00-\x7F]"` في `ui/` + `layout/` + `navigation/`

## المرحلة 3 — إمكانية الوصول ✅ مكتملة (2026-09-13)
### 3.1 DataTable: فرز بالكيبورد + ARIA
- [x] رؤوس الفرز → `<button type="button">` حقيقية (Enter/Space أصليان) مع `aria-sort` على الـ `th` (`ascending/descending/none`)
- [x] `scope="col"` لكل الأعمدة + `aria-rowcount="total+1"` لمعالجة تناقض `role="grid"`
- [x] `aria-label` لـ checkbox التحديد الجماعي والفردي (`common.select_all` / `common.select_row`)

### 3.2 Tabs: نمط WAI-ARIA كامل
- [x] `useId()` → `tabs-list-{id}` / `tabpanel-{id}` + `aria-controls` + `aria-labelledby`
- [x] roving tabindex (النشط 0 والباقي −1) + أسهم ←/→ باتجاه RTL صحيح + Home/End

### 3.3 FormModal: Focus Trap
- [x] composable جديد `composables/useFocusTrap.js` (بدون تبعيات): احتجاز Tab/Shift+Tab، تركيز أول عنصر، استبعاد hidden/disabled/aria-hidden، استعادة التركيز عند الإغلاق وunmount
- [x] ربط `FormModal` بالـ trap (تأجيل nextTick لأن الـ panel خلف v-if/Teleport) + `aria-label` من `components.dialog` (مفتاح جديد)

### 3.4 Tooltip بالتركيز
- [x] `DataTable.vue` خلايا tooltip: إضافة `group-focus-within/tooltip:opacity-100` بجانب hover

### 3.5 Navbar: قوائم كاملة الوصول
- [x] `Escape` يغلق القائمة ويعيد التركيز لزر الفتح (عام + داخل القائمة)
- [x] تنقل بالأسهم ↑/↓ بين `[role=option]` داخل القوائم (Modules + Quick Actions)
- [x] منطقة `aria-live=polite` تعلن فتح القائمة (`common.menu_opened`) + `aria-controls` على المُشغلات
- [x] `formatTimeAgo()` → `dashboard.time_ago_*` (إزالة 4 نصوص صلبة) + زر `Print` → `common.print` (مفتاح جديد)

## المرحلة 4 — الصفحات الحرجة ✅ مكتملة (2026-09-13)
- [x] `FaceSyncDashboard.vue`: حقل البحث الخام → `SearchInput` (start/end منطقية + canvas تلقائي) + جداول `text-left` → `text-start` + `scope="col"`
- [x] `DailySummaries/Index.vue`: جداول المخالفات الثلاثة → `scope="col"` (كانت مستوفية tokens/text-start أصلاً)
- [x] `UserActivity/actionMeta.js`: `cyan/amber/purple-50/600` → `mistral-info/warning/primary` + `/10`
- [x] `Shifts/Rotations/Timeline.vue`: `gray/emerald/amber` → `mistral-surface/success/warning/muted`
- [x] `Navbar.vue`: `text-blue-500` → `text-mistral-info`
- [x] التحقق: grep صفر ألوان خام في `Components/` + `Pages/` (بوابة lint:tokens)

## المرحلة 5 — الحوكمة ✅ مكتملة (2026-09-13)
- [x] `DESIGN.md` الجذري → stub توجيه (رأس YAML + رابط `mistral.ai/DESIGN.md`) وحذف المتن التاريخي 0.9.0/0.1.0 (متاح في Git)
- [x] `AGENTS.md`: توثيق المرجع الوحيد + قاعدة صفر ألوان خام + قفل ارتفاع SunsetStripeBand
- [x] `app.css`: `--sunset-stripe-height: 3px` + تعليق GOVERNANCE (يُعدَّل فقط مع تحديث specs/012)
- [x] بوابة آلية `scripts/check-design-tokens.mjs` + `npm run lint:tokens`: ألوان خام + aria-label عربي صلب + `rounded-2xl/3xl` في ui/ — تُفشل البنية عند مخالفة

## سجل التغييرات
| التاريخ | التغيير | الملفات |
|---|---|---|
| 2026-09-13 | إنشاء الخطة وبدء المرحلة 1 | `specs/012-ui-ux-improvement/PROGRESS.md` |
| 2026-09-13 | إكمال المرحلة 1: توحيد radius + ألوان mistral + Tabs + Sidebar | `Button.vue`, `FormInput.vue`, `IconButton.vue`, `ErrorSummary.vue`, `FormModal.vue`, `ConfirmDialog.vue`, `EmptyState.vue`, `AvatarWithPreview.vue`, `StatCard.vue`, `FormActions.vue`, `Tabs.vue`, `DataTable.vue`, `DataTableToolbar.vue`, `SidebarItem.vue`, `Timeline.vue`, `ManageAssignments.vue`, `Dashboard.vue`, `app.css` |
| 2026-09-13 | إكمال المرحلة 2: RTL + logical properties + ترجمة + ألوان إضافية | `DataTable.vue`, `DataTableToolbar.vue`, `NavSidebar.vue`, `SearchInput.vue`, `FormSearchableSelect.vue`, `FormMultiSelect.vue`, `FormDatepicker.vue`, `FormTextarea.vue`, `FormSelect.vue`, `Breadcrumb.vue`, `LanguageSwitcher.vue`, `AppLayout.vue`, `Dashboard.vue`, `Alert.vue`, `FormModal.vue`, `LoadingSpinner.vue`, `Pagination.vue`, `Navbar.vue`, `AttendanceHeatmap.vue`, `DashboardWidget.vue`, `DailyReport/Index.vue`, `Live/Index.vue`, `lang/ar/common.php`, `lang/en/common.php`, `lang/ar/dashboard.php`, `lang/en/dashboard.php`, `lang/ar/components.php`, `lang/en/components.php`, `Modules/Attendance/lang/ar/attendance.php`, `Modules/Attendance/lang/en/attendance.php` |
| 2026-09-13 | إكمال المرحلة 3: a11y — فرز كيبورد + aria-sort + Tabs ARIA + Focus Trap + tooltip focus + قوائم Navbar | `DataTable.vue`, `Tabs.vue`, `FormModal.vue`, `Navbar.vue`, `composables/useFocusTrap.js` (جديد), `lang/{ar,en}/common.php`, `lang/{ar,en}/components.php` |
| 2026-09-13 | إكمال المرحلة 4: FaceSyncDashboard + DailySummaries + صفر ألوان خام | `FaceSyncDashboard.vue`, `DailySummaries/Index.vue`, `UserActivity/actionMeta.js`, `Shifts/Rotations/Timeline.vue`, `Navbar.vue` |
| 2026-09-13 | إكمال المرحلة 5: حوكمة — توجيه DESIGN.md + قفل SunsetStripeBand + بوابة lint:tokens | `DESIGN.md`, `AGENTS.md`, `app.css`, `scripts/check-design-tokens.mjs` (جديد), `package.json` |
