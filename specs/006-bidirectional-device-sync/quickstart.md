# الدليل السريع - Bidirectional Fingerprint Device Sync

**التاريخ:** 2026-07-20
**الم_feature:** 006-bidirectional-device-sync

> هذا الدليل يهدف إلى **التحقق الشامل من الميزة end-to-end** عبر سيناريوهات قابلة للتشغيل. لا يحتوي على كود تنفيذي كامل (يوجد في `tasks.md` والمرحلة `/speckit.implement`).

---

## المتطلبات المسبقة (Prerequisites)

### البيئة (Environment)

```bash
# 1. PHP 8.3+, Composer, Node 20+
php --version
node --version
composer --version

# 2. قاعدة البيانات
# SQLite (dev) أو MySQL 8.0+ (prod)
php artisan migrate --pretend

# 3. تشغيل جسر Python
cd zkteco-service
# على Windows:
start.bat
# على Linux/macOS:
./start.sh
# أو يدوياً:
python app.py          # ZKTeco على port 5000
python hikvision_service.py  # Hikvision على port 5001
```

### فحص الجسر

```bash
# يجب أن يعود 200 OK
curl http://localhost:5000/health
# {"status":"ok","service":"ZKTeco Microservice","version":"1.0.0","pyzk_available":true}

curl http://localhost:5001/health
# {"status":"ok","service":"Hikvision ISAPI Microservice","version":"1.0.0"}
```

### تشغيل Migrations

```bash
# migrations الجديدة
php artisan migrate

# التحقق
php artisan tinker
>>> \DB::table('device_sync_logs')->count();  // 0
>>> \DB::table('device_push_results')->count();  // 0
>>> \Schema::hasColumn('fingerprint_devices', 'last_pushed_at');  // true
```

### تشغيل Queue Worker

```bash
# في تبويب منفصل
php artisan queue:work redis --queue=device-push,device-push-fingerprints --tries=3 --backoff=30 --timeout=1800 -v
```

---

## الخطوة 1: إعداد البيانات الاختبارية (Test Data)

### إضافة جهاز ZKTeco

```bash
php artisan tinker
```

```php
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\Branches\Models\Branch;

// تأكد من وجود نوع الجهاز
$zkType = FingerprintDeviceType::firstOrCreate(
    ['manufacturer' => 'ZKTeco', 'name' => 'ZKTeco Time'],
    ['default_port' => 4370, 'protocol' => 'zkteco']
);

$branch = Branch::firstOrCreate(
    ['branch_name' => 'المبنى الرئيسي - اختبار'],
    ['company_id' => 1, 'status' => 'active']
);

$zkDevice = FingerprintDevice::create([
    'device_type_id' => $zkType->id,
    'branch_id' => $branch->id,
    'name' => 'جهاز اختبار ZKTeco',
    'serial_number' => 'TEST-ZK-001',
    'ip_address' => '192.168.10.240',  // غيّر إلى IP جهازك
    'port' => 4370,
    'comm_key' => '0',
    'timeout' => 30,
    'connection_type' => 'tcp',
    'status' => 'online',
    'is_push_enabled' => true,
]);

echo "Device created: ID={$zkDevice->id}";
```

### إضافة جهاز Hikvision

```php
$hikType = FingerprintDeviceType::firstOrCreate(
    ['manufacturer' => 'Hikvision', 'name' => 'Hikvision Access Control'],
    ['default_port' => 80, 'protocol' => 'hikvision']
);

$hikDevice = FingerprintDevice::create([
    'device_type_id' => $hikType->id,
    'branch_id' => $branch->id,
    'name' => 'جهاز اختبار Hikvision',
    'serial_number' => 'TEST-HIK-001',
    'ip_address' => '192.168.10.241',  // غيّر إلى IP جهازك
    'port' => 80,
    'comm_key' => 'admin:password123',  // username:password
    'timeout' => 30,
    'connection_type' => 'http',
    'status' => 'online',
    'is_push_enabled' => true,
]);
```

### إنشاء موظفين اختباريين

