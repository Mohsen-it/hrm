# عقد وظائف الطابور (Queue Jobs) للدفع
# Device Push Queue Job Contracts

**التاريخ:** 2026-07-20
**Module:** `Modules\FingerprintDevices`

---

## نظرة عامة

تُستخدم وظائف الطابور (Queue Jobs) عندما يكون عدد السجلات كبيراً (> 200) لتجنّب حجب المتصفح (FR-11، SC-10). هناك وظيفتان:

1. `PushUsersToDeviceJob` — دفع المستخدمين.
2. `PushFingerprintsToDeviceJob` — دفع البصمات.

---

## 1. PushUsersToDeviceJob

### الوصف
يدفع قائمة من الموظفين إلى جهاز بصمة واحد عبر الجسر.

### الملف
`Modules\FingerprintDevices\app\Jobs\PushUsersToDeviceJob.php`

### Constructor Payload

```php
public function __construct(
    public int $deviceId,
    public array $userIds,        // مصفوفة من users.id
    public ?int $syncLogId = null, // nullable — يُنشأ عند البدء
    public int $chunkSize = 50
) {}
```

### Configuration

```php
public int $tries = 3;
public int $backoff = 30;        // 30 ثانية بين المحاولات
public int $timeout = 1800;      // 30 دقيقة
public string $queue = 'device-push';
```

### Handle Method

```php
public function handle(DevicePushService $service): void
{
    $service->pushUsersBatch(
        deviceId: $this->deviceId,
        userIds: $this->userIds,
        syncLogId: $this->syncLogId,
        chunkSize: $this->chunkSize
    );
}
```

### Failure Handler

```php
public function failed(\Throwable $exception): void
{
    Log::error('PushUsersToDeviceJob failed permanently', [
        'device_id' => $this->deviceId,
        'user_count' => count($this->userIds),
        'error' => $exception->getMessage(),
    ]);
    
    // تحديث sync_log بالحالة failed
    if ($this->syncLogId) {
        DeviceSyncLog::where('id', $this->syncLogId)
            ->update(['status' => 'failed']);
    }
}
```

### Middleware (Rate Limiting)

```php
public function middleware(): array
{
    return [
        new RateLimited('device-push'),
    ];
}
```

### Dispatching

```php
use Illuminate\Support\Facades\Bus;

// من Controller عند > 200 موظف
$syncLog = DeviceSyncLog::create([
    'device_id' => $deviceId,
    'user_id' => auth()->id(),
    'direction' => 'push',
    'status' => 'running',
    'started_at' => now(),
]);

$job = new PushUsersToDeviceJob(
    deviceId: $deviceId,
    userIds: $userIds,
    syncLogId: $syncLog->id
);

Bus::dispatch($job->onQueue('device-push'));
```

---

## 2. PushFingerprintsToDeviceJob

### الوصف
يدفع بصمات الموظفين (الأساسية ثم الثانوية) إلى جهاز واحد.

### Constructor Payload

```php
public function __construct(
    public int $deviceId,
    public array $userIds,        // الموظفون المطلوب دفع بصماتهم
    public ?int $syncLogId = null,
    public int $chunkSize = 50
) {}
```

### Configuration

نفس `PushUsersToDeviceJob`:
- `$tries = 3`
- `$backoff = 30`
- `$timeout = 1800`
- `$queue = 'device-push-fingerprints'`

### Handle Method

```php
public function handle(DevicePushService $service): void
{
    $service->pushFingerprintsBatch(
        deviceId: $this->deviceId,
        userIds: $this->userIds,
        syncLogId: $this->syncLogId,
        chunkSize: $this->chunkSize
    );
}
```

---

## 3. Dispatching من الـ Controller

```php
// Modules\FingerprintDevices\Http\Controllers\DeviceFullSyncController.php

public function push(PushToDeviceRequest $request): JsonResponse
{
    $this->authorize('edit-fingerprint-devices');
    
    $deviceId = $request->integer('device_id');
    $options = $request->input('options', []);
    
    $userIds = $this->resolveUserIds($options);
    
    // إذا < 200 → synchronous
    if (count($userIds) <= 200) {
        $result = $this->pushService->push(
            deviceId: $deviceId,
            options: $options
        );
        
        return response()->json([
            'success' => true,
            'queued' => false,
            'sync_log_id' => $result->syncLogId,
            'summary' => $result->summary,
        ]);
    }
    
    // إذا > 200 → Queue
    $syncLog = DeviceSyncLog::create([
        'device_id' => $deviceId,
        'user_id' => auth()->id(),
        'direction' => 'push',
        'status' => 'running',
        'started_at' => now(),
    ]);
    
    if ($options['push_users'] ?? false) {
        PushUsersToDeviceJob::dispatch(
            $deviceId,
            $userIds,
            $syncLog->id
        );
    }
    
    if ($options['push_fingerprints'] ?? false) {
        PushFingerprintsToDeviceJob::dispatch(
            $deviceId,
            $userIds,
            $syncLog->id
        );
    }
    
    return response()->json([
        'success' => true,
        'queued' => true,
        'sync_log_id' => $syncLog->id,
        'estimated_count' => count($userIds),
    ], 202);
}
```

---

## 4. معالجة الأخطاء (Error Handling)

### في الـ Service

