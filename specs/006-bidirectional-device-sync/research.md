# البحث والتحليل - Bidirectional Fingerprint Device Sync

**التاريخ:** 2026-07-20
**الم_feature:** 006-bidirectional-device-sync

---

## قرارات البحث (Research Decisions)

### R1. الجسر Python — لا تعديل عليه

**القرار:** جسر Python (`zkteco-service/`) يبقى كما هو تماماً.

**السبب:**
- جميع endpoints الدفع موجودة وجاهزة:
  - `POST /device/add-user` (ZKTeco + Hikvision)
  - `POST /device/add-users-batch` (ZKTeco)
  - `POST /device/export-template` (ZKTeco + Hikvision)
  - `POST /device/export-templates-batch` (ZKTeco)
- مكتبة `pyzk` تتولى `pyzk.zk.user` و `pyzk.zk.finger` للجانب ZKTeco.
- `requests + HTTPDigestAuth` للجانب Hikvision (ISAPI).
- تعديل الجسر يخالف قاعدة "عدم تعديل ما يعمل" في المادة X من الدستور.

**البدائل المُ considered:**
- كتابة طبقة PHP مباشرة لـ `pyzk` (عبر `shell_exec`): ❌ مخاطر أمنية + صعوبة الصيانة.
- إعادة كتابة الجسر بـ Node.js: ❌ يخسر الاستثمار في Python الموجود.
- استبدال `pyzk` بـ SDK رسمي ZKTeco (C++/C#): ❌ Windows-only، يخالف المادة X (البساطة).

---

### R2. `DevicePushService` منفصل عن `DeviceFullSyncService`

**القرار:** إنشاء `DevicePushService` كـ service مستقل، دون دمجه في `DeviceFullSyncService`.

**السبب:**
- **SRP** (Single Responsibility): السحب ≠ الدفع — مسؤوليات مختلفة، استخدامات مختلفة.
- **Testability**: يمكن اختبار الدفع والسحب بمعزل.
- **Open/Closed**: السحب يبقى يعمل (FR-12)، الدفع يُضاف فوقه دون تعديل.
- **Permission Granularity**: يمكن لاحقاً إعطاء إذن `push-fingerprint-devices` فقط.

**البدائل المُ considered:**
- دمج الدفع في `DeviceFullSyncService`: ❌ يخلط المسؤوليات ويصعّب الاختبار.
- استبدال `DeviceFullSyncService` بـ Orchestrator موحّد: ❌ كسر الكود الموجود، يخالف FR-12.

---

### R3. دفع على دفعات (batch=50) عبر endpoint batch

**القرار:** افتراضياً، يُدفع 50 موظف / 50 بصمة في طلب HTTP واحد.

**السبب:**
- **تقليل الـ HTTP overhead**: 100 موظف = 2 طلبات بدلاً من 100.
- **مطابقة قدرات الجسر**: `add_users_batch` و `export_templates_batch` موجودان في Python.
- **ZKTeco official recommendation**: SDK يقترح دفعات 30-100 سجل.
- **Timeout management**: كل دفعة < 30 ثانية.

**البدائل المُ considered:**
- دفع واحد (record-by-record): ❌ بطيء جداً لـ 200+ موظف.
- دفعة واحدة 1000+: ❌ timeout خطير، يصعّب استئناف الفشل.
- دفعة ديناميكية حسب عدد السجلات: ❌ over-engineering.

**الجدول:**
| عدد السجلات | السلوك |
|-------------|--------|
| < 50 | طلب واحد |
| 50–500 | 10 طلبات متتالية |
| 500+ | Queue job (FR-11) |

---

### R4. Queue jobs للموظفين > 200

**القرار:** إذا كان عدد الموظفين المُحدَّدين للدفع > 200، يُنشأ `PushUsersToDeviceJob` Queue job.

**ال السبب:**
- **SC-10**: "الدفع لـ 200+ موظف يعمل في الخلفية ولا يحجب المتصفح".
- **UX**: المستخدم يضغط "دفع" ثم يُغلق الصفحة بدون انتظار.
- **Failure isolation**: فشل قسم لا يلغي القسم الآخر.
- **Retry**: Queue يدعم `tries`, `backoff` أصلياً.

**البدائل المُ considered:**
- SSE فقط (بدون Queue): ❌ يحجب المتصفح لوقت طويل، انقطاع = خسارة.
- Cron: ❌ لا يعطي تحكماً مباشراً.
- بدون Queue نهائياً: ❌ يخالف SC-10.

**الآلية:**
```
if (count($userIds) > 200) {
    dispatch(new PushUsersToDeviceJob($deviceId, $userIds));
    return response()->json(['status' => 'queued', 'job_id' => ...]);
}
```

---

### R5. جدولان جديدان لتتبع المزامنة

**القرار:** إضافة `device_sync_logs` و `device_push_results`.

**السبب:**
- **Auditability**: تتبع كل عملية مزامنة (تاريخ، من، كم، ما).
- **Retry support**: FR-21 (إعادة محاولة الفشل) يحتاج معرّف السجل.
- **Reporting**: التقارير المستقبلية (معدل النجاح، أنواع الأخطاء) تصبح ممكنة.
- **JSON storage**: تفاصيل المراحل (`steps`، `totals`، `errors`) مخزنة كـ JSON لتجنب جداول منفصلة.

**البدائل المُ considered:**
- ملف log فقط: ❌ لا يمكن الاستعلام عنه.
- جدول واحد: ❌ يخلط العملية بالتفاصيل.
- تخزين في `fingerprint_devices` (حقول JSON): ❌ يخسر التاريخ.

**الفهارس المختارة:**
- `device_sync_logs`: `(device_id, started_at)`، `(status)`، `(user_id, started_at)`.
- `device_push_results`: `(sync_log_id)`، `(device_id, record_type, status)`.

---

### R6. `employee_code` كمعرّف الجهاز (`user_id`)

**القرار:** استخدام `users.employee_code` كمعرّف المستخدم على جهاز البصمة.

**السبب:**
- **الاتساق**: `DeviceSyncOrchestrator::stepUsers` يتبع نفس المنطق.
- **Idempotency**: نفس الموظف → نفس `user_id` على الجهاز → لا تكرار.
- **Bilingual**: الاسم قابل للتغيير، الكود ثابت.

**البدائل المُ considered:**
- `users.id` (PK): ❌ أرقام داخلية قد تتغير، تخالف اصطلاحات ZKTeco.
- `users.email`: ❌ قد لا يكون موجوداً لبعض الموظفين.

---

### R7. تعديل `DeviceFullSyncService` بدلاً من استبداله

**القرار:** الإبقاء على `DeviceFullSyncService` كما هو + إضافة `DevicePushService` كطبقة عليا.

**السبب:**
- **Backward compatibility**: FR-12 صريح — السحب الحالي يجب أن يبقى يعمل.
- **Code reuse**: `stepInfo`, `stepUsers`, `stepFingerprints` (pull) تُعاد استخدامها.
- **Risk reduction**: لا حاجة لـ retest سحب تم اختباره مسبقاً.

**البدائل المُ considered:**
- إعادة كتابة `DeviceFullSyncService`: ❌ مخاطر regression.
- نقل السحب لـ `DevicePushService`: ❌ يخلط المسؤوليات.

---

### R8. `FormCheckbox` + SSE في الواجهة

**القرار:** استخدام المكونات الموجودة (`FormCheckbox`, `FormSelect`, `DataTable`, `Button`, `Alert`, `Badge`) + توسيع `Sync.vue` بقسم دفع.

**السبب:**
- **الاتساق البصري** (المادة VII من الدستور).
- **RTL** مدمج في المكونات.
- **Accessibility** (Tab navigation, ARIA labels) جاهز.

**البدائل المُ considered:**
- بناء UI مخصص: ❌ يخالف VII من الدستور.
- تعديل `FormCheckbox` لإضافة حالة جديدة: ❌ غير ضروري، الخصائص الحالية كافية.

---

### R9. إذن `push-fingerprint-devices` اختياري

**القرار:** إضافة إذن جديد `push-fingerprint-devices` قابل للدمج مع `edit-fingerprint-devices`.

**السبب:**
- **Granularity**: يمكن للمدير تقسيم الصلاحيات (موظف HR يسحب فقط، تقني IT يدفع).
- **Backward compat**: الإذن الجديد افتراضياً يُمنح مع `edit-fingerprint-devices` في الـ Seeder.

**البدائل المُ considered:**
- لا إذن جديد (يعتمد على `edit-fingerprint-devices`): ❌ يخل بصلاحية الفصل.
- إذن منفصل بالكامل: ❌ يحتاج migration من المستخدمين الحاليين.

**الآلية في Seeder:**
```php
$pushPermission = Permission::firstOrCreate(['name' => 'push-fingerprint-devices']);
$adminRole->givePermissionTo($pushPermission); // مع edit-fingerprint-devices
```

---

### R10. `retry_failed` على الـ endpoint

**القرار:** endpoint منفصل `POST /sync/retry/{logId}` يُعيد محاولة السجلات الفاشلة فقط.

**السبب:**
- **FR-21**: "السجلات الفاشلة يجب أن تكون قابلة لإعادة المحاولة بنقرة واحدة".
- **Efficiency**: لا حاجة لإعادة دفع 1000 موظف ناجح.
- **State tracking**: `device_push_results.status` يحدد ما الذي يحتاج إعادة.

**البدائل المُ considered:**
- إعادة دفع الكل: ❌ يكرر العمل الناجح.
- من الواجهة فقط (بدون endpoint): ❌ لا يمكن جدولة الإعادة.

---

## أفضل الممارسات المطبقة (Best Practices Applied)

### Laravel
- **Service Layer** للمنطق الأعمال.
- **Repository Layer** للوصول للبيانات.
- **FormRequest** للتحقق.
- **Resource** لتنسيق البيانات.
- **Eager loading** لتجنب N+1.
- **Queue jobs** للمهام الثقيلة.
- **JSON columns** للبيانات شبه المهيكلة.
- **chunkById** للمعالجة الدفعية.

### Vue.js
- **Composition API** حصرياً.
- **Composables** لإعادة الاستخدام.
- **Inertia.js** للتنقل.
- **Tailwind CSS** للتنسيق.
- **مكونات مشتركة** فقط (لا تكرار).

### ZKTeco / Hikvision
- **pyzk** للجانب ZKTeco (الأكثر استقراراً للـ templates).
- **ISAPI + HTTP Digest** للجانب Hikvision.
- **حجم دفعة معقول** (50 سجل).
- **Timeout كافٍ** (30s افتراضي، 300s للـ bulk).

---

## التكامل مع الكود الموجود (Integration Map)

```
┌────────────────────────────────────────────────────────────┐
│ Vue Layer                                                  │
│  - resources/js/Pages/FingerprintDevices/Sync.vue (تعديل) │
│  - resources/js/Pages/FingerprintDevices/Index.vue (تعديل)│
│  - resources/js/Pages/FingerprintDevices/Partials/         │
│      QuickPushModal.vue (جديد)                             │
└────────────────────────────────────────────────────────────┘
        ↓ Inertia POST + SSE
┌────────────────────────────────────────────────────────────┐
│ Controller Layer                                           │
│  - DeviceFullSyncController::push() (جديد)                │
│  - DeviceFullSyncController::pushStream() (جديد)          │
│  - DeviceFullSyncController::pushAll() (جديد)             │
│  - DeviceFullSyncController::retryFailed() (جديد)          │
└────────────────────────────────────────────────────────────┘
        ↓ يفوّض
┌────────────────────────────────────────────────────────────┐
│ Service Layer                                              │
│  - DevicePushService (جديد) ── المنطق الأساسي              │
│  - DeviceFullSyncService (موجود، يُعاد استخدامه للسحب)     │
│  - FingerprintDeviceService (تعديل: pushUsers/             │
│      pushFingerprints)                                     │
└────────────────────────────────────────────────────────────┘
        ↓ يحل driver
┌────────────────────────────────────────────────────────────┐
│ Adapter Layer (موجود)                                      │
│  - DeviceAdapterResolver → ZKTecoAdapter | HikvisionAdapter│
└────────────────────────────────────────────────────────────┘
        ↓ HTTP
┌────────────────────────────────────────────────────────────┐
│ Bridge (Python, لا تعديل)                                  │
│  - zkteco-service/app.py (ZKTeco, port 5000)               │
│  - zkteco-service/hikvision_service.py (Hikvision, port    │
│      5001)                                                 │
└────────────────────────────────────────────────────────────┘
        ↓ pyzk / ISAPI
┌────────────────────────────────────────────────────────────┐
│ Physical Device                                            │
│  - ZKTeco Time (port 4370)                                 │
│  - Hikvision Access Control (port 80/443)                  │
└────────────────────────────────────────────────────────────┘
```

---

## المخاطر المتبقية (Residual Risks)

| المخاطرة | التخفيف في التصميم |
|---------|-------------------|
| الجسر Python قد يكون خارج الشبكة | health check قبل الدفع + رسالة واضحة |
| بصمة بصيغة مختلفة بين ZKTeco/Hikvision | حفظ `template_format` في `user_fingerprints` |
| 1000+ موظف بدون Queue | Queue تلقائي لـ > 200 |
| فقدان progress (إغلاق المتصفح) | تخزين `sync_log_id` + استئناف اختياري |
| تكرار UID | `add_users_batch` يتولى البحث عن UID متاح في `pyzk` |

---

*آخر تحديث: 2026-07-20*
