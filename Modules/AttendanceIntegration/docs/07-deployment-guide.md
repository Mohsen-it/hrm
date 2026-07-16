# 7. Deployment Guide

## Prerequisites

- PHP 8.3+
- Laravel 13.x
- MySQL 8.0+ (production) / SQLite (development)
- Composer
- Node.js 18+ (for frontend assets)

## Fresh Installation

### 1. Enable the Module

```bash
php artisan module:enable AttendanceIntegration
```

### 2. Run Migrations

```bash
php artisan migrate
```

This creates:
- `attendance_integration_audit_logs` table
- Adds `api_token` column to `fingerprint_devices`
- Adds performance indexes to `raw_attendance_logs`

### 3. Configure Environment

Add to `.env`:

```env
# Broadcasting
BROADCAST_CONNECTION=reverb

# Reverb WebSocket
REVERB_APP_ID=917848
REVERB_APP_KEY=hrm_reverb_key
REVERB_APP_SECRET=hrm_reverb_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Attendance Integration
ATTENDANCE_INTEGRATION_DRIVER=zkteco
ATTENDANCE_PUSH_RATE_LIMIT=60
ATTENDANCE_DUPLICATE_WINDOW_SECONDS=30
ATTENDANCE_LIVE_FEED_MAX=100
```

### 4. Start Reverb (if using realtime)

```bash
php artisan reverb:start
```

### 5. Build Frontend Assets

```bash
npm run build
```

## Production Considerations

### 1. Use HTTPS for Reverb

```env
REVERB_SCHEME=https
REVERB_PORT=443
```

### 2. Configure Queue Worker

```bash
php artisan queue:work --queue=default --tries=3
```

The `DeadLetterPunchJob` uses the default queue.

### 3. Configure Log Rotation

The module writes to `storage/logs/attendance-push.log` and `storage/logs/attendance-sync.log`. Configure logrotate:

```
/var/www/hrm/storage/logs/attendance-*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
}
```

### 4. Database Backup

Include `attendance_integration_audit_logs` in your backup strategy.

### 5. Health Check Endpoint

```bash
curl http://localhost/api/attendance-integration/live/snapshot
```

Should return `{"punches":[],"server_time":"..."}` with HTTP 200.

## Rollback

```bash
php artisan module:disable AttendanceIntegration
php artisan migrate:rollback --path=Modules/AttendanceIntegration/database/migrations
```