```php
use Modules\Users\Models\User;

$users = [];
for ($i = 1; $i <= 10; $i++) {
    $users[] = User::create([
        'employee_code' => 'TEST-EMP-'.str_pad($i, 3, '0', STR_PAD_LEFT),
        'name' => 'موظف اختبار '.$i,
        'full_name_ar' => 'موظف اختبار '.$i,
        'email' => "test_emp_{$i}@hrm.local",
        'password' => bcrypt('password'),
        'status' => 1,
        'is_active_employee' => true,
    ]);
}

echo "Created ".count($users)." users";
```

---

## الخطوة 2: اختبار دفع المستخدمين إلى ZKTeco

### السيناريو 2.1 — دفع 10 موظفين (synchronous)

```bash
# تسجيل الدخول كمدير في الواجهة أولاً
# ثم استخدم Tinker لمحاكاة الـ Controller
php artisan tinker
```

```php
use Modules\FingerprintDevices\Services\DevicePushService;
use Modules\FingerprintDevices\Models\FingerprintDevice;

$device = FingerprintDevice::find(1);  // ZKTeco
$service = app(DevicePushService::class);

$result = $service->pushUsers(
    deviceId: $device->id,
    userIds: User::where('employee_code', 'like', 'TEST-EMP-%')->pluck('id')->toArray(),
    options: ['push_users' => true, 'push_fingerprints' => false],
);

print_r($result->toArray());
```

**المتوقع:**
```php
[
    'success' => true,
    'sync_log_id' => 1,
    'summary' => [
        'pushed_users' => 10,
        'failed_users' => 0,
        'skipped_users' => 0,
        'duration_seconds' => 15.3,
    ],
]
```

### التحقق على الجهاز

على جهاز ZKTeco الفعلي:
- اذهب إلى `Menu > User Management > User List`.
- يجب أن ترى 10 مستخدمين بأرقام `TEST-EMP-001` إلى `TEST-EMP-010`.
- الأسماء بالعربية.

أو عبر جسر Python مباشرة:

```bash
curl -X POST http://localhost:5000/device/get-users \
  -H "Content-Type: application/json" \
  -d '{"ip":"192.168.10.240","port":4370,"password":0}'
```

**المتوقع:** مصفوفة `users` فيها 10 سجلات (أو أكثر إذا كان فيه مستخدمين سابقين).

### التحقق من DB

```php
>>> \Modules\FingerprintDevices\Models\DeviceSyncLog::count();
// 1

>>> \Modules\FingerprintDevices\Models\DeviceSyncLog::latest()->first()->status;
// "completed"

>>> \Modules\FingerprintDevices\Models\DevicePushResult::where('status', 'success')->count();
// 10

>>> \Modules\FingerprintDevices\Models\FingerprintDevice::find(1)->last_pushed_at;
// 2026-07-20 10:30:00 (Carbon instance)
```

---

## الخطوة 3: اختبار دفع البصمات

### إضافة بصمات للموظفين

```php
// إضافة بصمة وهمية (في الإنتاج تُسحب من جهاز أولاً)
use Modules\FingerprintDevices\Models\UserFingerprint;

foreach (User::where('employee_code', 'like', 'TEST-EMP-%')->get() as $user) {
    // template_data من المفترض أن يكون base64 من قالب حقيقي
    // للاختبار، استخدم قالب تجريبي
    UserFingerprint::create([
        'user_id' => $user->id,
        'device_id' => $device->id,
        'finger_id' => 0,
        'template_data' => 'BASE64_ENCODED_TEMPLATE_DATA_HERE',
        'template_format' => 'zkteco-base64',
        'template_version' => 9,
        'is_master' => true,
        'captured_at' => now(),
        'synced_at' => now(),
    ]);
}
```

> **ملاحظة:** في الإنتاج، البصمة تُسحَب من الجهاز أولاً عبر `DeviceFullSyncService::stepFingerprints`. للاختبار الحقيقي، استخدم بصمة من جهاز فعلي.

### دفع البصمات

```php
$service = app(DevicePushService::class);

$result = $service->pushFingerprints(
    deviceId: $device->id,
    userIds: User::where('employee_code', 'like', 'TEST-EMP-%')->pluck('id')->toArray(),
    options: ['push_fingerprints' => true],
);

print_r($result->toArray());
```

