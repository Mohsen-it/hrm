# اعتماد نظام التصميم المُستوحى من Mistral AI - خطة التنفيذ التقنية

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-15
**الفرع:** `003-mistral-design-system`
**المرجع البصري:** `mistral.ai\DESIGN.md`

---

## سياق تقني

### التقنيات المستخدمة

- **إطار العمل الأمامي:** Vue 3 (Composition API) + Inertia.js v3
- **التنسيق:** Tailwind CSS 4.3 (مع `@theme` directive لرموز التصميم)
- **البناء:** Vite 8
- **الأيقونات:** Font Awesome 6
- **إدارة الحالة:** Inertia shared props + Vue 3 reactivity
- **نظام التصميم المرجعي:** `mistral.ai\DESIGN.md` (مصدر وحيد للرموز)

### الحالة الراهنة للمكونات

- 13 مكون UI موجود في `resources\js\Components\ui\`: `Alert`, `Badge`, `ConfirmDialog`, `DataTable`, `EmptyState`, `FormInput`, `FormModal`, `FormSelect`, `FormTextarea`, `LoadingSpinner`, `PageHeader`, `SearchInput` + `index.js`.
- 4 مكونات layout: `Sidebar`, `SidebarGroup`, `SidebarItem` (متعلقة بـ spec 002) و `Navbar`.
- 14 مكون **مطلوب إنشاؤه**: `Button`, `Card`, `FormCheckbox`, `FormRadio`, `FormSwitch`, `FormGroup`, `Tabs`, `StatCard`, `SunsetStripeBand`, `Breadcrumb`, `Pagination`, `Avatar`, `IconButton`, `FormDatepicker`.
- الصفحات حالياً تستخدم فئات CSS خام (`.btn-primary`, `.card`, `.form-input`) بدلاً من مكونات Vue — انتهاك للمادة VII.2 من الدستور.

### نقاط مجهولة تم بحثها

1. **مكان المكونات الجديدة** — `Components/ui\` (يتبع البنية القائمة).
2. **تسمية الرموز** — إعادة تسمية للرموز باسم Mistral مع إبقاء الأسماء القديمة كـ aliases لجيل واحد.
3. **التعامل مع spec 002** — تحديث spec السايدبار ليرجع إلى رموز Mistral (في commit منفصل).
4. **توفر خط PP Editorial Old** — استخدام Georgia كـ fallback (مجاني، قريب بصرياً).
5. **Sunset Stripe Band** — تطبيقه على كل الصفحات (مصادق عليها وصفحات المصادقة).
6. **حالات Hover** — الإبقاء عليها (انحراف عن Mistral مقصود لإنتاجية HRM).
7. **CSS classes للأزرار** — استبدالها بمكون `<Button>` مع إبقاء `.btn` كـ shim لـ release واحد.
8. **Form Checkbox** — مخصص بـ wrapper + FA icon (بدلاً من native).
9. **التحقق من تباين WCAG** — خطوة CI بفحص تركيبات الألوان.
10. **ترتيب الهجرة** — 4 مراحل (Tokens → Components → Pages → Cleanup).

> تم حل جميع النقاط في `research.md`.

---

## فحص الدستور (Constitution Check)

### القواعد المطبقة

| المادة | القاعدة | التأثير على التصميم | الحالة |
|---|---|---|---|
| **VII.2** | استخدام المكونات المشتركة إلزامي | إنشاء `Button`, `Card`, `FormCheckbox` إلخ لسد الفجوات؛ استبدال `.btn-*` بـ `<Button>` | ✅ متوافق |
| **VII.3** | كل مكون جديد يجب أن يدعم RTL | كل المكونات الجديدة تقبل `dir` prop وتستخدم logical properties | ✅ متوافق |
| **VII.4** | دعم RTL افتراضياً + `useTranslations()` | المكونات تستخدم `t()` للترجمة، تنعكس الأيقونات بـ `rtl-flip` | ✅ متوافق |
| **VI.2.1** | Lazy Loading | المكونات الجديدة تُحمّل مع الـ chunk الخاص بها (Inertia lazy pages) | ✅ متوافق |
| **VI.2.2** | Component Memoization | استخدام `computed` للقيم المشتقة (مثل `meta` في `DataTable`) | ✅ متوافق |
| **VI.2.3** | Bundle Size | لا مكتبات جديدة؛ استخدام FA icons المُحمّلة بالفعل | ✅ متوافق |
| **VI.2.4** | Data Fetching | لا تغيير في جلب البيانات | ✅ متوافق |
| **X.1** | لا مكتبات غير ضرورية | لا حاجة لـ UI kit خارجية؛ النظام مبني على Tailwind + FA | ✅ متوافق |
| **X.3** | لا future-proofing | النظام يعتمد على 4 مراحل قابلة للنشر كل واحدة مستقلة | ✅ متوافق |
| **XIV.6** | Time to First Paint < 1.5s | إضافة ~30 متغير CSS لا تؤثر على وقت التحميل | ✅ متوافق |
| **XIV.2.2** | Composition API | كل المكونات الجديدة تستخدم `<script setup>` | ✅ متوافق |
| **XIV.3** | Scalability Rules | رموز مركزية → إضافة لون جديد بـ < 5 دقائق | ✅ متوافق |

### المخالفات المحتملة والمبررات

| المخالفة المحتملة | المادة | المبرر |
|-------------------|--------|--------|
| تأخير إزالة الفئات القديمة (`.btn-*`) إلى Phase 4 | VII.2 | مطلوب backward-compat لتجنب كسر 13 وحدة في commit واحد. الفئات ستحذف في spec 004 (التنظيف). |
| استخدام خط `Georgia` بدلاً من PP Editorial Old | VII.4 | PP Editorial Old مرخص. `Georgia` متاح افتراضياً ويحافظ على الطابع التحريري. |
| الإبقاء على hover states (Mistral لا يستخدمها) | VII.2 | HRM نظام إنتاجية؛ hover feedback ضروري لاكتشاف العناصر التفاعلية. |

---

## المرحلة 0: البحث

تم تنفيذها بالكامل في `research.md`.

### القرارات الرئيسية

| القرار | الوصف |
|--------|-------|
| **D-01** | المكونات الجديدة في `Components/ui\` و `layout\` (لا مجلد منفصل) |
| **D-02** | إعادة تسمية الرموز إلى `mistral-*` مع aliases للرموز القديمة لجيل واحد |
| **D-03** | spec 002 سيُعدّل ليستخدم رموز Mistral (commit منفصل) |
| **D-04** | استخدام `Georgia` كـ fallback لـ PP Editorial Old |
| **D-05** | Sunset Stripe Band على كل الصفحات (مصادقة + نظام) |
| **D-06** | الإبقاء على hover states (انحراف مقصود) |
| **D-07** | استبدال `.btn-*` بـ `<Button>` مع إبقاء `.btn` كـ shim |
| **D-08** | Form Checkbox مخصص بـ wrapper + FA icon |
| **D-09** | CI contrast lint لتركيبات الألوان عالية الاستخدام |
| **D-10** | هجرة 4 مراحل: Tokens → Components → Pages → Cleanup |

---

## المرحلة 1: التصميم والعقود

### نموذج البيانات

تم توثيقه بالكامل في `data-model.md` ويغطي:
- **9 كيانات:** Color Tokens, Typography Tokens, Spacing Tokens, Radius Tokens, Elevation Tokens, Component Catalog, Button Variants, Page Type Templates, State Transitions.
- **40+ متغير CSS** للرموز.
- **17 مكون Vue** (13 موجودة + 14 جديدة).
- **11 variant للأزرار** (primary, secondary, cream, dark, on-cream, link, danger, ghost, icon, primary-pressed, primary-disabled).

### عقود المكونات

تم توثيقها في `contracts/`:
- [`README.md`](./contracts/README.md) — فهرس + اتفاقية موحدة.
- [`button.md`](./contracts/button.md) — `Button` (الأكثر تفصيلاً، يستخدم كقالب).
- [`card.md`](./contracts/card.md) — `Card` بـ 6 variants.
- [`form-checkbox.md`](./contracts/form-checkbox.md) — `FormCheckbox` (custom-styled).
- [`sunset-stripe-band.md`](./contracts/sunset-stripe-band.md) — `SunsetStripeBand` (التوقيع البصري).
- 22 عقد إضافي (مختصر) للمكونات الأخرى.

### دليل التحقق

تم توثيقه بالكامل في `quickstart.md` ويغطي:
- متطلبات النظام.
- اختبارات يدوية لكل مرحلة.
- استكشاف الأخطاء وإصلاحها.
- اختبارات نهاية لنهاية (وظيفية، بصرية، وصول، أداء).

---

## مراحل التنفيذ (الترتيب)

### Phase P1: Tokens (يوم عمل واحد)

1. تحديث `resources\css\app.css` بـ `@theme` block جديد يحتوي:
   - كل متغيرات `--color-mistral-*` (40+).
   - كل متغيرات `--font-*` و `--text-*` (20+).
   - كل متغيرات `--spacing-*` (10+).
   - كل متغيرات `--radius-*` (7).
   - كل متغيرات `--shadow-*` (5).
   - aliases للرموز القديمة (10 aliases).
2. حذف الرموز القديمة غير المستخدمة (`--color-teal-deep`, `--color-violet-soft`, `--color-surface-1/2/3`).
3. تشغيل `npm run build` للتأكد من عدم وجود أخطاء.

**الناتج:** ملف `app.css` محدّث. كل الرموز الجديدة متاحة. لا تغيير بصري بعد (لأن aliases تحافظ على المظهر).

### Phase P2: Components (3-4 أيام عمل)

1. **إنشاء المكونات الجديدة** (14 مكون):
   - `Button.vue` (الأكثر استخداماً، يُنفّذ أولاً).
   - `Card.vue`.
   - `FormCheckbox.vue`, `FormRadio.vue`, `FormSwitch.vue`.
   - `FormGroup.vue`.
   - `Tabs.vue`, `StatCard.vue`.
   - `SunsetStripeBand.vue` (في `layout/`).
   - `Breadcrumb.vue`, `Pagination.vue`, `Avatar.vue`.
   - `IconButton.vue`, `FormDatepicker.vue`.

2. **تحديث المكونات الموجودة** (13 مكون):
   - استبدال `var(--color-primary)` بـ `var(--color-mistral-primary)`.
   - استبدال `var(--color-ink-mute)` بـ `var(--color-mistral-steel)`.
   - استبدال `var(--color-ink-faint)` بـ `var(--color-mistral-stone)`.
   - استبدال `var(--color-hairline)` بـ `var(--color-mistral-hairline)`.
   - تحديث أحجام النصوص لتطابق `text-heading-*` tokens.
   - تحديث padding لتطابق `--spacing-*` tokens.
   - تحديث radius لتطابق `--radius-*` tokens.

3. **تحديث `Components\ui\index.js`** لتصدير المكونات الجديدة.

4. **تحديث `AppLayout.vue`** لإضافة `<SunsetStripeBand />`.

5. **إنشاء صفحة عرض مؤقتة** `Pages\_dev\DesignSystemShowcase.vue` للتحقق البصري.

**الناتج:** 14 مكون جديد + 13 مكون محدّث. صفحة showcase تعمل. النظام البصري موحّد.

### Phase P3: Page Migration (5-7 أيام عمل)

لكل وحدة من الـ 13 وحدة موجودة:

1. **استبدال `<button class="btn-*">` بـ `<Button variant="...">`**.
2. **استبدال `<input class="form-input">` بـ `<FormInput>`**.
3. **استبدال `<table>` بـ `<DataTable>`**.
4. **استبدال `<select class="form-input">` بـ `<FormSelect>`**.
5. **استبدال `<textarea class="form-input">` بـ `<FormTextarea>`**.
6. **استبدال `<a class="btn btn-primary">` بـ `<Button :href="...">`**.
7. **استبدال `class="card"` بـ `<Card>`**.
8. **إضافة `<SunsetStripeBand />`** في الـ layout.

الترتيب: ابدأ بـ `Companies` (الصفحة النموذجية في spec 002) → الباقي.

**الناتج:** 50+ ملف صفحة محدّث. 0 استخدام لـ `<input>` أو `<table>` خام.

### Phase P4: Cleanup (نصف يوم)

1. **حذف aliases** من `app.css` (10 aliases).
2. **حذف فئات `.btn-*` و `.card` و `.form-input` و `.form-checkbox`** القديمة.
3. **تحديث spec 002** لاستخدام رموز Mistral.
4. **حذف صفحة `_dev\DesignSystemShowcase.vue`**.
5. **تحديث `AGENTS.md`** بقسم "Design System" الجديد.

**الناتج:** نظام موحّد، بدون backward-compat. الدستور محدّث. spec 002 متزامن.

---

## اعتبارات التنفيذ

- **الأمان:** لا تغيير في الصلاحيات أو الأدوار.
- **الأداء:** إضافة ~30 متغير CSS لا تذكر. المكونات الجديدة تستخدم `computed` و `v-memo` كما هو منصوص في الدستور.
- **اللغة:** المكونات تستخدم `t()` للترجمة. تحديث `lang\ar\*` و `lang\en\*` عند الحاجة.
- **RTL:** كل المكونات الجديدة تدعم `dir="rtl"` و تستخدم logical properties (`ms-*`, `me-*`, `ps-*`, `pe-*`).
- **الوصول (Accessibility):** تطبيق WCAG AA على كل التفاعلات. focus ring واضح. aria-labels على كل الأزرار الأيقونية.
- **التوافق العكسي (Backward Compatibility):** aliases + shim classes لجيل واحد. spec 004 سيُنفّذ التنظيف الكامل.

---

## ترتيب التنفيذ المقترح (ملخص)

| # | المرحلة | المدة | المخرَج |
|---|---------|------|---------|
| 1 | P1: Tokens | 1 يوم | `app.css` محدّث |
| 2 | P2.1: Button component | 0.5 يوم | `Button.vue` |
| 3 | P2.2: Card + Form controls | 1 يوم | `Card`, `FormCheckbox`, `FormRadio`, `FormSwitch`, `FormGroup` |
| 4 | P2.3: باقي المكونات الجديدة | 1 يوم | 9 مكونات أخرى |
| 5 | P2.4: تحديث المكونات الموجودة | 1 يوم | 13 مكون محدّث |
| 6 | P2.5: SunsetStripeBand + AppLayout | 0.5 يوم | التوقيع البصري |
| 7 | P3: Page migration | 5-7 أيام | 50+ ملف محدّث |
| 8 | P4: Cleanup | 0.5 يوم | aliases محذوفة + spec 002 محدّث |
| **المجموع** | | **10-13 يوم** | النظام موحّد |

---

## الاختبارات

- **Visual regression:** فتح كل صفحة في الـ 13 وحدة في المتصفح.
- **Functional:** النقر على الأزرار، ملء النماذج، التحقق من الحفظ/التعديل/الحذف.
- **Accessibility:** فحص بـ axe DevTools.
- **Performance:** Lighthouse على 3 صفحات (Companies Index, Employees Create, Dashboard).
- **Lint:** `php artisan pint` + Vite build.
- **Tests:** `php artisan test` (لا تغيير في الـ tests، لكن نضمن عدم كسر شيء).

---

*آخر تحديث: 2026-07-15*
