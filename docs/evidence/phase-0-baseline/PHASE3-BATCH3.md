# Phase 3 — Batch 3: FaceSync tabs 2-3 + DailySummaries verdict (2026-09-14)

## ReportTable: إضافتان (`clickable` + `compact`)

- `clickable` + حدث `row-click` (مرآة `DataTable.rowClickable`) — لصف الموظف القابل للنقر.
- `compact` (py-2 بدل py-3 للـ th/td) — للجداول الكثيفة.
- القيم الافتراضية تحافظ على جدول الدفعة 2 حرفياً (تحقق بالبناء).

## الترحيل 2: تبويب الموظفين (8 أعمدة)

- الخلايا حرفية (بadge الثلاثي، شريط التغطية، `source_devices.join`) + `row-click → openEmployeeDetail` + `cursor-pointer` محفوظ.
- الفراغ المخصص (أيقونة بحث + no_results) محفوظ عبر `#empty` — صفر تغيير بصري.

## الترحيل 3: جدول الأوامر الفاشلة (5 أعمدة، مضغوط)

- `compact` + `table-class text-[12px]` + `:hover=false` (الأصل بلا hover) — تكافؤ تام.
- العمود الخامس بلا رأس كما كان (`label: ''`).
- `truncate/max-w` انتقلت إلى `span.block` داخلي (نفس العرض، سياق CSS أصح).
- كتلة الفراغ (check-circle خضراء) محفوظة عبر `#empty`.

## حكم DailySummaries ×3: لا ترحيل (موثق)

- نمط تصميم مختلف شرعي: `mistral-border` + رأس `mistral-cream/30` + `px-4` + `dir=ltr` للتواريخ — توحيدها قسراً في ReportTable كان سيغير التصميم لا يحافظ عليه.
- متسقة داخلياً (نفس النمط ×3) وتستخدم `EmptyState` + `Button loading` أصلاً.
- القاعدة: التوحيد ≠ التماثل. نمط cream-violation يبقى نمطاً ثانياً معتمداً.

## البوابات

- `lint:tokens`: PASS. `build`: SUCCESS — chunk FaceSync **29.33KB مقابل 30.54KB** (أصغر: shells مكررة أُزيلت).
- اختبارات الأجهزة: 31/31 PASS.

## حالة المرحلة 3

- مكتمل: Users modal + Assign + ManageAssignments + FaceSync ×3 + مكون ReportTable.
- متروك عمداً: Dashboard ×3 + DailySummaries ×3 + Balances matrix + Timeline/Show + تقويم (تقارير خاصة)، busy-icons، تلميحات إرشادية.
- متبقٍ لاحقاً: تدقيق RTL/LTR بصري + `SunsetStripeBand` + تحويلات `<button>` الانتقائية.
