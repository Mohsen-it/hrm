# Phase 1 — Stabilization Report (2026-09-14)

> القاعدة: تثبيت العقود كما هي، صفر تغيير في كود الإنتاج (باستثناء migration إضافية واحدة آمنة).

## النتيجة

| المقياس | خط الأساس (Phase 0) | بعد Phase 1 |
|---|---|---|
| إجمالي الاختبارات | 531 | 532 (+1 اختبار حد جديد) |
| ناجح | 479 | **504** |
| فاشل | 15 | **0** |
| خطأ | 37 | **0** |
| متخطى (موثق) | 0 | 28 (routes معطلة قصداً) |
| `pint --test` (الملفات المعدلة) | — | PASS |
| كود إنتاج معدل | — | **صفر سطر** |

المصدر: `test-phase1-full.log` (222s).

## ما تم (13 ملفاً: 12 اختبار + 1 migration)

### 1. عقود Shifts المعطلة قصداً — 28 skipped
- `ShiftCategoriesControllerTest` (13) + `ShiftCategoryAssignmentControllerTest` (15)
- السبب: routes معلقة في `Modules/Shifts/routes/web.php:19` و `:45` (backend-only، الإدارة عبر rotations/time-schedules حسب AGENTS.md).
- الإصلاح: `setUp` يتخطى مع رسالة توثيقية. الكود والـ controllers محفوظة 100%.

### 2. صلاحية `edit-fingerprint-devices` — 8 fixed
- `BidirectionalSyncTest` (3 ✅) + `DevicePushControllerTest` (5 ✅)
- السبب: `givePermissionTo` بدون `seedPermissions()` أولاً (الصف غير موجود في SQLite).
- الإصلاح: `seedPermissions()` في `setUp`. صفر إنتاج.

### 3. عمود `deleted_at` الناقص — migration إضافية آمنة
- `2026_09_15_000001_add_soft_deletes_to_device_sync_logs_and_push_results.php`
- السبب: `DeviceSyncLog` و`DevicePushResult` يستخدمان `SoftDeletes` والجدولان بلا عمود → كل استعلام `log-status` يسقط 500 (إنتاج أيضاً، ليس اختبارات فقط).
- الأمان: `nullable` + guards (`hasTable/hasColumn`) + `down()` عكسية. على MySQL: عمود nullable = online-safe، لكن يُنصح بنافذة صيانة عند `migrate` احتياطاً.
- كشف اختبارين `log-status` (404/تفاصيل) كانا مخفيين خلف الـ 500.

### 4. عقد التحقق في web routes — 3 fixed
- `DevicePushControllerTest` (validation ×3): التوقع كان 422 JSON، والعقد الفعلي (bootstrap/app.php:36 `shouldRenderJsonWhen(is('api/*'))`) هو 302 + session errors لكل web routes.
- الإصلاح: تثبيت العقد الفعلي. تغييره إلى 422 قرار منتج مؤجل (نماذج Inertia تعتمد على 302).

### 5. بروتوكول ADMS السلكي — 4 fixed
- `DeviceCommandServiceTest` (إعادة المحاولة + صيغة الوجه) + `AdmsDeliveryOrderTest` (×2)
- الكود كان صحيحاً (توثيق device-verified داخل السورس): وجه → `DATA UPDATE biodata Type=2`، مستخدم → `DATA UPDATE USERINFO`، حذف → `DATA DELETE USERINFO`، بصمة → `DATA UPDATE FINGERTMP`.
- stale: `DATA UPDATE FACE` + `C:10#/C:11#` + `FID` للوجه + `max_retries 15` (الفعلي 30).
- أضيف اختبار حد جديد: `test_failed_user_update_gives_up_after_max_retries` (الحد الأعلى للـ retry محمي).

### 6. توزيع الوجه — 4 fixed
- `FaceTemplateDistributionServiceTest` (×3) + `DistributeFaceTemplateSetJobTest` (×1)
- تثبيت السلوك الفعلي: exclusion المصدر في الـ Job (ليس الـ Service)، `queueSetForDevice` يوزع الأحدث لكل index متجاهلاً setId، عزل assertions عن side-effects الـ Observer.
- ⚠️ REVIEW مفتوحة (موثقة في الكود): `setId/sourceSerial` vestigial — إما استعادة set-scoping أو حذف الباراميترات بقرار منتج.

### 7. ADMS غير متزامن — 4 fixed
- `BiodataPushIntegrationTest` (×4): route الـ ADMS العام يوزع `BiodataIngestionJob` (burst protection) → `{queued:true}`، بينما route الـ biodata المخصص متزامن `{saved}`.
- التثبيت: `{received:1, queued:true}` + بقاء `assertDatabaseHas` (الـ sync driver يشغل الـ job inline — إثبات end-to-end بلا فقدان).

### 8. Parser + حدود — 2 fixed
- `BiodataParserTest`: wire type 1 = بصمة (مثبت 2026-09-10)، type 0 = غياب النوع → unknown.
- `StoreDevicePunchRequestTest`: سقف Body ارتفع 512KB → 1MB (رسالة التحقق تؤكد).

### 9. أهم إصلاح: نمط الدورية الخلفية — 2 fixed
- `DailyReportServiceTest` (3-day duty ×2): helper كان يبني نمط `[1,0,...,1,1]` بفرضية indexing خلفي، والمحرك أمامي (`RotationEngine.php:25`) — النمط كان ينتج يوماً واحداً بدل 3.
- الإصلاح: `[1,1,1,0,...]` (test-only). **المحرك سليم ومثبت باختباراته الخضراء — لم يُلمس.**

## قرارات مؤجلة (ليست حرجة)
1. `shouldRenderJsonWhen` → 422 لـ JSON على web routes (يحتاج مراجعة Inertia).
2. `queueSetForDevice(setId/sourceSerial)` vestigial (استعادة scoping أو حذف).
3. `pint` كامل المشروع ما زال FAIL في 5 ملفات قديمة (خارج النطاق، لم تُلمس).

## الخطوة التالية المقترحة (Phase 2 — أداء بقياس)
1. تفعيل slow-query log (100ms) في بيئة قياس + `EXPLAIN` للتقارير الثقيلة.
2. N+1 في `ReportsController` و`LiveAttendance` فقط بعد baseline أرقام.
3. مراجعة `route/config cache` في staging (ليس الإنتاج).
4. `npm run build` + تحليل bundle قبل أي تغيير Font Awesome.
