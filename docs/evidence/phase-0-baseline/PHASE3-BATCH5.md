# Phase 3 — Batch 5: item 9 verdict (2026-09-14)

## الحكم بعد دراسة كل جدول متبقٍ

| الجدول | القرار | السبب |
|---|---|---|
| Timeline (شهر × موظف، sticky مزدوج) | لا ترحيل | رأس مزدوج + أعمدة لاصقة + خلايا ملونة يومياً — هيكل لا يستوعبه ReportTable إلا بإعادة كتابة |
| Show (تقويم شهري W/R) | لا ترحيل | شبكة أسابيع × أيام — مكون تقويم لا جدول تقرير |
| Dashboard ×3 | لا ترحيل | variant ثالث متسق (headers uppercase + divide rows) — التوحيد القسري يغير التصميم |
| DailySummaries ×3 | لا ترحيل (مؤكد) | variant cream-violation المتسق داخلياً |
| Timeline empty الخام | **حُوّل → EmptyState** | icon/title/description تطابق props تماماً |

## الأنماط المعتمدة للتقارير (3)

1. `hairline/steel` → `ReportTable` (FaceSync ×3 مكتملة).
2. `cream-violation` (DailySummaries ×3) — متسق، يُترك.
3. `widget-uppercase` (Dashboard ×3) — متسق، يُترك.

## البوابات

- `lint:tokens`: PASS. `build`: SUCCESS.
- اختبارات Shifts: 73 (45 ناجح + 28 متخطى الموثق)، 0 فاشل.
