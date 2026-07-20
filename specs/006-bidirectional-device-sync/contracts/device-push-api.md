# عقد API الدفع إلى أجهزة البصمة
# Device Push API Contract

**التاريخ:** 2026-07-20
**Module:** `Modules\FingerprintDevices`
**Base Path:** `/fingerprint-devices/sync`

---

## نظرة عامة

هذا العقد يوثّق نقاط النهاية (endpoints) الجديدة لدفع البيانات من تطبيق HRM إلى جهاز البصمة. جميع المسارات تتطلب:
- المصادقة عبر `auth` middleware
- إذن `edit-fingerprint-devices` (أو `push-fingerprint-devices` إذا تم تفعيله)
- `device_id` صحيح موجود في جدول `fingerprint_devices`

---

## 1. POST /fingerprint-devices/sync/push

دفع المستخدمين و/أو البصمات إلى جهاز واحد. ينشئ سجل مزامنة ويعمل بشكل synchronous حتى 200 سجل، أو يضع في Queue لما هو أكبر.

### Request

**Headers:**
- `Content-Type: application/json`
- `X-CSRF-TOKEN: <token>`
- `X-Requested-With: XMLHttpRequest` (لـ JSON response)

**Body:**
```json
{
  "device_id": 1,
  "options": {
    "push_users": true,
    "push_fingerprints": true,
    "push_face_photos": false,
    "user_ids": [1, 2, 3, 4, 5],
    "branch_id": null
  }
}
```

**Validation Rules:**
| الحقل | القاعدة |
|------|---------|
| `device_id` | `required`, `integer`, `exists:fingerprint_devices,id` |
| `options` | `required`, `array` |
| `options.push_users` | `nullable`, `boolean` |
| `options.push_fingerprints` | `nullable`, `boolean` |
| `options.push_face_photos` | `nullable`, `boolean` |
| `options.user_ids` | `nullable`, `array`, `max:3000` |
| `options.user_ids.*` | `integer`, `exists:users,id` |
| `options.branch_id` | `nullable`, `integer`, `exists:branches,id` |

**منطق الفلترة:**
- إذا تم تحديد `user_ids` → يُدفع هؤلاء فقط.
- إذا تم تحديد `branch_id` → يُدفع موظفو هذا الفرع.
- إذا لم يُحدد أي منهما → يُدفع كل الموظفين النشطين.
- الموظفين بدون `employee_code` يُتجاهلون (status = `skipped`).

### Response (Synchronous — <= 200 users)

**Status:** `200 OK`

```json
{
  "success": true,
  "queued": false,
  "sync_log_id": 42,
  "summary": {
    "pushed_users": 45,
    "pushed_fingerprints": 38,
    "failed_users": 2,
    "failed_fingerprints": 1,
    "skipped_users": 3,
    "duration_seconds": 18.4
  },
  "failed": [
    {
      "user_id": 102,
      "employee_code": "EMP-102",
      "record_type": "user",
      "error": "Device timeout after 30s"
    },
    {
      "user_id": 205,
      "employee_code": "EMP-205",
      "record_type": "fingerprint",
      "finger_id": 0,
      "error": "Invalid template format"
    }
  ]
}
```

### Response (Queued — > 200 users)

**Status:** `202 Accepted`

```json
{
  "success": true,
  "queued": true,
  "job_id": "uuid-here",
  "estimated_count": 500,
  "message": "تم وضع العملية في قائمة الانتظار. ستكتمل في الخلفية."
}
```

### Errors

| Code | السبب |
|------|------|
| `400` | خيارات الدفع كلها `false` |
| `403` | لا يوجد إذن |
| `404` | الجهاز غير موجود |
| `422` | فشل التحقق من المدخلات |
| `503` | الجسر Python (port 5000/5001) لا يستجيب |

---

## 2. POST /fingerprint-devices/sync/push-stream

نفس منطق `/push` لكن يرسل التقدم في الوقت الفعلي عبر **Server-Sent Events (SSE)**.

### Request
نفس body الخاص بـ `/push`.

### Response (SSE)

**Headers:**
```
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive
X-Accel-Buffering: no
```

**Events:**

```
event: start
data: {"device_name":"جهاز الاستقبال","total_users":45}

event: progress
data: {"step":"push_users","status":"running","message":"جاري الدفع...","percent":50}

event: progress
data: {"step":"push_users","status":"ok","message":"تم دفع 45 موظف","percent":60,"pushed":45,"failed":2}

event: progress
data: {"step":"push_fingerprints","status":"running","message":"جاري دفع البصمات...","percent":70}

event: done
data: {"success":true,"sync_log_id":42,"summary":{...}}

```

**Event Types:**