**المتوقع:**
```php
[
    'success' => true,
    'sync_log_id' => 2,
    'summary' => [
        'pushed_fingerprints' => 10,  // أو أقل إذا firmware لا يدعم
        'failed_fingerprints' => 0,
        'duration_seconds' => 12.5,
    ],
]
```

> **ملاحظة:** بعض أجهزة ZKTeco القديمة لا تدعم رفع البصمات عبر الشبكة. في هذه الحالة، كل السجلات ستكون `failed` مع `error_message: "Device timeout - firmware does not support template upload"`.

---

## الخطوة 4: اختبار دفع إلى Hikvision

```php
$hikDevice = FingerprintDevice::find(2);  // Hikvision

$result = $service->pushUsers(
    deviceId: $hikDevice->id,
    userIds: User::limit(5)->pluck('id')->toArray(),
    options: ['push_users' => true],
);

print_r($result->toArray());
```

**التحقق على جهاز Hikvision:**
- ادخل إلى `Person > Person List` في واجهة الويب.
- يجب أن ترى الموظفين.

**التحقق من DB:**
```php
>>> \Modules\FingerprintDevices\Models\DeviceSyncLog::where('device_id', 2)->count();
// 1

>>> \Modules\FingerprintDevices\Models\DeviceSyncLog::where('device_id', 2)->first()->status;
// "completed"
```

---

## الخطوة 5: اختبار المزامنة الثنائية (Bidirectional)

### عبر الواجهة (Vue)

1. افتح `http://localhost:8000/fingerprint-devices/sync?device_id=1`.
2. اختر الجهاز ZKTeco.
3. فعّل **كل** الخيارات:
   - ☑ معلومات الجهاز
   - ☑ سحب المستخدمين
   - ☑ سحب البصمات
   - ☑ سحب الحضور
   - ☑ **دفع المستخدمين** (جديد)
   - ☑ **دفع البصمات** (جديد)
4. اضغط "تشغيل المزامنة".

**المتوقع:**
- شريط التقدم يمر بـ 6 مراحل (info → pull_users → pull_fingerprints → pull_attendance → push_users → push_fingerprints).
- في النهاية، بطاقة ملخص تعرض:
  - السحب: `50 موظف، 80 بصمة، 200 سجل حضور`.
  - الدفع: `48 موظف، 75 بصمة، فشل 2`.

### عبر Tinker (E2E Test)

```php
// تشغيل Pull + Push كاملين
$service = app(\Modules\FingerprintDevices\Services\DeviceSyncOrchestratorExtension::class);

$result = $service->runBidirectional(
    deviceId: $device->id,
    pullOptions: ['info' => true, 'users' => true, 'fingerprints' => true, 'attendance' => true],
    pushOptions: ['push_users' => true, 'push_fingerprints' => true],
);

print_r($result->toArray());
```

**المتوقع:**
- `result.direction === 'bidirectional'`
- `result.totals.pulled.users_matched > 0`
- `result.totals.pushed.pushed_users > 0`

---

## الخطوة 6: اختبار الدفع الانتقائي (Selective)

### حسب قائمة IDs

```php
$result = $service->pushUsers(
    deviceId: $device->id,
    userIds: [1, 2, 3],  // 3 موظفين فقط
    options: ['push_users' => true],
);

// summary.pushed_users === 3
```

### حسب الفرع

```php
$result = $service->pushUsersByBranch(
    deviceId: $device->id,
    branchId: 1,
    options: ['push_users' => true],
);
```

### الموظفين الذين ليس لديهم UID على الجهاز

```php
$result = $service->pushUsersMissing(
    deviceId: $device->id,
    options: ['push_users' => true],
);
```

---

## الخطوة 7: اختبار إعادة المحاولة (Retry)

### السيناريو 7.1 — فشل + retry ناجح

```php
// محاكاة فشل: أطفئ الجهاز فعلياً أو غيّر IP
$device->update(['ip_address' => '192.168.99.99']);  // IP غير موجود

$result = $service->pushUsers(
    deviceId: $device->id,
    userIds: range(1, 5),
    options: ['push_users' => true],
);

// summary.failed_users === 5
// log.status === 'failed'

$logId = $result->syncLogId;

// إعادة IP الصحيح
$device->update(['ip_address' => '192.168.10.240']);

// إعادة المحاولة
$retried = $service->retryFailed($logId);

// retried.succeeded === 5
// retried.still_failing === 0
```

