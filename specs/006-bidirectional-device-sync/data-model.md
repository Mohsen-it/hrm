# نموذج البيانات - Bidirectional Fingerprint Device Sync

**التاريخ:** 2026-07-20
**الوحدة:** `Modules\FingerprintDevices`

---

## مخطط العلاقات (Entity-Relationship Diagram)

```
fingerprint_devices (1) ──── (N) device_sync_logs
                                    │
                                    └── (N) device_push_results

users (1) ──────────── (N) device_sync_logs (كمستخدم مُشغِّل)
users (1) ──────────── (N) device_push_results (كموظف مُستهدف)
fingerprint_devices (1) ──── (N) device_push_results

user_fingerprints (N) ──── (1) fingerprint_devices  (موجود، يُستخدم للدفع)
users (1) ──────────── (N) user_fingerprints         (موجود)
```

---

## الكيانات الجديدة (New Entities)

### 1. `DeviceSyncLog` — سجل عملية مزامنة

**الجدول:** `device_sync_logs`
**الملف:** `Modules\FingerprintDevices\database\migrations/2026_07_20_000002_create_device_sync_logs_table.php`

| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| `id` | bigint UNSIGNED | PK, auto-increment | المعرّف |
| `device_id` | bigint UNSIGNED | FK → `fingerprint_devices.id`, NOT NULL, ON DELETE CASCADE | الجهاز |
| `user_id` | bigint UNSIGNED | FK → `users.id`, NULLABLE, ON DELETE SET NULL | المستخدم الذي شغّل المزامنة (NULL = نظام) |
| `direction` | enum(`pull`, `push`, `bidirectional`) | NOT NULL, default `pull` | نوع العملية |
| `steps` | json | NULLABLE | تفاصيل المراحل (نفس بنية `DeviceFullSyncService::$result['steps']`) |
| `totals` | json | NULLABLE | مجاميع: `users_matched`, `fingerprints_saved`, `pushed_users`, `pushed_fingerprints` |
| `errors` | json | NULLABLE | مصفوفة من النصوص |
| `started_at` | timestamp | NOT NULL | وقت البدء |
| `finished_at` | timestamp | NULLABLE | وقت الانتهاء |
| `duration_seconds` | decimal(10,2) | NULLABLE | المدة بالثواني |
| `status` | enum(`running`, `completed`, `failed`, `partial`) | NOT NULL, default `running` | الحالة النهائية |
| `created_at` | timestamp | NOT NULL | وقت الإنشاء |
| `updated_at` | timestamp | NOT NULL | وقت التعديل |

**الفهارس (Indexes):**
- `INDEX (device_id, started_at)` — لاستعلام "آخر مزامنة لجهاز".
- `INDEX (status)` — لفلترة المزامنات الفاشلة/الناجحة.
- `INDEX (user_id, started_at)` — لتاريخ المستخدم.
- `INDEX (direction, started_at)` — لتقارير السحب/الدفع.

**العلاقات (Relations):**
- `device()` → `BelongsTo(FingerprintDevice::class, 'device_id')`
- `user()` → `BelongsTo(User::class, 'user_id')` (nullable)
- `pushResults()` → `HasMany(DevicePushResult::class, 'sync_log_id')`

**الأوسمة (Scopes):**
- `scopeForDevice(Builder $q, int $deviceId)` — لسجلات جهاز معين.
- `scopeCompleted(Builder $q)` — المكتملة فقط.
- `scopeFailed(Builder $q)` — الفاشلة.
- `scopePushed(Builder $q)` — الدفع فقط (`direction IN ('push', 'bidirectional')`).

**الوصولات (Accessors):**
- `getDurationHumanAttribute(): string` — "5s", "2m 15s".

---

### 2. `DevicePushResult` — نتيجة دفع سجل واحد

**الجدول:** `device_push_results`
**الملف:** `Modules\FingerprintDevices\database\migrations/2026_07_20_000003_create_device_push_results_table.php`

| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| `id` | bigint UNSIGNED | PK, auto-increment | المعرّف |
| `sync_log_id` | bigint UNSIGNED | FK → `device_sync_logs.id`, NOT NULL, ON DELETE CASCADE | المزامنة الأم |
| `device_id` | bigint UNSIGNED | FK → `fingerprint_devices.id`, NOT NULL, ON DELETE CASCADE | الجهاز (denormalized لتسريع الاستعلام) |
| `record_type` | enum(`user`, `fingerprint`, `face_photo`) | NOT NULL | نوع السجل |
| `target_user_id` | bigint UNSIGNED | FK → `users.id`, NULLABLE, ON DELETE SET NULL | الموظف المستهدف |
| `target_finger_id` | int UNSIGNED | NULLABLE | رقم البصمة (0-9) للـ `record_type = fingerprint` |
| `device_uid` | int UNSIGNED | NULLABLE | UID على الجهاز (يُملأ بعد الدفع الناجح) |
| `status` | enum(`success`, `failed`, `skipped`) | NOT NULL | النتيجة |
| `error_message` | text | NULLABLE | سبب الفشل (max 1000 chars) |
| `attempted_at` | timestamp | NOT NULL | وقت المحاولة |
| `retry_count` | tinyint UNSIGNED | NOT NULL, default 0 | عدد مرات إعادة المحاولة |
| `created_at` | timestamp | NOT NULL | وقت الإنشاء |
| `updated_at` | timestamp | NOT NULL | وقت التعديل |

**الفهارس:**
- `INDEX (sync_log_id)` — لكل سجلات مزامنة معينة.
- `INDEX (device_id, record_type, status)` — لفلترة الفشل في جهاز.
- `INDEX (target_user_id)` — لتاريخ موظف معين.
- `INDEX (status, attempted_at)` — لتقرير الفشل الأخير.

**العلاقات:**
- `syncLog()` → `BelongsTo(DeviceSyncLog::class, 'sync_log_id')`
- `device()` → `BelongsTo(FingerprintDevice::class, 'device_id')`
- `targetUser()` → `BelongsTo(User::class, 'target_user_id')`

**الأوسمة:**
- `scopeFailed(Builder $q)` — الفاشلة فقط (للـ retry).
- `scopeForDevice(Builder $q, int $deviceId)`.
- `scopeOfType(Builder $q, string $type)`.

---

## تعديلات على الكيانات الموجودة (Modifications to Existing Entities)

### 3. `fingerprint_devices` — أعمدة جديدة

**الملف:** `Modules\FingerprintDevices\database\migrations/2026_07_20_000001_add_last_pushed_at_to_fingerprint_devices_table.php`

```php
Schema::table('fingerprint_devices', function (Blueprint $table) {
    $table->timestamp('last_pushed_at')->nullable()->after('last_synced_at');
    $table->unsignedInteger('sync_log_count')->default(0)->after('last_pushed_at');
});
```

| العمود | النوع | الوصف |
|--------|------|------|
| `last_pushed_at` | timestamp, nullable | آخر دفع ناجح |
| `sync_log_count` | int, default 0 | عداد (للتقارير/الـ caching) |

**تعديل `FingerprintDevice` Model:**
- إضافة إلى `$fillable` و `$casts`.
- إضافة علاقة `pushResults()` → `HasMany(DevicePushResult::class, 'device_id')`.
- إضافة علاقة `syncLogs()` → `HasMany(DeviceSyncLog::class, 'device_id')`.
- إضافة accessor `getLastPushedAtHumanAttribute()`.

---

## الكيانات الموجودة المُستخدمة (Reused — No Changes)

| الكيان | الاستخدام |
|--------|----------|
| `User` | مصدر بيانات الدفع (الاسم، الكود) |
| `UserFingerprint` | مصدر `template_data` للبصمات |
| `FingerprintDevice` | هدف الدفع + إعدادات الاتصال |
| `FingerprintDeviceType` | يحدد الـ driver (zkteco/hikvision) |
| `Branch` | فلترة الموظفين حسب الفرع |

