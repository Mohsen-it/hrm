# المزامنة الثنائية الاتجاه لأجهزة البصمة - خطة التنفيذ التقنية
# Bidirectional Fingerprint Device Sync - Technical Plan

**الإصدار:** 1.0.0
**التاريخ:** 2026-07-20
**الفرع (Branch):** `006-bidirectional-device-sync`

---

## السياق التقني (Technical Context)

### البنية التحتية
- **Framework:** Laravel 13 + PHP 8.3+
- **Frontend:** Vue 3 (Composition API) + Inertia.js + Tailwind CSS 4.3
- **Database:** SQLite (dev) / MySQL 8.0+ (prod)
- **ORM:** Eloquent
- **Architecture:** Modular (nwidart/laravel-modules)
- **Auth:** Spatie Permission
- **Build:** Vite
- **الـ Microservice الجانبي:** Python 3.x + Flask (`zkteco-service/` على `http://127.0.0.1:5000` لـ ZKTeco و `http://127.0.0.1:5001` لـ Hikvision)

### الوحدات المشاركة
| الوحدة | الدور |
|--------|------|
| `Modules\FingerprintDevices` | إدارة الأجهزة + UI المزامنة + Controllers |
| `Modules\AttendanceIntegration` | `DeviceAdapterInterface`، `ZKTecoAdapter`، `HikvisionAdapter`، `DeviceAdapterResolver` |
| `Modules\Users` | مصدر بيانات الموظفين |
| `Modules\Attendance` | تخزين `RawAttendanceLog` (السحب فقط) |

### التبعيات الحالية (Existing Code to Reuse)
- `Modules\FingerprintDevices\Services\DeviceFullSyncService` — السحب (info, users, fingerprints, face_photos, attendance).
- `Modules\FingerprintDevices\Services\DeviceSyncOrchestrator` (في `AttendanceIntegration`) — البديل المنظم للسحب.
- `Modules\AttendanceIntegration\Services\DeviceAdapterResolver` — حل الـ driver من نوع الجهاز.
- `Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoAdapter` — يحتوي `addUser`, `setFingerprintTemplate` (جاهز للدفع).
- `Modules\AttendanceIntegration\Drivers\Hikvision\HikvisionAdapter` — يحتوي `addUser`, `setFingerprintTemplate` (جاهز للدفع).
- `zkteco-service/app.py` (Flask, ZKTeco) — endpoints الدفع: `/device/add-user`، `/device/add-users-batch`، `/device/export-template`، `/device/export-templates-batch`.
- `zkteco-service/hikvision_service.py` (Flask, Hikvision) — نفس الـ endpoints لـ ISAPI.

### التبعيات الجديدة (New)
- `Modules\FingerprintDevices\Services\DevicePushService` (جديد) — الدفع فقط، يُكمل `DeviceFullSyncService`.
- `Modules\FingerprintDevices\Jobs\PushUsersToDeviceJob` (جديد) — Queue job للدفع الكبير.
- `Modules\FingerprintDevices\Jobs\PushFingerprintsToDeviceJob` (جديد) — Queue job للبصمات.
- جدولان جديدان: `device_sync_logs`، `device_push_results`.
- أعمدة جديدة على `fingerprint_devices`: `last_pushed_at`، `sync_log_count`.

### غير معروف (NEEDS CLARIFICATION)
- لا يوجد — تم توضيح جميع النقاط في `spec.md` §8 (افتراضات موثّقة) و §10 (مخاطر + تخفيفات).

---

## فحص الدستور (Constitution Check)

