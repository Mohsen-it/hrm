# Phase 0 — Baseline (2026-09-14)

> قراءة فقط. لم يتم تعديل أي كود أو بيانات. كل الأوامر التالية آمنة: `about`, `route:list --json`, `test` على SQLite memory, `pint --test`, `lint:tokens`.

## 1. البيئة
- Laravel 13.19.0 / PHP 8.3.28 / Composer 2.10.2
- APP_ENV=production / DEBUG=OFF / URL=10.10.250.2 / Locale=ar / TZ=Asia/Riyadh
- DB=mysql / Queue=database / Cache=file / Session=file / Broadcast=log / Mail=log
- Cache: config NOT CACHED, events NOT CACHED, routes NOT CACHED, views CACHED
- Modules: nwidart v12.0.0 / Spatie Permissions v8.0.0
- Git: branch `main`, working tree CLEAN (لا تعديلات محلية — ملفات الخطة المذكورة كـ dirty أصبحت committed أو غير موجودة كـ diff)
- المصدر: `docs/evidence/phase-0-baseline/about.json`

## 2. المسارات: 388
- المصدر: `route-list.json`
- device/adms/biodata/push/fingerprint/face/sync/live: 59
- attendance/punch/shift/rotation/schedule: 148
- report/export: 42
- api/*: 56
- ملاحظة: العدد يشمل تداخل الفئات (نفس الـ URI قد يُحسب في فئتين). التصنيف للمراقبة فقط وليس حدّاً.

## 3. الاختبارات: 531 test / 1441 assertion / 265s
- ناجح: 479
- فاشل: 15 + خطأ: 37 = 52 مشكلة قائمة مسبقاً (قبل أي تعديل)
- المصدر: `test-full.log`
- الحماية: `tests/TestCase.php` يجبر SQLite memory ويرفض التشغيل على mysql — لا خطر على بيانات الإنتاج.

### 3.1 الفشل (15) — لا تلمس قبل الحماية
1. `StoreDevicePunchRequestTest::test_body_max_size` — يتوقع `max:524288` والفعلي `max:1048576`
2. `BiodataParserTest::test_type_label_fingerprint` — يتوقع `fingerprint` والفعلي `unknown_type_0`
3-4. `DailyReportServiceTest` (three_day_duty ×2) — `present` مقابل `rest` + assert false
5-6. `DeviceCommandServiceTest` (failed_user_update_not_requeued + face_template_body_format `DATA UPDATE biodata` vs `DATA UPDATE FACE`)
7. `DistributeFaceTemplateSetJobTest` — 15 مقابل 16
8-9. `FaceTemplateDistributionServiceTest` ×2 — عدّ الأوامر (2 vs 1 / 1 vs 0)
10-13. `BiodataPushIntegrationTest` ×4 — يتوقع `{saved:1}` والفعلي `{queued:true + correlation_id}`
14-15. `AdmsDeliveryOrderTest` ×2 — صيغة `DATA UPDATE USERINFO/biodata` مقابل `C:10#` / `DATA UPDATE FACE`

### 3.2 الأخطاء (37) — عقود مكسورة مسبقاً
- `edit-fingerprint-devices` غير معرّفة لـ guard `web` → تكسر `BidirectionalSyncTest` + `DevicePushControllerTest` (9 اختبارات)
- `shift-categories.*` غير معرّفة → تكسر `ShiftCategoriesControllerTest` (13 اختباراً)
- `shift-assignments.*` غير معرّفة → تكسر `ShiftCategoryAssignmentControllerTest` (15 اختباراً)
- `FaceTemplateDistributionServiceTest::test_queues_historical...` — `2 records were found`

### 3.3 القاعدة
ممنوع إصلاح هذه الـ 52 في نفس PR مع أي تحسين أداء أو UI. تُصلح في Phase 1 كـ `تثبيت عقود` مع قرار موثق لكل واحدة: هل الكود صح والاختبار قديم، أم العكس.

## 4. الجودة
- `php artisan pint --test`: FAIL — 5 ملفات تحتاج تنسيق (منها `backups/debug-archive...` و `grant_admin_bridge.php` الجذريان + 3 في الوحدات). لا تُصلح الآن؛ تُسجل كدين.
- `npm run lint:tokens`: PASS — raw-colors / aria-labels / radius كلها OK.

## 5. الحزمة (public/build)
- الإجمالي: ~2.75MB
- الأكبر: `inertia-vendor 201KB`, `charts 186KB`, `app.css 136KB`, `fa-solid 116KB`, `fa-brands 112KB`, `realtime 70KB`, `AppLayout 46KB`, `DataTable 43KB`
- المرشح الأول للقياس (وليس التغيير بعد): استيراد Font Awesome الكامل في `resources/js/app.js` + فصل `charts` و `realtime` إلى async chunks.

## 6. الخطوة التالية (Phase 1 — تثبيت فقط، لا تحسين)
1. توثيق states الأوامر و idempotency لكل توزيع fingerprint/face/user.
2. تثبيت عقود `PunchIngestionService`, `SchedulePunchClassifierService`, `DailyReportService`, `MonthlyReportService`, `AbsenceCalculationService` باختبارات حدود زمنية.
3. حصر route/permission matrix وإضافة اختبار لكل مسار حرج (guest redirect / forbidden / success).
4. مراجعة route cache في بيئة اختبار فقط.
5. لا N+1 fixes، لا bundle changes، لا UI migration قبل إغلاق الـ 52 أو تصنيفها رسمياً.