---

## قواعد التحقق (Validation Rules)

| الحقل | القاعدة |
|------|---------|
| `device_sync_logs.direction` | enum محدد (3 قيم) |
| `device_sync_logs.status` | enum محدد (4 قيم) |
| `device_push_results.record_type` | enum محدد (3 قيم) |
| `device_push_results.status` | enum محدد (3 قيم) |
| `device_push_results.target_finger_id` | 0–9 (إذا كان نوع السجل `fingerprint`) |
| `device_push_results.retry_count` | 0–3 (الحد الأقصى لعدد المحاولات) |
| `device_sync_logs.user_id` | nullable (NULL = عملية نظام) |
| `device_push_results.error_message` | max 1000 حرف |

---

## انتقال الحالة (State Transitions)

### `device_sync_logs.status`

```
[NEW] → running ─→ completed
              ├──→ failed (خطأ كارثي)
              └──→ partial (بعض المراحل فشلت)
```

**القواعد:**
- `running` → `completed` فقط إذا انتهت كل المراحل بنجاح.
- `running` → `failed` إذا فشل الاتصال بالجهاز أو خطأ غير قابل للاستئناف.
- `running` → `partial` إذا فشلت مرحلة واحدة على الأقل لكن الباقي نجح.

### `device_push_results.status`

```
[NEW] → success
     ├──→ failed → (retry) → success | failed
     └──→ skipped (شرط مسبق غير محقق، مثل employee_code فارغ)
```

**القواعد:**
- `success` لا يتغير.
- `failed` يمكن أن يصبح `success` عبر `retry`.
- `skipped` لا يُعاد (الشرط المسبق ثابت).

---

## علاقات الكيانات (Relationship Matrix)

| الكيان | علاقة مع | نوع العلاقة | عبر |
|--------|----------|-------------|-----|
| `DeviceSyncLog` | `FingerprintDevice` | BelongsTo | `device_id` |
| `DeviceSyncLog` | `User` | BelongsTo (nullable) | `user_id` |
| `DeviceSyncLog` | `DevicePushResult` | HasMany | `sync_log_id` |
| `DevicePushResult` | `DeviceSyncLog` | BelongsTo | `sync_log_id` |
| `DevicePushResult` | `FingerprintDevice` | BelongsTo | `device_id` |
| `DevicePushResult` | `User` | BelongsTo (nullable) | `target_user_id` |
| `FingerprintDevice` | `DeviceSyncLog` | HasMany | `device_id` |
| `FingerprintDevice` | `DevicePushResult` | HasMany | `device_id` |

---

## مثال على البيانات (Sample Data)

```json
// device_sync_logs
{
  "id": 1,
  "device_id": 1,
  "user_id": 5,
  "direction": "bidirectional",
  "steps": [
    {"name": "info", "status": "ok", "message": "Device info refreshed"},
    {"name": "pull_users", "status": "ok", "message": "10 matched"},
    {"name": "push_users", "status": "partial", "message": "8 success, 2 failed"}
  ],
  "totals": {
    "users_matched": 10,
    "fingerprints_saved": 8,
    "attendance_saved": 50,
    "pushed_users": 8,
    "pushed_fingerprints": 6
  },
  "errors": ["push_users: User 102 failed: timeout"],
  "started_at": "2026-07-20T10:00:00Z",
  "finished_at": "2026-07-20T10:05:30Z",
  "duration_seconds": 330.5,
  "status": "partial"
}
```

```json
// device_push_results
{
  "id": 1,
  "sync_log_id": 1,
  "device_id": 1,
  "record_type": "user",
  "target_user_id": 102,
  "target_finger_id": null,
  "device_uid": 5,
  "status": "success",
  "error_message": null,
  "attempted_at": "2026-07-20T10:02:15Z",
  "retry_count": 0
}
```

---

*آخر تحديث: 2026-07-20*
