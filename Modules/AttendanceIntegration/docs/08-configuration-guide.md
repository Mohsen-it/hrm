# 8. Configuration Guide

## Configuration File

`Modules/AttendanceIntegration/config/config.php`

## Environment Variables

### Driver Configuration

| Variable | Default | Description |
|---|---|---|
| `ATTENDANCE_INTEGRATION_DRIVER` | `zkteco` | Default driver name |
| `ZKTECO_BRIDGE_URL` | `http://127.0.0.1:5000` | Python ZKTeco bridge service URL |
| `ZKTECO_BRIDGE_TIMEOUT` | `30` | Bridge HTTP timeout in seconds |
| `SUPREMA_API_URL` | — | Suprema API base URL |
| `SUPREMA_API_KEY` | — | Suprema API key |
| `HIKVISION_API_URL` | — | Hikvision API base URL |
| `HIKVISION_API_KEY` | — | Hikvision API key |

### Push Configuration

| Variable | Default | Description |
|---|---|---|
| `ATTENDANCE_PUSH_RATE_LIMIT` | `60` | Max requests per minute per device |
| `ATTENDANCE_PUSH_RATE_DECAY` | `1` | Rate limit decay (minutes) |
| `ATTENDANCE_DUPLICATE_WINDOW_SECONDS` | `30` | Duplicate detection window |
| `ATTENDANCE_PUSH_MAX_RETRIES` | `3` | Max retry attempts for failed ingestion |

### Live Feed Configuration

| Variable | Default | Description |
|---|---|---|
| `ATTENDANCE_LIVE_FEED_MAX` | `100` | Max items in recent punch cache |
| `ATTENDANCE_LIVE_FEED_TTL` | `6` | Cache TTL in hours |

### Logging Configuration

| Variable | Default | Description |
|---|---|---|
| `ATTENDANCE_INTEGRATION_LOG_LEVEL` | `debug` | General integration log level |
| `ATTENDANCE_PUSH_LOG_LEVEL` | `debug` | Push request log level |
| `ATTENDANCE_SYNC_LOG_LEVEL` | `debug` | Device sync log level |

### Reverb (Broadcasting)

| Variable | Default | Description |
|---|---|---|
| `BROADCAST_CONNECTION` | `reverb` | Broadcasting driver |
| `REVERB_APP_ID` | `917848` | Reverb application ID |
| `REVERB_APP_KEY` | `hrm_reverb_key` | Reverb app key |
| `REVERB_APP_SECRET` | `hrm_reverb_secret` | Reverb app secret |
| `REVERB_HOST` | `localhost` | Reverb hostname (client-facing) |
| `REVERB_PORT` | `8080` | Reverb WebSocket port |
| `REVERB_SCHEME` | `http` | `http` or `https` |
| `REVERB_SERVER_HOST` | `0.0.0.0` | Reverb bind address |
| `REVERB_SERVER_PORT` | `8080` | Reverb server port |

## Full Example `.env` Configuration

```env
# Driver
ATTENDANCE_INTEGRATION_DRIVER=zkteco

# ZKTeco Python Bridge
ZKTECO_BRIDGE_URL=http://127.0.0.1:5000
ZKTECO_BRIDGE_TIMEOUT=30

# Push Limits
ATTENDANCE_PUSH_RATE_LIMIT=60
ATTENDANCE_DUPLICATE_WINDOW_SECONDS=30
ATTENDANCE_PUSH_MAX_RETRIES=3

# Live Feed
ATTENDANCE_LIVE_FEED_MAX=100
ATTENDANCE_LIVE_FEED_TTL=6

# Broadcasting
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=917848
REVERB_APP_KEY=hrm_reverb_key
REVERB_APP_SECRET=hrm_reverb_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Logging
ATTENDANCE_PUSH_LOG_LEVEL=debug
ATTENDANCE_SYNC_LOG_LEVEL=debug
```

## Rate Limiter Details

The `attendance_push` rate limiter uses the device serial number as the key, ensuring each device gets its own limit independent of others:

```php
RateLimiter::for('attendance_push', function (Request $request) {
    $key = 'attendance_push:' . ($request->input('SN') ?? $request->ip());
    return Limit::perMinute(config('attendanceintegration.push.rate_limit', 60))->by($key);
});
```