| المادة | الحالة | ملاحظات |
|--------|--------|---------|
| I: المبادئ العامة (1.1، 1.2) | ✅ متوافق | البساطة أولاً، توثيق، اختبار |
| II: بنية الوحدات (Controller→Service→Repository→Model) | ✅ متوافق | `DevicePushService` + `DevicePushRepository` يتبعان النمط |
| III: التسمية (Models مفرد، Controllers جمع، snake_case migrations) | ✅ متوافق | `DeviceSyncLog`، `DevicePushResult`، `create_device_sync_logs_table` |
| IV: قاعدة البيانات (FK + Indexes + Soft Deletes) | ✅ متوافق | FK constraints + composite indexes |
| V: الأمان (Spatie، Validation في Service، لا secrets) | ✅ متوافق | `edit-fingerprint-devices` للكتابة، CSRF على POST، لا passwords في logs |
| VI: الأداء (Eager loading، Chunking، Queue للمهام الثقيلة) | ✅ متوافق | chunkById للموظفين، Queue jobs للدفع الكبير |
| VII: المكونات المشتركة (DataTable، FormInput، FormModal، PageHeader) | ✅ متوافق | استخدام `DataTable`، `FormCheckbox`، `FormSelect`، `Button`، `Alert` |
| IX: التوثيق (PHPDoc، تعليقات على الكود المعقد) | ✅ متوافق | PHPDoc على كل method عام في الـ Service الجديد |
| X: البساطة (لا مكتبات غير ضرورية) | ✅ متوافق | استخدام HTTP client الموجود (`Illuminate\Support\Facades\Http`) |
| XIV: التوسع (DI، Stateless، Single Responsibility، Queue) | ✅ متوافق | كل Service يأخذ تبعياته عبر constructor، Queue jobs للموظفين > 200 |

**النتيجة:** ✅ لا توجد انتهاكات للدستور

---

## المرحلة 0: البحث والتحليل (Research)

> التفاصيل الكاملة في `research.md` — ملخص هنا:

| # | القرار | السبب الرئيسي |
|---|--------|---------------|
| R1 | **جسر Python موجود** يبقى كما هو (لا تعديل عليه) | `pyzk` و `ISAPI` موجودان؛ endpoints الدفع جاهزة |
| R2 | **`DevicePushService` منفصل** عن `DeviceFullSyncService` | SRP — السحب ≠ الدفع، يسهل الاختبار |
| R3 | **دفع على دفعات (batch=50)** عبر endpoint batch | تقليل overhead الـ HTTP + يدعم 100+ موظف بدون timeout |
| R4 | **Queue jobs** للموظفين > 200 | يلبّي SC-10 (لا يحجب المتصفح) |
| R5 | **جدولان جديدان** `device_sync_logs` + `device_push_results` | تتبع دقيق + تقارير فشل + تاريخ العمليات |
| R6 | **`employee_code` كمعرّف الجهاز** | يطابق منطق `DeviceSyncOrchestrator::stepUsers` الموجود |
| R7 | **تعديل `DeviceFullSyncService`** بدلاً من استبداله | يحافظ على التوافق مع FR-12 (السحب لا يتأثر) |
| R8 | **`FormCheckbox` + شريط تقدم SSE** | تجربة مستخدم متسقة مع باقي التطبيق |
| R9 | **اختياري: إذن `push-fingerprint-devices`** | فصل الصلاحيات بين السحب والدفع، قابل للدمج مع `edit-fingerprint-devices` |
| R10 | **`retry_failed` على الـ endpoint** | FR-21 (إعادة محاولة السجلات الفاشلة) |

---

## المرحلة 1: التصميم والعقود (Design & Contracts)

### نموذج البيانات (Data Model)

**الملف:** `specs/006-bidirectional-device-sync/data-model.md`

**الكيانات الجديدة:**
1. `DeviceSyncLog` — سجل عملية مزامنة كاملة (سحب/دفع/كلاهما).
2. `DevicePushResult` — نتيجة دفع كل سجل (user/fingerprint/face_photo).
3. تعديلات على `fingerprint_devices`: `last_pushed_at`، `sync_log_count`.

**الكيانات الموجودة المُستخدمة (لا تغيير عليها):**
- `User`، `UserFingerprint`، `FingerprintDevice`، `FingerprintDeviceType`، `Branch`.

### العقود (Contracts)

**المجلد:** `specs/006-bidirectional-device-sync/contracts/`

| العقد | الوصف |
|------|------|
| `device-push-api.md` | POST endpoints للدفع (مستخدمين/بصمات/الكل) |
| `device-sync-stream-api.md` | SSE endpoints (سحب + دفع + ثنائي) |
| `device-push-job.md` | Queue job contracts (payload + retry) |
| `fingerprint-device-resource.md` | تحديث Resource لإضافة `last_pushed_at` |

### الدليل السريع (Quickstart)

**الملف:** `specs/006-bidirectional-device-sync/quickstart.md`

