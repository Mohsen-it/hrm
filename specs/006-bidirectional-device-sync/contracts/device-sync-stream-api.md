# عقد API المزامنة مع البث المباشر (SSE)
# Device Sync Stream API Contract

**التاريخ:** 2026-07-20
**Module:** `Modules\FingerprintDevices`
**Base Path:** `/fingerprint-devices/sync`

---

## نظرة عامة

هذا العقد يوحّد جميع مسارات المزامنة التي تستخدم **Server-Sent Events (SSE)** لبث التقدم في الوقت الفعلي. يغطي:

1. `POST /sync/stream` — سحب فقط (موجود، يُوثَّق للمرجعية)
2. `POST /sync/push-stream` — دفع فقط (جديد)
3. `POST /sync/bidirectional-stream` — سحب + دفع (جديد)
4. `POST /sync/push-all-stream` — دفع لجميع الأجهزة (جديد)

---

## البنية الموحدة (Unified Event Schema)

كل SSE event يتبع نفس البنية:

```
event: <event_name>
data: <json_payload>
```

### Event Types

| Event | الوصف | الـ Payload |
|-------|------|-------------|
| `start` | بداية المزامنة | `{device_id, device_name, total_steps}` |
| `progress` | تحديث مرحلة | `{step, status, message, percent, data?}` |
| `done` | اكتملت | `{success, sync_log_id, summary, totals, errors?}` |
| `error` | خطأ كارثي | `{message, code?}` |
| `cancel` | أُلغيت من المستخدم | `{message}` |

### Step Names (Common)

| Step | الوصف | direction |
|------|------|-----------|
| `info` | جلب معلومات الجهاز | pull |
| `pull_users` | سحب المستخدمين | pull |
| `pull_fingerprints` | سحب البصمات | pull |
| `pull_attendance` | سحب الحضور | pull |
| `pull_face_photos` | سحب صور الوجه | pull |
| `push_users` | دفع المستخدمين | push |
| `push_fingerprints` | دفع البصمات | push |
| `push_face_photos` | دفع صور الوجه | push |
| `done` | اكتمل | - |

### Status Values

| Status | الوصف |
|--------|------|
| `running` | قيد التنفيذ |
| `ok` | نجح |
| `failed` | فشل |
| `skipped` | تم تخطيه (الإعداد غير مفعّل) |
| `partial` | نجح جزئياً |

---

## 1. POST /fingerprint-devices/sync/stream (موجود - Pull Only)

> مسار موجود مسبقاً في `DeviceFullSyncController::syncStream`. يُوثَّق هنا للتوحيد.

### Request

```json
{
  "device_id": 1,
  "options": {
    "info": true,
    "users": true,
    "fingerprints": true,
    "face_photos": true,
    "attendance": true,
    "clear_local_cache": false
  }
}
```

### SSE Flow

```
event: start
data: {"device_id":1,"device_name":"جهاز الاستقبال","total_steps":5}

event: progress
data: {"step":"info","status":"running","message":"...","percent":0}

event: progress
data: {"step":"info","status":"ok","message":"Device info refreshed","percent":15}

event: progress
data: {"step":"pull_users","status":"running","message":"جاري سحب الموظفين...","percent":25}

event: progress
data: {"step":"pull_users","status":"ok","message":"50 matched, 2 unmatched","percent":50}

event: progress
data: {"step":"pull_fingerprints","status":"running","message":"...","percent":60}

event: progress
data: {"step":"pull_attendance","status":"running","message":"...","percent":75}

event: done
data: {"success":true,"sync_log_id":40,"totals":{"users_matched":50,"fingerprints_saved":80,"attendance_saved":200}}
```

---

## 2. POST /fingerprint-devices/sync/push-stream (جديد - Push Only)

### Request

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

### SSE Flow

```
event: start
data: {"device_id":1,"device_name":"جهاز الاستقبال","total_steps":3,"direction":"push"}

event: progress
data: {"step":"info","status":"ok","message":"Device online","percent":10}

event: progress
data: {"step":"push_users","status":"running","message":"جاري دفع 50 موظف...","percent":30}

event: progress
data: {"step":"push_users","status":"partial","message":"45 success, 5 failed","percent":70,"pushed":45,"failed":5}

event: progress
data: {"step":"push_fingerprints","status":"running","message":"جاري دفع 45 بصمة...","percent":80}

event: progress
data: {"step":"push_fingerprints","status":"ok","message":"43 success, 2 failed","percent":95,"pushed":43,"failed":2}

event: done
data: {
  "success": true,
  "sync_log_id": 41,
  "summary": {
    "pushed_users": 45,
    "pushed_fingerprints": 43,
    "failed_users": 5,
    "failed_fingerprints": 2,
    "duration_seconds": 18.4
  },
  "errors": [
    "User 102: Device timeout",
    "User 205: Invalid template format"
  ]
}
```

### السلوك
- إذا `> 200` موظف → الـ Controller يضع في Queue ويرسل `event: queued` بدلاً من البث.
- `event: queued` يرسل `sync_log_id` placeholder، والتقدم الفعلي يأتي من polling endpoint.

---

## 3. POST /fingerprint-devices/sync/bidirectional-stream (جديد)

