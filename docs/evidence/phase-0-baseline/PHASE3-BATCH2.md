# Phase 3 — Batch 2: ReportTable shared component (2026-09-14)

## المكون الجديد: `resources/js/Components/ui/ReportTable.vue`

- غلاف تقرير presentational: `columns[]` + `items[]` + slots (`cell-{key}`, `header-{key}`, `empty`).
- نفس لغة `DataTable`: `role="grid"`, `scope="col"`, `aria-rowcount`, `dir` (rtl افتراضياً)، محاذاة منطقية (`start/center/end`)، tokens (`mistral-steel/hairline/canvas`)، hover، sticky-header اختياري.
- حالات مدمجة: `loading` → `LoadingSpinner`، فارغ → `EmptyState`.
- مسجل في `ui/index.js` حسب المادة VII (مكونات مشتركة).

## الترحيل الأول: FaceSyncDashboard تبويب الأجهزة (11 عموداً)

- الخلايا نُقلت حرفياً إلى slots (شريط الصحة، الألوان الشرطية، روابط، زر retry مع disabled/title) — **صفر تغيير سلوكي**.
- فروق مقصودة وموثقة فقط:
  1. `th` الأول: `text-left` الفيزيائية ← `text-start` المنطقية (إصلاح RTL؛ في LTR متطابق).
  2. كلاسات `font-semibold/text-*` انتقلت من `td` إلى `span` داخلي (نفس العرض).
  3. حالة فراغ جديدة `EmptyState` (كانت `tbody` فارغة) + `role/aria` (تحسين وصول).
- حجم chunk بعد البناء: 30.54KB مقابل 30.6KB قبله — تكافؤ عملي.

## البوابات

- `lint:tokens`: PASS. `npm run build`: SUCCESS (834 modules، نفس تحذير CSS السابق).
- اختبارات FingerprintDevices: 27/27 PASS (DevicePush + Bidirectional + AdmsDelivery + DeviceCommand).

## المتبقي (دفعات لاحقة)

- ترحيل تبويبي FaceSync (الموظفون/الأوامر) + DailySummaries ×3 + Dashboard ×3 بنفس النمط.
- 24 زر `<button>` خام (تبويبات، إغلاق، أيقونات خاصة) — تحويل انتقائي فقط حيث يطابق `Button/IconButton` تماماً.
- تدقيق RTL/LTR بصري (عربي/إنجليزي) + `SunsetStripeBand` 3px قبل إغلاق المرحلة 3.