**السيناريوهات:**
1. دفع 10 موظفين إلى جهاز ZKTeco + التحقق من الجهاز.
2. دفع 10 بصمات إلى جهاز Hikvision + التحقق من الجهاز.
3. مزامنة ثنائية (سحب + دفع) في عملية واحدة.
4. الدفع الانتقائي (شركة معينة فقط).
5. اختبار إعادة المحاولة (retry failed records).
6. اختبار Queue job (>200 موظف).

---

## ترتيب التنفيذ

### الموجة 1: قاعدة البيانات
1. `Modules\FingerprintDevices\database\migrations\2026_07_20_000001_add_last_pushed_at_to_fingerprint_devices_table.php`
2. `Modules\FingerprintDevices\database\migrations\2026_07_20_000002_create_device_sync_logs_table.php`
3. `Modules\FingerprintDevices\database\migrations\2026_07_20_000003_create_device_push_results_table.php`

### الموجة 2: النماذج
1. `Modules\FingerprintDevices\app\Models\DeviceSyncLog.php`
2. `Modules\FingerprintDevices\app\Models\DevicePushResult.php`
3. تعديل `FingerprintDevice` (إضافة fillable + casts + علاقة `pushResults`)

### الموجة 3: المستودعات
1. `Modules\FingerprintDevices\app\Repositories\DeviceSyncLogRepository.php`
2. `Modules\FingerprintDevices\app\Repositories\DevicePushResultRepository.php`

### الموجة 4: الخدمات
1. `Modules\FingerprintDevices\app\Services\DevicePushService.php` (الجوهر)
2. `Modules\FingerprintDevices\app\Services\DeviceSyncOrchestratorExtension.php` (يمدد الـ Orchestrator الموجود ليشمل الدفع)
3. `Modules\FingerprintDevices\app\Services\FingerprintDeviceService.php` (إضافة `pushUsers`، `pushFingerprints`)

### الموجة 5: الـ Jobs (Queue)
1. `Modules\FingerprintDevices\app\Jobs\PushUsersToDeviceJob.php`
2. `Modules\FingerprintDevices\app\Jobs\PushFingerprintsToDeviceJob.php`

### الموجة 6: التحكمات والمسارات
1. تعديل `Modules\FingerprintDevices\app\Http\Controllers\DeviceFullSyncController.php`:
   - إضافة `push()`، `pushStream()`، `pushAll()`، `retryFailed()`.
2. تعديل `Modules\FingerprintDevices\routes\web.php`:
   - إضافة مسارات `fingerprint-devices.sync.push`، `fingerprint-devices.sync.push-stream`، `fingerprint-devices.sync.push-all`، `fingerprint-devices.sync.retry-failed`.
3. `Modules\FingerprintDevices\app\Http\Requests\PushToDeviceRequest.php` (FormRequest).

### الموجة 7: الموارد (Resources)
1. تعديل `Modules\FingerprintDevices\app\Http\Resources\FingerprintDeviceResource.php`:
   - إضافة `last_pushed_at`، `sync_log_count`، `last_sync_log`.

### الموجة 8: الواجهات
1. تعديل `resources/js/Pages/FingerprintDevices/Sync.vue`:
   - إضافة قسم "دفع إلى الجهاز" مع `FormCheckbox`.
   - تعديل شريط التقدم ليشمل مراحل الدفع.
   - تعديل بطاقة الملخص لتشمل `pushed_users`، `pushed_fingerprints`.
2. تعديل `resources/js/Pages/FingerprintDevices/Index.vue`:
   - إضافة عمود `last_pushed_at` في `DataTable`.
   - إضافة زر "دفع سريع" لكل صف.
3. إنشاء `resources/js/Pages/FingerprintDevices/Partials/QuickPushModal.vue` (modal دفع سريع).

### الموجة 9: الترجمة والصلاحيات
1. `Modules\FingerprintDevices\lang\ar\fingerprint_devices.php` — مفاتيح جديدة (`sync_push_*`).
2. `Modules\FingerprintDevices\lang\en\fingerprint_devices.php` — نفس المفاتيح بالإنجليزية.
3. `Modules\FingerprintDevices\database\seeders\PermissionSeeder.php` (أو ما يعادله) — إضافة `push-fingerprint-devices`.

