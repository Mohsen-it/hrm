# عقد مورد الجهاز (FingerprintDeviceResource)
# FingerprintDevice Resource Contract

**التاريخ:** 2026-07-20
**Module:** `Modules\FingerprintDevices`

---

## نظرة عامة

`FingerprintDeviceResource` يحوّل موديل `FingerprintDevice` إلى JSON للواجهة. مع الميزة الجديدة، يُضاف:
- `last_pushed_at` (مع accessor بصيغة بشرية)
- `sync_log_count` (counter)
- `last_sync_log` (آخر سجل مزامنة كامل)
- `push_capabilities` (ما يدعمه الجهاز)

---

## الملف

`Modules\FingerprintDevices\app\Http\Resources\FingerprintDeviceResource.php`

---

## المخرجات (Output)

### الحقول الموجودة (محفوظة)

| الحقل | النوع | المصدر |
|------|------|--------|
| `id` | int | `$this->id` |
| `name` | string | `$this->name` |
| `serial_number` | string | `$this->serial_number` |
| `ip_address` | string | `$this->ip_address` |
| `port` | int | `$this->port` |
| `status` | string | `$this->status` |
| `connection_type` | string | `$this->connection_type` |
| `timeout` | int | `$this->timeout` |
| `device_type` | object | `$this->whenLoaded('deviceType')` |
| `branch` | object | `$this->whenLoaded('branch')` |
| `user_count` | int | `$this->user_count` |
| `fingerprint_count` | int | `$this->fingerprint_count` |
| `attendance_log_count` | int | `$this->attendance_log_count` |
| `last_seen_at` | string | `$this->last_seen_at?->toDateTimeString()` |
| `last_synced_at` | string | `$this->last_synced_at?->toDateTimeString()` |
| `capabilities` | array | `$this->capabilities` |
| `is_push_enabled` | bool | `$this->is_push_enabled` |

### الحقول الجديدة (المضافة)

| الحقل | النوع | المصدر | الوصف |
|------|------|--------|------|
| `last_pushed_at` | string\|null | `$this->last_pushed_at` | آخر دفع ناجح |
| `last_pushed_at_human` | string | accessor | "منذ 5 دقائق" |
| `sync_log_count` | int | `$this->sync_log_count` | عدد المزامنات |
| `last_sync_log` | object\|null | `$this->whenLoaded('lastSyncLog')` | ملخص آخر مزامنة |
| `push_capabilities` | object | accessor | ما يدعمه الجهاز للدفع |
| `can_push_users` | bool | accessor | true إذا الـ driver يدعم الدفع |
| `can_push_fingerprints` | bool | accessor | true إذا الـ driver يدعم الدفع |
| `can_push_face_photos` | bool | accessor | true لـ Hikvision فقط |

---

## الكود (Implementation)

### Resource

```php
<?php

namespace Modules\FingerprintDevices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FingerprintDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'status' => $this->status,
            'connection_type' => $this->connection_type,
            'timeout' => $this->timeout,
            
            'device_type' => $this->whenLoaded('deviceType', fn() => [
                'id' => $this->deviceType->id,
                'name' => $this->deviceType->name,
                'manufacturer' => $this->deviceType->manufacturer,
                'default_port' => $this->deviceType->default_port,
            ]),
            
            'branch' => $this->whenLoaded('branch', fn() => [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ]),
            
            'user_count' => $this->user_count,
            'fingerprint_count' => $this->fingerprint_count,
            'attendance_log_count' => $this->attendance_log_count,
            
            'last_seen_at' => $this->last_seen_at?->toDateTimeString(),
            'last_seen_at_human' => $this->last_seen_at?->diffForHumans(),
            
            'last_synced_at' => $this->last_synced_at?->toDateTimeString(),
            'last_synced_at_human' => $this->last_synced_at?->diffForHumans(),
            
            // ===== حقول جديدة (المزامنة الثنائية) =====
            
            'last_pushed_at' => $this->last_pushed_at?->toDateTimeString(),
            'last_pushed_at_human' => $this->last_pushed_at?->diffForHumans(),
            
            'sync_log_count' => $this->sync_log_count ?? 0,
            
            'last_sync_log' => $this->whenLoaded('lastSyncLog', function () {
                $log = $this->lastSyncLog;
                if (!$log) return null;
                
                return [
                    'id' => $log->id,
                    'direction' => $log->direction,
                    'status' => $log->status,
                    'started_at' => $log->started_at?->toDateTimeString(),
                    'duration_seconds' => $log->duration_seconds,
                    'totals' => $log->totals,
                ];
            }),
            
            'push_capabilities' => [
                'users' => $this->can_push_users,
                'fingerprints' => $this->can_push_fingerprints,
                'face_photos' => $this->can_push_face_photos,
            ],
            
            'capabilities' => $this->capabilities,
            'is_push_enabled' => $this->is_push_enabled,
        ];
    }
}
```

### Accessors في `FingerprintDevice` Model

