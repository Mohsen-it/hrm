# 12. Upgrade Guide

## Version Compatibility

| Module Version | Laravel | PHP | Breaking Changes |
|---|---|---|---|
| 1.0.0 | 13.x | 8.3+ | Initial release |
| 1.1.0 | 13.x | 8.3+ | Stabilization — none |

## Upgrading from Legacy (Pre-Module)

If you are migrating from the old FingerprintDevices-based attendance integration:

### Step 1: Enable the Module

```bash
php artisan module:enable AttendanceIntegration
```

### Step 2: Run Migrations

```bash
php artisan migrate
```

### Step 3: Update Device Push URLs

Update your ZKTeco devices to point to the new endpoint:

**Old URL:**
```
http://your-server/api/fingerprint-push/adms
```

**New URL:**
```
http://your-server/api/attendance-integration/push/adms
```

The old URLs will continue working temporarily (forwarded internally) but will return a deprecation warning.

### Step 4: Set Up Device Tokens (Optional but Recommended)

Generate a token for each device:
```bash
php artisan tinker --execute="echo bcrypt('device-specific-token');"
```

Update the device record:
```sql
UPDATE fingerprint_devices
SET api_token = '$2y$12$...'
WHERE serial_number = 'ZK_MAIN_001';
```

### Step 5: Remove Old Routes (After Migration Confirmed)

Once all devices have been migrated:

1. Remove legacy routes from `Modules/FingerprintDevices/routes/web.php`:
   ```php
   // REMOVE these lines:
   Route::prefix('api/fingerprint-push')->group(function () {
       Route::post('attendance', [DeprecatedPushController::class, 'attendance'])
           ->name('fingerprint-push.attendance');
       Route::match(['get', 'post'], 'adms', [DeprecatedPushController::class, 'adms'])
           ->name('fingerprint-push.adms');
   });
   ```

2. Remove `DeprecatedPushController.php`:
   ```bash
   rm Modules/FingerprintDevices/app/Http/Controllers/DeprecatedPushController.php
   ```

## Upgrading Module Version

### From 1.0.0 to 1.1.0

No breaking changes. Run:
```bash
php artisan migrate
composer dump-autoload
```

## Rollback

To revert to pre-module state:
```bash
php artisan module:disable AttendanceIntegration
php artisan migrate:rollback --path=Modules/AttendanceIntegration/database/migrations
```

Note: This removes the `api_token` column from `fingerprint_devices` and drops the `attendance_integration_audit_logs` table. Back up before rolling back.

## Dependency Updates

```bash
# Update composer dependencies
composer update

# Update npm dependencies
npm update

# Rebuild frontend
npm run build
```