| Event | الوصف | الـ data |
|-------|------|----------|
| `start` | بداية المزامنة | `{device_name, total_users, total_fingerprints}` |
| `progress` | تحديث مرحلة | `{step, status, message, percent, ...}` |
| `done` | اكتملت العملية | `{success, sync_log_id, summary}` |
| `error` | خطأ كارثي | `{message}` |

**`step` values:**
- `info` — معلومات الجهاز
- `push_users` — دفع المستخدمين
- `push_fingerprints` — دفع البصمات
- `push_face_photos` — دفع صور الوجه
- `done` — اكتمل

---

## 3. POST /fingerprint-devices/sync/push-all

دفع إلى **كل الأجهزة النشطة** (`status != deactivated`).

### Request

```json
{
  "options": {
    "push_users": true,
    "push_fingerprints": true,
    "branch_id": null
  }
}
```

### Response

**Status:** `200 OK`

```json
{
  "success": true,
  "total_devices": 5,
  "results": [
    {
      "device_id": 1,
      "device_name": "جهاز الاستقبال - المبنى الرئيسي",
      "success": true,
      "pushed_users": 45,
      "pushed_fingerprints": 38,
      "duration_seconds": 18.4,
      "sync_log_id": 42
    },
    {
      "device_id": 2,
      "device_name": "جهاز الاستقبال - المبنى الثانوي",
      "success": false,
      "error": "Device offline"
    }
  ]
}
```

### السلوك
- كل جهاز يُعالَج **بالتوازي** (لا sequential) — Laravel job dispatch لكل جهاز.
- الفشل في جهاز واحد لا يلغي الباقي.
- النتيجة النهائية تحتوي على مجاميع مجمّعة.

---

## 4. POST /fingerprint-devices/sync/retry-failed/{logId}

إعادة محاولة السجلات الفاشلة فقط من مزامنة سابقة.

### Request

**URL Param:** `logId` — `device_sync_logs.id`

**Body (اختياري):**
```json
{
  "max_retries": 1
}
```

### Response

**Status:** `200 OK`

```json
{
  "success": true,
  "sync_log_id": 43,
  "retried_count": 5,
  "succeeded": 3,
  "still_failing": 2,
  "newly_failed": [
    {
      "user_id": 102,
      "record_type": "user",
      "error": "Device still not responding"
    }
  ]
}
```

### القواعد
- `DevicePushResult.retry_count` يزداد لكل محاولة.
- إذا وصل `retry_count` إلى 3 (الحد الأقصى)، يُعلَّم كـ `permanently_failed` ولا يُعاد.
- يُنشأ `device_sync_logs` جديد (لا يُحدَّث القديم) لتتبع تاريخ المحاولات.

---

## 5. GET /fingerprint-devices/sync/last-log/{deviceId}

عرض آخر سجل مزامنة لجهاز معين (لـ UI السريعة).

### Response

**Status:** `200 OK`

```json
{
  "device_id": 1,
  "last_sync": {
    "id": 42,
    "direction": "bidirectional",
    "status": "partial",
    "started_at": "2026-07-20T10:00:00Z",
    "duration_seconds": 330.5,
    "totals": {
      "users_matched": 50,
      "pushed_users": 45,
      "pushed_fingerprints": 38
    }
  }
}
```

---

## أكواد الأخطاء الموحدة (Error Codes)

| Code | الوصف | المعالجة |
|------|------|----------|
| `PUSH_BRIDGE_DOWN` | الجسر Python لا يستجيب | عرض رسالة "تأكد من تشغيل zkteco-service" |
| `PUSH_DEVICE_OFFLINE` | الجهاز لا يستجيب | عرض "تحقق من IP/Port وحالة الجهاز" |
| `PUSH_TIMEOUT` | عملية الدفع تجاوزت 30s | إعادة محاولة تلقائية مرة واحدة |
| `PUSH_INVALID_TEMPLATE` | بصمة بصيغة غير صالحة | تخطي + تسجيل |
| `PUSH_USER_EXISTS` | الموظف موجود (UID متعارض) | حل تلقائي عبر `add_users_batch` |
| `PUSH_PERMISSION_DENIED` | لا يوجد إذن | رسالة 403 |

---

## ملاحظات التنفيذ (Implementation Notes)

1. **Authorization**: كل endpoint يستخدم `$this->authorize('edit-fingerprint-devices')` في الـ Controller.
2. **CSRF**: كل POST يتطلب CSRF token.
3. **Rate Limiting**: `/push` و `/push-stream` لهما `throttle:10,1` (10 طلبات/دقيقة).
4. **Logging**: كل request يُسجَّل في `storage/logs/laravel.log` مع `device_id` و `user_id`.
5. **Idempotency**: لا توجد حماية من الإرسال المكرر (الطبيعة idempotent للدفع — device يعيّن نفس UID).

---

*آخر تحديث: 2026-07-20*
