# 14. Realtime Architecture Guide

## Overview

The module uses **Laravel Reverb** (first-party WebSocket server) to push live attendance events to connected browsers without polling.

## Architecture

```
PunchIngestionService
        │
        ▼
PunchReceived Event (ShouldBroadcast)
        │
        ▼
Laravel Reverb WebSocket Server
        │
        │  private-attendance.live
        │
        ▼
Laravel Echo (Browser)
        │
        ▼
useRealtimeAttendance() Composable
        │
        ▼
Vue Components (Live/Index.vue)
```

## Components

### Backend: `PunchReceived` Event

```php
class PunchReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [new PrivateChannel('attendance.live')];
    }

    public function broadcastAs(): string
    {
        return 'punch.received';
    }

    public function broadcastWith(): array
    {
        return [
            'device' => $this->device->toArray(),
            'user' => [...],
            'punch_type' => ...,
            'punched_at' => ...,
            'session_id' => ...,
            'status' => ...,
        ];
    }
}
```

### Backend: Channel Authorization

```php
// routes/channels.php
Broadcast::channel('attendance.live', function ($user) {
    return $user !== null && $user->hasPermissionTo('view-attendance');
});
```

### Frontend: Echo Initialization

```js
// resources/js/bootstrap.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
    wsPort: 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});
```

### Frontend: Composable

```js
// resources/js/composables/useRealtimeAttendance.js
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

export function useRealtimeAttendance({ onPunch } = {}) {
    const isConnected = ref(false)
    const lastPunch = ref(null)

    onMounted(() => {
        window.Echo.private('attendance.live')
            .listen('.punch.received', (data) => {
                lastPunch.value = data
                if (onPunch) onPunch(data)
                router.reload({
                    only: ['live', 'missing', 'anomalies', 'health'],
                    preserveState: true,
                    preserveScroll: true,
                })
            })
            .subscribed(() => { isConnected.value = true })
    })

    onUnmounted(() => {
        window.Echo.leave('attendance.live')
    })

    return { isConnected, lastPunch }
}
```

## Reverb Server Configuration

```env
REVERB_APP_ID=917848
REVERB_APP_KEY=hrm_reverb_key
REVERB_APP_SECRET=hrm_reverb_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

## Starting Reverb

```bash
# Development
php artisan reverb:start

# Production (with process manager like Supervisor)
php artisan reverb:start --host=0.0.0.0 --port=8080
```

## Scaling Reverb

For multi-server deployments, enable Redis-based scaling:

```env
REVERB_SCALING_ENABLED=true
REVERB_SCALING_CHANNEL=reverb
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Testing Real Time

### Verify Reverb is running
```bash
curl http://localhost:8080/apps/917848
```

### Verify Echo connects
Open browser console and check:
```js
window.Echo.connector.pusher.connection.state
// Should be "connected"
```

### Trigger a test event
```bash
php artisan tinker
> event(new \Modules\AttendanceIntegration\Events\PunchReceived(...));
```

## Troubleshooting Real Time

| Symptom | Check |
|---|---|
| Echo not connecting | Is Reverb running? Is port 8080 accessible? |
| Channel subscription fails | Is user authenticated? Has `view-attendance` permission? |
| Events not received | Is `BROADCAST_CONNECTION=reverb` set? Is queue worker running? |
| CORS errors | Set `allowed_origins: ['*']` in `config/reverb.php` |