```php
// Modules\FingerprintDevices\Services\DevicePushService.php

public function pushUsersBatch(int $deviceId, array $userIds, ?int $syncLogId, int $chunkSize): void
{
    $chunks = array_chunk($userIds, $chunkSize);
    
    foreach ($chunks as $chunk) {
        try {
            $users = User::whereIn('id', $chunk)
                ->whereNotNull('employee_code')
                ->get(['id', 'employee_code', 'name']);
            
            if ($users->isEmpty()) continue;
            
            $result = $this->adapter->addUsers(  // أو addUser لكل واحد
                $device->ip_address,
                $device->port,
                $device->comm_key,
                $device->timeout,
                $users->map(fn($u) => new UserData(
                    userId: $u->employee_code,
                    name: $u->name,
                    uid: 0,  // يُعيّنه الجهاز
                ))->all()
            );
            
            $this->recordResults($syncLogId, $deviceId, $result, 'user');
        } catch (\Throwable $e) {
            Log::warning('Push batch failed', [
                'device_id' => $deviceId,
                'chunk_size' => count($chunk),
                'error' => $e->getMessage(),
            ]);
            
            $this->recordChunkFailure($syncLogId, $deviceId, $chunk, $e->getMessage());
        }
    }
    
    if ($syncLogId) {
        $this->finalizeSyncLog($syncLogId);
    }
}
```

### تسجيل النتائج (Result Recording)

```php
private function recordResults(int $syncLogId, int $deviceId, array $result, string $recordType): void
{
    $rows = [];
    foreach ($result['success'] ?? [] as $entry) {
        $rows[] = [
            'sync_log_id' => $syncLogId,
            'device_id' => $deviceId,
            'record_type' => $recordType,
            'target_user_id' => $entry['user_pk'] ?? null,
            'device_uid' => $entry['uid'] ?? null,
            'status' => 'success',
            'attempted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    
    foreach ($result['failed'] ?? [] as $entry) {
        $rows[] = [
            'sync_log_id' => $syncLogId,
            'device_id' => $deviceId,
            'record_type' => $recordType,
            'target_user_id' => $entry['user_id'] ?? null,
            'status' => 'failed',
            'error_message' => substr($entry['error'] ?? 'unknown', 0, 1000),
            'attempted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    
    if (!empty($rows)) {
        DevicePushResult::insert($rows);
    }
}
```

---

## 5. Job Tracking

### من الـ Frontend

```javascript
// composables/useJobStatus.js
export function useJobStatus(syncLogId) {
    const status = ref('queued')
    const progress = ref(0)
    const summary = ref(null)
    
    const poll = async () => {
        const response = await fetch(route('fingerprint-devices.sync.log-status', { log: syncLogId }))
        const data = await response.json()
        
        status.value = data.status
        progress.value = data.progress || 0
        summary.value = data.summary
        
        if (['completed', 'failed', 'partial'].includes(data.status)) {
            return data
        }
        
        await new Promise(r => setTimeout(r, 2000))
        return poll()
    }
    
    return { status, progress, summary, poll }
}
```

### الـ Endpoint المرافق

`GET /fingerprint-devices/sync/log-status/{log}` — يُرجع الحالة الحالية للسجل.

---

## 6. Worker Configuration

### Supervisor / Horizon

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => ['default', 'device-push', 'device-push-fingerprints'],
        'retry_after' => 1900,
    ],
],
```

### Workers

```bash
# Laravel Horizon (مستحسن)
php artisan horizon

# أو يدوياً:
php artisan queue:work redis --queue=device-push,device-push-fingerprints --tries=3 --backoff=30 --timeout=1800
```

---

## 7. Idempotency

### المشكلة
إذا فشل الـ job بعد دفع 30 موظف بنجاح ثم أُعيد، قد يدفع 30 آخرين مرتين.

### الحل
- **Tracking عبر `device_push_results`**: قبل الدفع، نتحقق من `DevicePushResult` حيث `target_user_id = X` و `status = success` و `sync_log_id = recent` (آخر 1 ساعة).
- **إذا وُجد → نتخطى** (`status = skipped` مع reason "already pushed").

```php
$alreadyPushed = DevicePushResult::where('device_id', $deviceId)
    ->where('target_user_id', $user->id)
    ->where('record_type', 'user')
    ->where('status', 'success')
    ->where('created_at', '>', now()->subHour())
    ->exists();

if ($alreadyPushed) {
    $result[] = ['status' => 'skipped', 'user_id' => $user->id];
    continue;
}
```

---

## 8. Testing

### Unit Test Example

```php
// tests/Unit/DevicePushServiceTest.php

use Illuminate\Support\Facades\Queue;

it('dispatches queue job when pushing > 200 users', function () {
    Queue::fake();
    
    $userIds = range(1, 250);
    $this->postJson(route('fingerprint-devices.sync.push'), [
        'device_id' => $this->device->id,
        'options' => [
            'push_users' => true,
            'user_ids' => $userIds,
        ],
    ])->assertStatus(202)
      ->assertJson(['queued' => true, 'estimated_count' => 250]);
    
    Queue::assertPushed(PushUsersToDeviceJob::class, 1);
});

it('runs synchronously for <= 200 users', function () {
    $userIds = range(1, 50);
    $this->postJson(route('fingerprint-devices.sync.push'), [
        'device_id' => $this->device->id,
        'options' => [
            'push_users' => true,
            'user_ids' => $userIds,
        ],
    ])->assertStatus(200)
      ->assertJson(['queued' => false]);
});
```

---

*آخر تحديث: 2026-07-20*