```php
// Modules\FingerprintDevices\app\Models/FingerprintDevice.php

public function getCanPushUsersAttribute(): bool
{
    $manufacturer = strtolower($this->deviceType?->manufacturer ?? '');
    
    return str_contains($manufacturer, 'zkteco')
        || str_contains($manufacturer, 'zk')
        || str_contains($manufacturer, 'hikvision')
        || str_contains($manufacturer, 'hik');
}

public function getCanPushFingerprintsAttribute(): bool
{
    return $this->can_push_users; // نفس الشيء حالياً
}

public function getCanPushFacePhotosAttribute(): bool
{
    $manufacturer = strtolower($this->deviceType?->manufacturer ?? '');
    
    return str_contains($manufacturer, 'hikvision')
        || str_contains($manufacturer, 'hik');
}

public function getLastSyncLogAttribute(): ?DeviceSyncLog
{
    return $this->syncLogs()
        ->latest('started_at')
        ->first();
}
```

### Relationship: `lastSyncLog`

```php
// في FingerprintDevice Model
public function syncLogs(): HasMany
{
    return $this->hasMany(DeviceSyncLog::class, 'device_id');
}
```

> ملاحظة: عند `->load('syncLogs')`، نأخذ آخر سجل عبر `->first()` يدوياً، أو نستخدم relationship مخصص.

---

## مثال على المخرجات

```json
{
  "id": 1,
  "name": "جهاز الاستقبال - المبنى الرئيسي",
  "serial_number": "MED7254500092",
  "ip_address": "192.168.10.240",
  "port": 4370,
  "status": "online",
  "connection_type": "tcp",
  "timeout": 30,
  "device_type": {
    "id": 1,
    "name": "ZKTeco Time",
    "manufacturer": "ZKTeco",
    "default_port": 4370
  },
  "branch": {
    "id": 1,
    "branch_name": "المبنى الرئيسي"
  },
  "user_count": 50,
  "fingerprint_count": 80,
  "attendance_log_count": 1250,
  "last_seen_at": "2026-07-20T09:30:00+03:00",
  "last_seen_at_human": "منذ 30 دقيقة",
  "last_synced_at": "2026-07-20T08:00:00+03:00",
  "last_synced_at_human": "منذ ساعتين",
  "last_pushed_at": "2026-07-19T14:30:00+03:00",
  "last_pushed_at_human": "منذ 18 ساعة",
  "sync_log_count": 24,
  "last_sync_log": {
    "id": 42,
    "direction": "bidirectional",
    "status": "partial",
    "started_at": "2026-07-20T08:00:00+03:00",
    "duration_seconds": 330.5,
    "totals": {
      "users_matched": 50,
      "pushed_users": 45,
      "pushed_fingerprints": 38
    }
  },
  "push_capabilities": {
    "users": true,
    "fingerprints": true,
    "face_photos": false
  },
  "capabilities": {
    "tcp": true,
    "udp": true,
    "fingerprint_upload": true
  },
  "is_push_enabled": true
}
```

---

## استخدام في الـ Vue

```vue
<!-- resources/js/Pages/FingerprintDevices/Index.vue -->
<DataTable :columns="columns" :data="devices">
    <template #cell-last_pushed_at="{ row }">
        <span v-if="row.last_pushed_at">
            {{ row.last_pushed_at_human }}
        </span>
        <span v-else class="text-mistral-stone">—</span>
    </template>
    
    <template #cell-actions="{ row }">
        <Button variant="primary" size="sm" @click="quickPush(row)">
            <i class="fas fa-cloud-upload-alt"></i> دفع سريع
        </Button>
    </template>
</DataTable>
```

---

## Eager Loading

في الـ Controller:

```php
$devices = $this->repository->query()
    ->with(['deviceType', 'branch', 'syncLogs' => function($q) {
        $q->latest('started_at')->limit(1);
    }])
    ->paginate(20);
```

> `syncLogs` المحدودة بأحدث سجل تتجنب N+1.

---

## الاختبار (Testing)

```php
// tests/Feature/FingerprintDeviceResourceTest.php

it('includes last_pushed_at and capabilities', function () {
    $device = FingerprintDevice::factory()->create([
        'last_pushed_at' => now()->subHour(),
    ]);
    $device->load('deviceType');
    
    $resource = (new FingerprintDeviceResource($device))->toArray(request());
    
    expect($resource['last_pushed_at'])->not->toBeNull();
    expect($resource['last_pushed_at_human'])->toContain('ساعة');
    expect($resource['push_capabilities']['users'])->toBeTrue();
});

it('returns false for face_photos push on ZKTeco', function () {
    $deviceType = FingerprintDeviceType::factory()->create([
        'manufacturer' => 'ZKTeco',
    ]);
    $device = FingerprintDevice::factory()->create([
        'device_type_id' => $deviceType->id,
    ]);
    
    expect($device->fresh()->can_push_face_photos)->toBeFalse();
});
```

---

*آخر تحديث: 2026-07-20*