### التحقق من DB

```php
// قبل الـ retry
>>> \Modules\FingerprintDevices\Models\DevicePushResult::where('sync_log_id', $logId)->where('status', 'failed')->count();
// 5

// بعد الـ retry
>>> \Modules\FingerprintDevices\Models\DeviceSyncLog::count();
// 2 (الأصلي + الـ retry log جديد)

>>> \Modules\FingerprintDevices\Models\DevicePushResult::where('status', 'success')->where('sync_log_id', '!=', $logId)->count();
// 5
```

---

## الخطوة 8: اختبار Queue Job (>200 موظف)

```php
// إنشاء 250 موظف
$bigUsers = [];
for ($i = 100; $i < 350; $i++) {
    $bigUsers[] = User::create([
        'employee_code' => 'BIG-EMP-'.$i,
        'name' => 'موظف كبير '.$i,
        'full_name_ar' => 'موظف كبير '.$i,
        'email' => "big_emp_{$i}@hrm.local",
        'password' => bcrypt('password'),
        'status' => 1,
        'is_active_employee' => true,
    ]);
}

$userIds = collect($bigUsers)->pluck('id')->toArray();
echo "Created ".count($userIds)." users\n";

// اطلب الدفع (سيتم وضعه في Queue)
$response = $this->postJson('/fingerprint-devices/sync/push', [
    'device_id' => $device->id,
    'options' => [
        'push_users' => true,
        'user_ids' => $userIds,
    ],
]);

// يجب أن يكون queued
echo $response->status();  // 202
echo $response->json('queued');  // true
echo $response->json('estimated_count');  // 250
$logId = $response->json('sync_log_id');
```

### في Worker (في تبويب منفصل)

```bash
php artisan queue:work redis --queue=device-push -v
```

**المتوقع:**
```
[2026-07-20 11:00:00] Processing: Modules\FingerprintDevices\Jobs\PushUsersToDeviceJob
[2026-07-20 11:00:30] Processed:  Modules\FingerprintDevices\Jobs\PushUsersToDeviceJob
```

### تتبع التقدم

```bash
# في تبويب ثالث
watch -n 5 'php artisan tinker --execute="echo \Modules\FingerprintDevices\Models\DeviceSyncLog::find('$logId')->status;"'
```

يجب أن ينتقل من `running` → `completed` خلال 1-2 دقيقة.

### التحقق النهائي

```php
$result = \Modules\FingerprintDevices\Models\DeviceSyncLog::find($logId);
echo "Status: {$result->status}\n";
echo "Pushed: ".($result->totals['pushed_users'] ?? 0)."\n";
echo "Failed: ".($result->totals['failed_users'] ?? 0)."\n";
echo "Duration: {$result->duration_seconds}s\n";
```

**المتوقع:**
```
Status: completed
Pushed: 248
Failed: 2
Duration: 95.4s
```

---

## الخطوة 9: اختبار الواجهة (Vue E2E)

### 9.1 صفحة Sync

```bash
# تسجيل الدخول
# افتح المتصفح
# http://localhost:8000/fingerprint-devices/sync
```

**الفحوصات:**
- [ ] الجهاز يظهر في القائمة المنسدلة.
- [ ] جميع الـ checkboxes تعمل (`push_users`، `push_fingerprints`).
- [ ] الضغط على "تشغيل" يبدأ المزامنة.
- [ ] شريط التقدم يعرض 6 مراحل بالترتيب الصحيح.
- [ ] عند الانتهاء، الملخص يعرض:
  - **السحب:** users_matched, fingerprints_saved, attendance_saved
  - **الدفع:** pushed_users, pushed_fingerprints
- [ ] زر "إلغاء" يعمل ويوقف المزامنة.
- [ ] زر "إعادة محاولة الفاشل" يظهر فقط إذا `result.errors.length > 0`.

### 9.2 صفحة Index

```bash
http://localhost:8000/fingerprint-devices
```

