# ترحيل الـ Persistent Layout (Inertia + Vue 3)

> وثيقة تخطيط وتتبع التقدم لترحيل مشروع HRM من نمط الغلاف (Wrapper Layout) إلى
> نمط التخطيط المستمر (Persistent Layout) في Inertia.

## الهدف

إيقاف إعادة تركيب (Re-mount) الـ Layout الرئيسي عند كل تنقّل بين الصفحات، بحيث
يبقى السايدبار والنافبار واتصال الـ WebSocket (Echo) ثابتين عبر التنقلات.

## التشخيص (قبل الترحيل)

| البند | النتيجة |
|---|---|
| صفحات تستخدم نمط الغلاف `<AppLayout>` داخل `<template>` | **142 صفحة** |
| صفحات تستخدم `layout: AppLayout` (النمط المستمر) | **0 صفحات** |
| حل مؤقت لتمرير السايدبار | موجود في `NavSidebar.vue` (`lastSidebarScrollTop`) |
| صفحات بدون عنوان (`<AppLayout>` بدون `title`) | 4 صفحات (AttendanceShifts Create/Edit/Show + Rotations/Timeline) |
| صفحات لا تستخدم AppLayout | 7 (Login + 6 Partials) — لا تحتاج تعديل |

### المشاكل الناتجة عن إعادة التركيب
1. **فقدان موضع تمرير السايدبار** عند التنقّل (تمت معالجته مؤقتاً بمتغير على مستوى
   الوحدة في `NavSidebar.vue` — والكود يعترف بالمشكلة في تعليق).
2. **إعادة الاشتراك بـ Echo (WebSocket)** مع كل تنقّل لأن `useRealtimeAttendance`
   مربوط بـ `onMounted`/`onUnmounted` داخل AppLayout.
3. إهدار موارد: إعادة بناء الشجرة الفرعية كاملة (سايدبار + نافبار + لوحة أوامر) عند
   كل زيارة.

## القرار

**الخيار A — الترحيل الكامل إلى Persistent Layout** (الطريقة الرسمية الموصى بها
في Inertia). لكل صفحة:

1. إضافة بلوك `<script>` عادي:
   ```vue
   <script>
   import AppLayout from '@/Layouts/AppLayout.vue';

   export default {
       layout: AppLayout,
   };
   </script>
   ```
2. حذف غلاف `<AppLayout :title="...">...</AppLayout>` من القالب.
3. نقل تعبير العنوان إلى composable `usePageTitle(...)` داخل `<script setup>`
   (لأن الـ Layout المستمر لا يستقبل props من الصفحة).

## خطة التنفيذ

- [x] **1. composable العنوان** — `resources/js/composables/usePageTitle.js`
      (حالة reactive على مستوى الوحدة + `resetPageTitle()`).
- [x] **2. تحديث AppLayout.vue** — `defineOptions({ inheritAttrs: false })` لمنع
      تسرب props الصفحات إلى الـ DOM، وقراءة العنوان من composable، وإعادة تعيين
      العنوان عند كل تنقّل (لأن 4 صفحات بلا عنوان).
- [x] **3. تنظيف NavSidebar.vue** — حذف الـ workaround المؤقت للتمرير
      (`scrollRef` / `lastSidebarScrollTop` / `captureSidebarScroll`).
- [x] **4. سكريبت الترحيل الآلي** — `scripts/migrate-persistent-layout.js`
      (تحويل 142 صفحة دفعة واحدة).
- [x] **5. التحقق** — grep: صفر `<AppLayout>` في القوالب + `npm run build` ناجح +
      فحص عينات من الصفحات.
- [x] **6. تحديث هذه الوثيقة** بالنتائج النهائية.

## حالة التنفيذ (سجل التقدم)

| التاريخ | الخطوة | الحالة |
|---|---|---|
| 2026-08-17 | إنشاء composable `usePageTitle` | ✅ |
| 2026-08-17 | تحديث `AppLayout.vue` | ✅ |
| 2026-08-17 | تنظيف `NavSidebar.vue` | ✅ |
| 2026-08-17 | سكريبت الترحيل | ✅ |
| 2026-08-17 | تنفيذ السكريبت (142 صفحة) | ✅ |
| 2026-08-17 | التحقق + البناء | ✅ |

