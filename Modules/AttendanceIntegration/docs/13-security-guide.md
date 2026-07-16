# 13. Security Guide

## Authentication

### Device Authentication

Every push endpoint is protected by `AuthenticateDevice` middleware that:

1. **Resolves device** by serial number (from body, header, or query)
2. **Validates device exists** and is not deactivated
3. **Validates token** if device has `api_token` set

### Token Formats

The middleware supports two token formats:

| Format | Detection | Comparison |
|---|---|---|
| bcrypt hash | Starts with `$2y$` or `$2b$` | `Hash::check()` |
| Plain text | Anything else | `hash_equals()` |

**Recommendation**: Use bcrypt hashes in production. Generate with:
```bash
php artisan tinker --execute="echo bcrypt('your-token');"
```

### Request Flow

```
Device → AuthenticateDevice Middleware
  ├─ serial missing → 401 DEVICE_SERIAL_MISSING
  ├─ device not found → 401 DEVICE_NOT_FOUND
  ├─ device deactivated → 403 DEVICE_DEACTIVATED
  ├─ token required but missing → 401 DEVICE_TOKEN_MISSING
  ├─ token invalid → 403 DEVICE_TOKEN_INVALID
  └─ valid → proceed to controller
```

## Rate Limiting

| Limiter | Rate | Key |
|---|---|---|
| `attendance_push` | 60/min | Device serial or IP |

Rate limiting prevents brute-force token attacks and DoS on push endpoints.

## Input Validation

All push requests pass through `StoreDevicePunchRequest` which:

| Check | Constraint |
|---|---|
| Max batch size | 500 records per request |
| Max Body size | 512 KB |
| Punch type | `in:check_in,check_out,auto,break_in,break_out` |
| Status code | Integer 0-255 |
| Work code | Integer 0-65535 |
| User ID | String, max 50 characters |

## Duplicate Prevention

The `guardAgainstDuplicate()` method prevents replay attacks:

1. Queries `raw_attendance_logs` for existing records
2. Window: ±30 seconds from punch timestamp
3. Match: `(device_id, device_user_id, punch_time)`
4. Uses composite index `idx_raw_logs_dedup` for sub-millisecond lookup

## Broadcast Channel Authorization

```php
Broadcast::channel('attendance.live', function ($user) {
    return $user !== null && $user->hasPermissionTo('view-attendance');
});
```

Only users with `view-attendance` permission can subscribe to live punch events.

## Data Protection

| Data | Storage | Notes |
|---|---|---|
| Device `api_token` | Database, hashed | Use bcrypt in production |
| Device `comm_key` | Database, plain text | Device connection password |
| Raw punch data | `raw_attendance_logs.raw_data` (JSON) | May contain personal data; apply retention policy |
| Audit logs | `attendance_integration_audit_logs` | Append-only; contains user IDs and timestamps |

## Security Checklist

- [ ] All device tokens use bcrypt hashing in production
- [ ] Rate limiting is enabled (`ATTENDANCE_PUSH_RATE_LIMIT > 0`)
- [ ] HTTPS is enabled for production (`REVERB_SCHEME=https`)
- [ ] `APP_DEBUG=false` in production
- [ ] CSRF middleware excludes API routes
- [ ] Device passwords (`comm_key`) are rotated periodically
- [ ] Audit logs are reviewed for suspicious patterns
- [ ] Broadcast channel authorization is configured

## Threat Model

| Threat | Mitigation |
|---|---|
| Device impersonation | Bearer token authentication |
| Token brute force | Rate limiting (60/min) |
| Replay attacks | 30-second dedup window + database check |
| Payload injection | FormRequest validation |
| Eavesdropping | HTTPS (TLS) |
| Unauthorized broadcast access | Permission check on channel |
| Data exfiltration via audit logs | Audit log access control |
| DoS via batch push | 500 record limit per request |