### الموجة 10: الاختبارات
1. `Modules\FingerprintDevices\tests\Unit\DevicePushServiceTest.php`
2. `Modules\FingerprintDevices\tests\Feature\DevicePushControllerTest.php`
3. `Modules\FingerprintDevices\tests\Feature\BidirectionalSyncTest.php` (end-to-end)
4. Browser test لـ `Sync.vue` (اختياري).

### الموجة 11: التحسين
1. `php artisan pint` على كل الملفات الجديدة.
2. `php artisan test` — جميع الاختبارات تمر.
3. فحص N+1 queries (تأكيد eager loading).
4. التأكد من SSE timeout معقول (< 30 دقيقة).

---

## الاعتبارات

### الأمان
- Spatie Permission: `edit-fingerprint-devices` على POST endpoints، `push-fingerprint-devices` (اختياري، يُسجَّل في Seeder).
- Validation في `FormRequest` + Service layer.
- لا passwords في logs (FR-18 ضمنياً).
- CSRF على كل النماذج.
- جسر Python يستمع على `127.0.0.1` فقط (افتراضياً) — لا تعرضه على الإنترنت.

### الأداء
- Chunking بـ `chunkById(50)` عند جلب الموظفين للدفع.
- Queue jobs للدفع > 200 موظف.
- Cache: لا — كل عملية مزامنة فريدة، لا داعي للـ cache.
- Eager loading: `UserFingerprint::with('device', 'user')`.
- فهرسة: `(device_id, started_at)` على `device_sync_logs`، `(sync_log_id)` على `device_push_results`.
- SSE timeout: 30 دقيقة كحد أقصى (لـ 1000+ سجل).

### اللغة والـ RTL
- ترجمة عربية وإنجليزية لكل النصوص الجديدة.
- `useTranslations()` composable في Vue.
- دعم RTL افتراضي (موروث من `AppLayout`).

### المكونات المشتركة المُستخدمة
- `<DataTable />` (لعمود `last_pushed_at` في `Index.vue`)
- `<FormCheckbox />` (لخيارات الدفع في `Sync.vue`)
- `<FormSelect />` (لاختيار الجهاز)
- `<FormModal />` (`QuickPushModal`)
- `<PageHeader />` (في `Sync.vue`)
- `<Alert />` (لرسائل الخطأ والنجاح)
- `<Badge />` (لحالات السجلات)
- `<Button />` (لأزرار الدفع والإلغاء)
- `<LoadingSpinner />` (دفع قيد التشغيل)
- `<StatCard />` (لملخصات الدفع في النتيجة)

---

## التحقق من الدستور (Post-Design)

| المادة | الحالة |
|--------|--------|
| II: بنية الوحدات | ✅ كل طبقة جديدة (PushService، PushRepository، Model، Job) تتبع النمط |
| III: التسمية | ✅ Models مفرد، Controllers جمع، Migrations snake_case |
| IV: قاعدة البيانات | ✅ FK + composite indexes + timestamps |
| V: الأمان | ✅ Service-level validation، CSRF، لا secrets في الكود |
| VI: الأداء | ✅ Eager loading + chunkById + Queue |
| VII: المكونات | ✅ استخدام 9 مكونات مشتركة من `Components/ui/` |
| XIV: التوسع | ✅ DI عبر constructor، Stateless Services، SRP، Queue |

**النتيجة:** ✅ التصميم متوافق مع الدستور، جاهز للتنفيذ.

---

## مخرجات المرحلة 1 (Phase 1 Artifacts)

- [x] `specs/006-bidirectional-device-sync/spec.md` (من /speckit.specify)
- [x] `specs/006-bidirectional-device-sync/checklists/requirements.md`
- [x] `specs/006-bidirectional-device-sync/plan.md` (هذا الملف)
- [x] `specs/006-bidirectional-device-sync/research.md`
- [x] `specs/006-bidirectional-device-sync/data-model.md`
- [x] `specs/006-bidirectional-device-sync/contracts/device-push-api.md`
- [x] `specs/006-bidirectional-device-sync/contracts/device-sync-stream-api.md`
- [x] `specs/006-bidirectional-device-sync/contracts/device-push-job.md`
- [x] `specs/006-bidirectional-device-sync/contracts/fingerprint-device-resource.md`
- [x] `specs/006-bidirectional-device-sync/quickstart.md`

---

*آخر تحديث: 2026-07-20*
