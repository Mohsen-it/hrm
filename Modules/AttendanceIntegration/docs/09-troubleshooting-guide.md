# 9. Troubleshooting Guide

## Quick Diagnostic Commands

```bash
# Check module status
php artisan module:list

# Check routes are registered
php artisan route:list --name=attendance-integration

# Check migrations status
php artisan migrate:status

# Check Reverb status
php artisan reverb:status

# Check queue worker
php artisan queue:monitor

# View recent push logs
tail -f storage/logs/attendance-push.log

# View sync logs
tail -f storage/logs/attendance-sync.log
```

## Common Issues

### 1. "Route [attendance-integration.push] not defined"

**Cause**: Module is disabled or routes not loaded.

**Fix**:
```bash
php artisan module:enable AttendanceIntegration
php artisan config:clear
php artisan route:clear
```

### 2. Device push returns 401 "DEVICE_SERIAL_MISSING"

**Cause**: Device not sending serial number.

**Fix**: Ensure device sends either:
- `SN` in request body
- `X-Device-Serial` header
- `serial_number` in request body

### 3. Device push returns 401 "DEVICE_TOKEN_MISSING"

**Cause**: Device has `api_token` set but not sending it.

**Fix**: Send `Authorization: Bearer <token>` header, or remove `api_token` from the device record (not recommended for production).

### 4. Device push returns 403 "DEVICE_TOKEN_INVALID"

**Cause**: Token mismatch.

**Fix**: 
- If using hashed token (bcrypt): regenerate the token hash
- If using plain text: verify the token matches exactly
- Check for whitespace or encoding issues in the token

### 5. Punches not creating attendance sessions

**Cause**: `device_user_id` doesn't match any `users.employee_code`.

**Fix**:
1. Check the employee's `employee_code` in the database
2. Verify the device stores the same `user_id` as the employee's `employee_code`
3. Use the device sync feature to match users: `POST /fingerprint-devices/sync`

### 6. Duplicate punches appearing

**Cause**: Device re-sending punches within the 30-second dedup window.

**Fix**: 
- Increase `ATTENDANCE_DUPLICATE_WINDOW_SECONDS` in `.env`
- Check device ADMS configuration for retry settings

### 7. "419 CSRF token mismatch" on live snapshot

**Cause**: CSRF middleware interfering with API calls.

**Fix**: The `/api/*` prefix should exclude CSRF. Verify `app/Http/Middleware/VerifyCsrfToken.php` has:
```php
protected $except = [
    'api/*',
];
```

### 8. Broadcasting not working (Vue not receiving realtime events)

**Checklist**:
1. Is Reverb running? `php artisan reverb:start`
2. Is `BROADCAST_CONNECTION=reverb` set?
3. Can the browser reach `ws://localhost:8080`?
4. Is `laravel-echo` + `pusher-js` installed? `npm list laravel-echo`
5. Is `window.Echo` defined in browser console?
6. Is the user authenticated (private channel requires auth)?

### 9. Audit logs not being written

**Cause**: Foreign key constraint or database issue.

**Fix**:
1. Check `storage/logs/laravel.log` for SQL errors
2. SQLite tests: foreign keys from migration are skipped; no FK enforcement
3. Verify `attendance_integration_audit_logs` table exists

### 10. Migration failures

**Issue**: `2026_07_15_000004_add_audit_foreign_keys` fails.

**Fix**: The migration skips SQLite. For MySQL, ensure referenced tables exist:
```bash
php artisan migrate:status
```

## Log Channels Reference

| Channel | Path | Purpose |
|---|---|---|
| `attendance_push` | `storage/logs/attendance-push.log` | All push request/response data |
| `attendance_sync` | `storage/logs/attendance-sync.log` | Device sync operations |
| `attendance_integration` | `storage/logs/attendance-integration.log` | General module operations |
| `laravel` | `storage/logs/laravel.log` | Application errors/exceptions |

## Escalation

If the issue persists after trying the above:

1. Collect logs from all 4 channels
2. Note the correlation ID from the failed response header
3. Query `attendance_integration_audit_logs` for that correlation ID:
   ```sql
   SELECT * FROM attendance_integration_audit_logs WHERE correlation_id = 'xxx';
   ```
4. Check device firmware version and compatibility