**الفحوصات:**
- [ ] عمود `last_pushed_at` يظهر وقت آخر دفع.
- [ ] عمود `last_pushed_at_human` يظهر "منذ X".
- [ ] الأجهزة بدون دفع تعرض `—`.
- [ ] زر "دفع سريع" في عمود الإجراءات يفتح modal.
- [ ] Modal الدفع السريع يعرض:
  - عدد الموظفين المتوقع دفعهم
  - عدد البصمات المتوقعة
  - خيارات (مستخدمين/بصمات/كلاهما)
  - زر "تأكيد الدفع"

### 9.3 صفحة Show

```bash
http://localhost:8000/fingerprint-devices/1
```

**الفحوصات:**
- [ ] قسم "آخر المزامنات" يعرض آخر 10 سجلات.
- [ ] كل سجل يعرض: direction, status, duration, totals.
- [ ] الضغط على سجل يفتح تفاصيله (modal أو صفحة).

---

## الخطوة 10: اختبارات آلية (Automated Tests)

```bash
# تشغيل كل الاختبارات
php artisan test --filter=FingerprintDevices

# أو اختبار محدد
php artisan test --filter=DevicePushServiceTest
php artisan test --filter=BidirectionalSyncTest
```

### الاختبارات المتوقعة

```
✓ it pushes users to a ZKTeco device
✓ it pushes users to a Hikvision device
✓ it handles partial failure gracefully
✓ it dispatches queue job when pushing more than 200 users
✓ it runs synchronously for <= 200 users
✓ it pushes fingerprints after pushing users
✓ it retries failed push records
✓ it pushes users by branch
✓ it pushes only selected users
✓ it skips users without employee_code
✓ it updates last_pushed_at after successful push
✓ it records results in device_push_results
✓ it creates a sync log entry
✓ it includes push totals in the sync log
✓ it does not affect existing pull functionality
✓ it handles bridge unavailable error
✓ it handles device offline error
✓ it handles template format error
```

---

## الخطوة 11: معايير النجاح (Success Criteria Validation)

| المقياس | الهدف | كيف تتحقق |
|--------|------|----------|
| **SC-1** | 100 موظف → ZKTeco في < 5min | `time php artisan tinker --execute="app(DevicePushService::class)->pushUsers(...);"` |
| **SC-2** | 100 بصمة → Hikvision في < 7min | نفس الشيء مع `pushFingerprints` |
| **SC-3** | مزامنة ثنائية (100×2) في < 10min | `app(Orchestrator::class)->runBidirectional(...)` |
| **SC-4** | نجاح ≥ 95% | `DevicePushResult::where('status','success')->count() / total() >= 0.95` |
| **SC-5** | الفشل الجزئي لا يلغي | اختبر سيناريو 7.1 |
| **SC-6** | تقدم SSE < 2s | افتح DevTools → Network → EventStream وراقب التأخير |
| **SC-7** | استعلامات DB ≤ 15 | فعّل `DB::enableQueryLog()` واطبع في الـ Controller |
| **SC-8** | ترجمة عربية + إنجليزية | غيّر `APP_LOCALE=en` وأعد تحميل |
| **SC-9** | السحب لا يتأثر | شغّل `php artisan test --filter=DeviceFullSync` |
| **SC-10** | Queue > 200 موظف | سيناريو الخطوة 8 |

---

## استكشاف الأخطاء (Troubleshooting)

### الجسر Python لا يستجيب

```bash
# فحص
curl http://localhost:5000/health
# Connection refused? أعد تشغيل الجسر
cd zkteco-service
python app.py
```

### الجهاز offline

```bash
# فحص الاتصال
ping 192.168.10.240
# فحص المنفذ
telnet 192.168.10.240 4370
```

### فشل بصمة (template format)

```
device_push_results.error_message = "Device timeout - firmware does not support template upload"
```

**الحل:** استخدم الجهاز الفعلي للبصمة، أو حدّث firmware الجهاز.

### Queue لا يعمل

```bash
# فحص
php artisan queue:failed

# إعادة محاولة
php artisan queue:retry all

# مسح
php artisan queue:flush
```

### SSE لا يبث

افتح DevTools → Network → ابحث عن `EventStream`:
- إذا الحالة `pending` بدون أي event → مشكلة في الـ Controller.
- إذا الحالة `200 OK` بدون data → تحقق من `flush()`.

---

*آخر تحديث: 2026-07-20*
