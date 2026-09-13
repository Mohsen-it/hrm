# خطة تحسين UI-UX — التوثيق والتقدم
**المسار:** `specs/012-ui-ux-improvement/PROGRESS.md`
**المرجع الوحيد:** `mistral.ai/DESIGN.md` + `specs/003-mistral-design-system/spec.md`
**النطاق المعتمد:** الأولويات الحرجة أولاً | الوضع الليلي مؤجل | البداية: التوحيد البصري
**الحالة:** قيد التنفيذ — المرحلة 1

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
- [x] `ui/Button.vue` danger/success/warning: `hover:bg-red/green/amber-700` → `hover:brightness-90` (بدون توكنز جديدة)
- [x] `ui/IconButton.vue` info/success/warning/danger: `hover:text-*-700` → توكن `mistral-*` نفسه + `brightness-90`
- [x] `ui/ErrorSummary.vue`: `red-50/100/200/600/700/800/900` → `mistral-danger/bg`
- [x] `ui/AvatarWithPreview.vue`: `bg-white/95`, `ring-black/10` → `mistral-canvas/ink`
- [x] `Timeline.vue:32-41` → خريطة `mistral-success/info/warning/danger/status-overtime/status-vacation/primary/cream-deeper`
- [x] `Dashboard.vue:194-195` → `status-overtime/vacation`
- [x] `ManageAssignments.vue:164-174,470-477,593-600` → نفس الخريطة + `StatCard.vue:21` vacation → `status-vacation`
- [x] التحقق: grep صفر نتائج لـ `red/green/amber/blue-700` في `ui/*` وصفر `purple/cyan/emerald/pink/teal/violet-50` في `Pages/*`
### 1.3 سلوك Tabs + Sidebar
- [x] `ui/Tabs.vue`: النشط `bg-white/text-ink` → `bg-mistral-ink/text-white` حسب `pill-tab-active` + `focus-visible` لأزرار pill و underline
- [x] `layout/SidebarItem.vue:64`: `aria-label="notifications"` → `t('common.notifications')` (المفتاح موجود في `lang/ar+en/common.php:25`) + وسم `DEPRECATED` لصالح `navigation/*`

## المرحلة 2 — RTL والترجمة (لم تبدأ)
- `DataTable.vue:320,340,408,429` + `Toolbar:218,265,301` + `NavSidebar:73` → `start-0`
- `SearchInput:48`, `FormSearchableSelect:153`, `FormMultiSelect:227` → `ps/pe`
- `AppLayout:77-82` → `ms/me`
- `DailyReport` + `timeAgo` + `sourceLabel` + إشعار البصمة → مفاتيح ترجمة

## المرحلة 3 — إمكانية الوصول (لم تبدأ)
- فرز DataTable بالكيبورد + `Tabs aria-controls/tabpanel` + أسهم + `FormModal` Focus Trap + إصلاح `role=grid` + tooltip `focus-within` + قوائم Navbar (`Escape`/أسهم/`aria-live`)

## المرحلة 4 — الصفحات الحرجة (لم تبدأ)
- `FingerprintDevices/Create:271` + `Edit:261` + `Balances:273` + `FaceSyncDashboard:566` → `FormInput`
- `DailySummaries` + `FaceSyncDashboard` → `DataTable` + `EmptyState`

## المرحلة 5 — الحوكمة (لم تبدأ)
- حذف/إعادة توجيه `DESIGN.md` الجذري → `mistral.ai/DESIGN.md`
- تثبيت قرار `SunsetStripeBand` بارتفاع 3px إداري
- معيار: 95% مكونات مشتركة + صفر `pill buttons` خارج badges + صفر hard-coded tokens

## سجل التغييرات
| التاريخ | التغيير | الملفات |
|---|---|---|
| 2026-09-13 | إنشاء الخطة وبدء المرحلة 1 | `specs/012-ui-ux-improvement/PROGRESS.md` |
| 2026-09-13 | إكمال المرحلة 1: توحيد radius + ألوان mistral + Tabs + Sidebar | `Button.vue`, `FormInput.vue`, `IconButton.vue`, `ErrorSummary.vue`, `FormModal.vue`, `ConfirmDialog.vue`, `EmptyState.vue`, `AvatarWithPreview.vue`, `StatCard.vue`, `FormActions.vue`, `Tabs.vue`, `DataTable.vue`, `DataTableToolbar.vue`, `SidebarItem.vue`, `Timeline.vue`, `ManageAssignments.vue`, `Dashboard.vue`, `app.css` |