### Request

```json
{
  "device_id": 1,
  "options": {
    "pull": {
      "info": true,
      "users": true,
      "fingerprints": true,
      "attendance": true
    },
    "push": {
      "users": true,
      "fingerprints": true
    }
  }
}
```

### SSE Flow

```
event: start
data: {"device_id":1,"total_steps":6,"direction":"bidirectional"}

event: progress
data: {"step":"info","status":"ok","percent":5}

event: progress
data: {"step":"pull_users","status":"ok","percent":20}

event: progress
data: {"step":"pull_fingerprints","status":"ok","percent":35}

event: progress
data: {"step":"pull_attendance","status":"ok","percent":50}

event: progress
data: {"step":"push_users","status":"ok","percent":75}

event: progress
data: {"step":"push_fingerprints","status":"ok","percent":95}

event: done
data: {
  "success": true,
  "sync_log_id": 42,
  "summary": {
    "pull": {
      "users_matched": 50,
      "fingerprints_saved": 80,
      "attendance_saved": 200
    },
    "push": {
      "pushed_users": 48,
      "pushed_fingerprints": 75,
      "failed": 3
    },
    "duration_seconds": 45.2
  }
}
```

### ترتيب المراحل
1. `info` (5%)
2. `pull_users` (5-25%)
3. `pull_fingerprints` (25-40%)
4. `pull_attendance` (40-55%)
5. `push_users` (55-80%)
6. `push_fingerprints` (80-100%)

---

## 4. POST /fingerprint-devices/sync/push-all-stream (جديد)

دفع لجميع الأجهزة النشطة، مع بث تقدم كل جهاز.

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

### SSE Flow

```
event: start
data: {"total_devices":5,"total_steps":2}

event: device_start
data: {"device_id":1,"device_name":"جهاز المبنى الرئيسي","step":"push_users"}

event: device_progress
data: {"device_id":1,"step":"push_users","status":"ok","pushed":50,"failed":2}

event: device_progress
data: {"device_id":1,"step":"push_fingerprints","status":"ok","pushed":45,"failed":1}

event: device_complete
data: {"device_id":1,"success":true,"sync_log_id":42}

event: device_start
data: {"device_id":2,"device_name":"جهاز المبنى الثانوي","step":"push_users"}

event: device_progress
data: {"device_id":2,"step":"push_users","status":"failed","error":"Device offline"}

event: device_complete
data: {"device_id":2,"success":false,"error":"Device offline"}

... (للأجهزة الأخرى) ...

event: done
data: {
  "success": true,
  "total_devices": 5,
  "succeeded": 4,
  "failed": 1,
  "totals": {
    "pushed_users": 198,
    "pushed_fingerprints": 180,
    "failed_users": 7
  }
}
```

---

## Client-Side Parsing

### Vue 3 Composable Example

```javascript
// composables/useSSE.js
export function useSSE(url, payload) {
  const events = ref([])
  const isRunning = ref(false)
  const progress = ref(0)
  const currentStep = ref('')
  const result = ref(null)
  const error = ref(null)

  const start = async () => {
    isRunning.value = true
    const csrf = document.querySelector('meta[name="csrf-token"]').content

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        'X-CSRF-TOKEN': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })

    if (!response.ok) {
      error.value = `HTTP ${response.status}`
      isRunning.value = false
      return
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    while (true) {
      const { done, value } = await reader.read()
      if (done) break

      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop() || ''

      let eventType = ''
      let eventData = ''

      for (const line of lines) {
        if (line.startsWith('event: ')) eventType = line.slice(7).trim()
        else if (line.startsWith('data: ')) eventData = line.slice(6)
        else if (line === '' && eventType && eventData) {
          handleEvent(eventType, JSON.parse(eventData))
          eventType = ''; eventData = ''
        }
      }
    }

    isRunning.value = false
  }

  const handleEvent = (type, data) => {
    events.value.push({ type, data })
    if (type === 'progress') {
      currentStep.value = data.step
      progress.value = data.percent
    } else if (type === 'done') {
      result.value = data
    } else if (type === 'error') {
      error.value = data.message
    }
  }

  return { events, isRunning, progress, currentStep, result, error, start }
}
```

---

## اعتبارات الأداء والأمان

### الأداء
- **Heartbeat**: كل 15 ثانية يرسل `:heartbeat\n\n` لمنع proxy timeout.
- **Backpressure**: إذا العميل بطيء، الـ PHP يُبطئ الإنتاج عبر `flush()`.
- **Max Duration**: 30 دقيقة (لـ 1000+ سجل)، بعدها `event: done` قسري.

### الأمان
- كل request يتحقق من `auth` و `edit-fingerprint-devices`.
- CSRF token في الـ headers.
- لا secrets في الـ events (الـ `error_message` آمن للعرض).
- `Access-Control-Allow-Origin: same-origin` فقط.

### Reliability
- **Reconnect**: العميل (EventSource) يتصل تلقائياً عند الانقطاع، لكن POST events لا تُعاد (طبيعة SSE).
- **Status check**: endpoint بديل `GET /sync/log/{id}` لاسترجاع حالة المزامنة بعد الانقطاع.

---

*آخر تحديث: 2026-07-20*
