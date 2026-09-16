# Phase 3 — Batch 1: shared-state unification (2026-09-14)

> توحيد فوق المكونات الحالية. صفر تغيير منطقي، صفر تغيير routes/payloads.

## التدقيق (قبل التعديل)

- `<table>` خام: 17 موقعاً في 12 صفحة — كلها جداول تقارير/مصفوفات خاصة (Dashboard ×3، FaceSync ×3، DailySummaries ×3، Balances matrix مع تحرير خلايا، Rotations timeline/show، تقويم، معاينات) → **تُركت عمداً** حسب قاعدة "لا تحويل آلي".
- `<input>` خام: 2 فقط (`type="hidden"` مشروع) → تُرك.
- `<button>` خام: صفر. `<select>/<textarea>` خام: صفر.
- سبينر `fa-spin` خام: 12 موقعاً — 9 منها busy-icon داخل أزرار/خطوات (نمط صحيح، تُرك) + **3 كتل مستقلة حُوّلت**.
- Dashboard: سليم مسبقاً (EmptyState + charts async-chunk + StatCards). ألوان hex داخل chart.js data (viz palette) — خارج نطاق lint، تُركت.

## ما حُوّل (3 صفحات، 6 تعديلات)

| الصفحة | قبل | بعد |
|---|---|---|
| `Users/Index.vue` (modal سجل البصمة) | `<i fa-spinner>` + empty خام | `LoadingSpinner lg` + `EmptyState fa-fingerprint` |
| `Rotations/ManageAssignments.vue` | `<i fa-spinner>` | `LoadingSpinner lg` (نص التحميل بقي) |
| `Rotations/Assign.vue` | `<i fa-spinner>` + empty نصي | `LoadingSpinner lg` + `EmptyState fa-users` (تلميحا الإرشاد بقيا نصاً عمداً) |

## البوابات

- `lint:tokens`: PASS (raw-colors / aria / radius).
- `npm run build`: SUCCESS — 833 module، تحذير CSS واحد سابق (`text-[var(--color-ink-*)]`) لا علاقة له بهذه الدفعة. `public/build` git-ignored.
- اختبارات: Rotations (Service+Engine+Unassigned) + UserScope = 17/17 PASS. لا تغيير backend أصلاً.

## المتبقي للمرحلة 3 (دفعات لاحقة)

1. `FaceSyncDashboard` + `DailySummaries` + `Dashboard tables`: تقييم مكون `ReportTable` مشترك يحافظ على الخلايا المحسوبة والتصدير — **لا تحويل مباشر**.
2. `Assign.vue`/`BulkAssign.vue` بحث الموظفين: `debounce` للبحث (فحص `useFilters` أولاً).
3. تدقيق تباين RTL/LTR بصرياً (عربي + إنجليزي) قبل إغلاق المرحلة.
4. `SunsetStripeBand` ارتفاع 3px: تحقق أنه ما زال في كل layout (لم يُلمس).
