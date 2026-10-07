# خطة سهولة التعلّم والوضوح — التوثيق والتقدم
**المسار:** `specs/013-ux-learnability/PROGRESS.md`
**المرجع:** `mistral.ai/DESIGN.md` + `specs/003-mistral-design-system/spec.md` + `specs/012-ui-ux-improvement/PROGRESS.md`
**المبدأ:** Rotations هو نظام الدوريات الفعلي الوحيد في الواجهة؛ شاشات Shifts الأساسية خلفية ولا تظهر في القائمة.
**الحالة:** P0 مكتمل + P1 مكتمل (2026-09-21) — P2 والقياس النهائي متبقّيان

## 1. مؤشرات النجاح (KPIs)

| KPI | خط الأساس | بعد P0+P1 | المستهدف | طريقة القياس |
|---|---|---|---|---|
| `lint:tokens` (6 بوابات) | 3/6 خضراء (ثغرات: hex مبعثر، جداول يدوية، خصائص فيزيائية) | **6/6 خضراء** | 6/6 | `npm run lint:tokens` |
| الجداول اليدوية العامة في `Pages/` | 10 جداول | **0** (8 رُحّلت إلى `ReportTable` + 2 استثناء موثّق) | 0 | بوابة `manual-tables` |
| hex مبعثر خارج اللوحة المركزية | 33 موقعاً | **0** (`chartPalette.js` فقط) | 0 | بوابة `scattered-hex` |
| خصائص RTL فيزيائية | 17 موقعاً | **0** | 0 | بوابة `physical-rtl` |
| نصوص صلبة في شروحات الدوريات | 15 كتلة عربية صلبة + تسربان صينيان + فاصلتان fullwidth | **0** (مفاتيح `shifts.help_*` في ar+en) | 0 | grep + مراجعة |
| بطاقات Dashboard الافتراضية | 12 بطاقة دفعة واحدة | **6 + زر عرض المزيد** | ≤ 6 | عد البطاقات |
| TTFT (زمن أول مهمة لموظف جديد) | ~12-15 دقيقة (تقدير) | لم يُقس بعد | < 5 دقائق | سيناريو §4 |
| SUS | غير مقاس (~65 تقديراً) | لم يُقس بعد | ≥ 80 | استبيان §4 |

## 2. P0 — مكتمل (2026-09-21)

- [x] إصلاح تسرب صيني: `TimeSchedules/Create.vue` + `Rotations/Edit.vue` (+ فاصلتين fullwidth في `Create.vue` ×2)
- [x] لوحة ألوان رسوم مركزية `resources/js/utils/chartPalette.js` (1:1 مع توكنز `app.css`) + ترحيل `Dashboard.vue` و`DashboardChart.vue` و`UserActivity/Show.vue` و`Rotations/Show.vue` و`Timeline.vue` و`Vacations/Types/*` + CSS الطباعة في `Reports/User.vue` إلى `var(--color-mistral-*)`
- [x] إصلاح 17 بقعة RTL فيزيائية (`me-/ms-/start-/text-start` + `inset-x-0` + `dir="ltr"` لجزيرة JSON)
- [x] توضيح القائمة: مجموعة `shifts` أصبحت `menu.shifts_group` (الدوريات والجداول / Rotations & Schedules) في `navigation.js` + `lang/{ar,en}/menu.php`
- [x] توسيع `scripts/check-design-tokens.mjs` بثلاث بوابات: `scattered-hex` + `manual-tables` + `physical-rtl` (مع استثناءات موثقة للمصفوفات المرئية المتخصصة)

## 3. P1 — مكتمل (2026-09-21)

- [x] ترحيل 8 جداول يدوية إلى `ReportTable`: `Dashboard.vue` ×3 (أقسام/ورديات/مخالفات) + `DailySummaries/Index.vue` ×3 (مخالفات) + `Users/Index.vue` (سجل البصمات) + `Users/Show.vue` (الأرصدة)
- [x] تقسية استثناءين موثقين: تقويم شهر الدورية (`Rotations/Show.vue` + `scope="col"`) + مصفوفة الأرصدة التحريرية (`Vacations/Balances/Index.vue` + `scope` + `aria-label` مترجم + مفتاح `vacations.edit_entitled_for`)
- [x] مكوّن `ui/ContextHelp.vue` (قابل للطي، `aria-expanded`، عنوان افتراضي `common.what_is_this_section`) + تسجيله في `ui/index.js`
- [x] نقل 15 كتلة شرح إلى `shifts.help_*` (مفاتيح ar+en) في `TimeSchedules/Create+Edit` و`Rotations/Create+Edit` و`Partials/TimeScheduleForm.vue`
- [x] تخفيف Dashboard: 6 بطاقات افتراضياً + زر `common.show_more/show_less`
- [x] إصلاح تسمية صلبة: `DailySummaries` checkbox → `attendance.violations.recalc_missing_only`
- [x] التحقق: `npm run lint:tokens` أخضر (6/6) + `php -l` لكل ملفات اللغة + `npm run build` ناجح (841 وحدة)

## 4. القياس (متبقٍّ — P2 والنهائي)

### سيناريو المهام الخمس (يقاس بالساعة، بدون مساعدة)
1. إضافة موظف جديد 2. إنشاء طلب إجازة 3. إنشاء دورية + إسناد موظف 4. تسجيل جهاز بصمة 5. استخراج التقرير اليومي
- النجاح = إتمام المهمة بدون سؤال. المستهدف ≥ 90% (4.5/5).

### SUS (بعد السيناريو مباشرة)
- الاستبيان القياسي 10 أسئلة (Brooke). المستهدف ≥ 80.

## 5. P2 — متبقٍّ (مقترح تالٍ)
- [ ] جولة ترحيب (3 خطوات) + صفحة «ابدأ هنا» (5 مهام شائعة)
- [ ] `EmptyState` بزر إجراء في كل قائمة صفرية متبقية
- [ ] القياس النهائي (TTFT + SUS) وتحديث جدول §1

## سجل التغييرات
| التاريخ | التغيير | الملفات |
|---|---|---|
| 2026-09-21 | إنشاء الخطة وإكمال P0+P1 | `chartPalette.js` (جديد)، `ContextHelp.vue` (جديد)، `check-design-tokens.mjs`، `navigation.js`، `Dashboard.vue`، `DashboardChart.vue`، `DailySummaries/Index.vue`، `Users/Index.vue`، `Users/Show.vue`، `UserActivity/Show.vue`، `Rotations/{Show,Timeline,Create,Edit}.vue`، `TimeSchedules/Create+Edit.vue`، `TimeScheduleForm.vue`، `Vacations/{Types/*,Balances/Index}.vue`، `Reports/User.vue`، `FaceSyncDashboard.vue`، `Live/Index.vue`، `BulkAssign.vue`، `Sync.vue`، `My/Index.vue`، `NavSearch.vue`، lang: `shifts/ar+en` و`common/ar+en` و`menu/ar+en` و`attendance/ar+en` و`vacations/ar+en` |
