# 4. Driver Development Guide

## Overview

This guide explains how to add support for a new fingerprint/biometric attendance device vendor. **Zero changes to existing business logic are required.**

## Architecture Principle

```
Application Core → Contracts ← Driver Implementation
                    (interfaces)
```

The application **never** imports driver classes directly. All communication goes through interfaces defined in `Contracts/`.

## Quick Start Checklist

- [ ] Create driver directory under `Drivers/YourVendor/`
- [ ] Implement `DeviceAdapterInterface`
- [ ] Optionally implement `PunchNormalizerInterface`
- [ ] Optionally implement `PushPayloadParserInterface`
- [ ] Create `YourVendorProvider` implementing `DriverProviderInterface`
- [ ] Register provider in `AttendanceIntegrationServiceProvider`
- [ ] Create `FingerprintDeviceType` with matching manufacturer name
- [ ] Write tests for all implemented interfaces

## Step-by-Step

### 1. Create Driver Directory

```
Modules/AttendanceIntegration/app/Drivers/YourVendor/
├── YourVendorAdapter.php
├── YourVendorPunchNormalizer.php      (optional)
├── YourVendorPushParser.php           (optional)
└── YourVendorProvider.php
```

### 2. Implement DeviceAdapterInterface

```php
<?php
namespace Modules\AttendanceIntegration\Drivers\YourVendor;

use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\DTOs\DateRange;
use Modules\AttendanceIntegration\DTOs\DeviceInfo;
use Modules\AttendanceIntegration\DTOs\FingerprintTemplateData;
use Modules\AttendanceIntegration\DTOs\UserData;

class YourVendorAdapter implements DeviceAdapterInterface
{
    public function testConnection(string $ip, int $port, string $commKey = '', int $timeout = 30): bool
    {
        // Test if device is reachable
        return true;
    }

    public function getDeviceInfo(string $ip, int $port, string $commKey = '', int $timeout = 30): ?DeviceInfo
    {
        // Return device metadata; null on failure
        return DeviceInfo::fromArray([...]);
    }

    public function getUsers(string $ip, int $port, string $commKey = '', int $timeout = 30): array
    {
        // Return array of ['uid' => int, 'user_id' => string, 'name' => string]
        return [];
    }

    public function addUser(string $ip, int $port, string $commKey, int $timeout, UserData $user): bool
    {
        return true;
    }

    public function deleteUser(string $ip, int $port, string $commKey, int $timeout, int $uid): bool
    {
        return true;
    }

    public function getAttendance(string $ip, int $port, string $commKey, int $timeout, ?DateRange $range = null): array
    {
        // Return array of raw punch records
        return [];
    }

    public function getFingerprintTemplates(string $ip, int $port, string $commKey, int $timeout, int $uid): array
    {
        return [];
    }

    public function setFingerprintTemplate(string $ip, int $port, string $commKey, int $timeout, FingerprintTemplateData $template): bool
    {
        return true;
    }

    public function clearAttendance(string $ip, int $port, string $commKey, int $timeout): bool
    {
        return true;
    }

    public function getDriverName(): string
    {
        return 'yourvendor';
    }
}
```

### 3. Implement PunchNormalizerInterface (Optional)

Required if your device uses different status codes than ZKTeco (0=check_in, 1=check_out).

```php
class YourVendorPunchNormalizer implements PunchNormalizerInterface
{
    public function normalize(array $rawPunch): NormalizedPunch
    {
        return new NormalizedPunch(
            deviceUserId: (string) ($rawPunch['user_id'] ?? ''),
            timestamp: new \DateTimeImmutable($rawPunch['timestamp'] ?? 'now'),
            punchType: $this->mapPunchType($rawPunch),
            verifyMethod: $this->mapVerifyMethod($rawPunch),
            rawData: $rawPunch,
        );
    }

    public function getDriverName(): string
    {
        return 'yourvendor';
    }

    private function mapPunchType(array $raw): PunchType
    {
        return match ((int) ($raw['direction'] ?? 0)) {
            1 => PunchType::CheckIn,
            2 => PunchType::CheckOut,
            default => PunchType::Unknown,
        };
    }

    private function mapVerifyMethod(array $raw): VerifyMethod
    {
        return VerifyMethod::Fingerprint;
    }
}
```

### 4. Create Provider for Auto-Discovery

```php
class YourVendorProvider implements DriverProviderInterface
{
    public function driverName(): string { return 'yourvendor'; }
    public function providesAdapter(): bool { return true; }
    public function providesNormalizer(): bool { return true; }
    public function providesPushParser(): bool { return true; }

    public function adapterClass(): string { return YourVendorAdapter::class; }
    public function normalizerClass(): string { return YourVendorPunchNormalizer::class; }
    public function pushParserClass(): string { return YourVendorPushParser::class; }
}
```

### 5. Register the Driver

In `Modules/AttendanceIntegration/app/Providers/AttendanceIntegrationServiceProvider.php`:

```php
use Modules\AttendanceIntegration\Drivers\YourVendor\YourVendorProvider;

// In boot() or register():
$this->app->make(DeviceAdapterResolver::class)
    ->registerProvider(new YourVendorProvider);
```

### 6. Create Device Type

```sql
INSERT INTO fingerprint_device_types
    (name, manufacturer, protocol, default_port, supports_fingerprint, max_users, is_active)
VALUES
    ('YourModel-3000', 'YourVendor', 'HTTP', 4370, true, 1000, true);
```

The `manufacturer` column must contain your vendor name (used by `DeviceAdapter` for driver resolution).

### 7. Interface Contract Reference

| You implement | Used by |
|---|---|
| `DeviceAdapterInterface` | `FingerprintDeviceService`, `DeviceSyncOrchestrator` |
| `PunchNormalizerInterface` | `DevicePushController` (via `DeviceAdapterResolver`) |
| `PushPayloadParserInterface` | `DevicePushController` (via `DeviceAdapterResolver`) |