## نتائج التحقق (2026-08-17)

| الفحص | النتيجة |
|---|---|
| `<AppLayout>` متبقٍ في قوالب Pages | **0** |
| صفحات تحتوي `layout: AppLayout` | **142** |
| صفحات تحتوي `usePageTitle(...)` | **138** (142 − 4 بلا عنوان) |
| `</AppLayout>` متبقٍ | **0** |
| `npm run build` (vite) | ✅ نجح |

### عينات تم فحصها يدوياً
- `Attendance/DailyReport/Index.vue` — عنوان عربي حرفي → `usePageTitle('التقرير اليومي')`.
- `Shifts/AttendanceShifts/Show.vue` — قالب قبل السكريبت + استيراد بمسار صغير
  (`@/layouts/...`) + بلا عنوان (بدون استدعاء `usePageTitle`).
- `Vacations/Justifications/Edit.vue` — قالب من سطر واحد + عنوان عربي.
- `Shifts/Schedules/Show.vue` — عنوان template literal محفوظ كاملاً.
- `Shifts/ShiftCategories/Show.vue` — تعبير `props.category?.name` يعمل لأن
  `props` معرّف في `setup`.

## اختبار تشغيلي فعلي (E2E) — 2026-08-17

تم تسجيل الدخول على الخادم المحلي (`http://127.0.0.1:8000`) بحساب
`admin@hrm.local` (كلمة مرور الـ seeder) والتنقل بين 3 صفحات مختلفة عبر روابط
Inertia، مع التحقق البرمجي من ثبات العقدة وحالة التمرير:

| الاختبار | النتيجة |
|---|---|
| تسجيل الدخول وعرض Dashboard (صفحة AppLayout) | ✅ |
| التنقل /dashboard → /users → /dashboard → /attendance/live | ✅ بدون إعادة تحميل كاملة |
| نفس عقدة السايدبار DOM بعد 3 تنقلات (`isConnected`) | ✅ **true** (لم يُعد التركيب) |
| حفظ موضع تمرير السايدبار بعد التنقل (scrollHeight 1434→1671) | ✅ لم يُصفَّر (200→437) |
| بقاء المجموعات الموسّعة مفتوحة بعد التنقل | ✅ |
| تحديث عنوان النافبار لكل صفحة (لوحة التحكم ↔ الموظفون ↔ الحضور المباشر) | ✅ |
| إعادة الاشتراك بـ Echo: طلب `broadcasting/auth` واحد فقط خلال كل التنقلات | ✅ الاتصال حيّ |
| أخطاء الكونسول | ✅ صفر |
| زر الرجوع في المتصفح (history) | ✅ |

## ملاحظة: تراجع وإعادة تنفيذ

النسخة الأولى من السكريبت أدرجت استيراد `usePageTitle` خارج بلوك `<script setup>`
(خطأ في حساب موضع الإدراج). تم اكتشافه بفحص عينة، والتراجع عبر
`git checkout -- resources/js/Pages` (لا توجد تعديلات سابقة غير مُلتزمة)، وإصلاح
السكريبت مع إضافة فحص بنيوي يتحقق من وجود الاستدعاء داخل البلوك، ثم إعادة
التنفيذ. درس مستفاد: فحص البنية تلقائياً في السكريبت نفسه.

## ملاحظات ومخاطر

- **العنوان**: بعد الترحيل لا يمكن تمرير `title` من الصفحة للـ Layout، لذلك يُحل
  عبر `usePageTitle()` — يعمل مع كل التعبيرات الموجودة (`t(...)`، template
  literals، `displayName`، `props.category?.name`) لأنها متغيرات نطاق `setup`.
- **الموبايل**: السايدبار يرسل `close` عند النقر على أي رابط (`onNavigate`) فلا
  يبقى الدرج مفتوحاً بعد الترحيل.
- **البناء**: Vue 3.5 يدعم `defineOptions`؛ لا توجد بلوكات `<script>` عادية
  حالياً في الصفحات فلن يحدث تعارض.
- **`inheritAttrs: false` إلزامي**: بعد الترحيل سيمرر Inertia كل props الصفحة إلى
  الـ Layout؛ بدون هذا الخيار ستتسرب (users, filters...) كخصائص DOM.
