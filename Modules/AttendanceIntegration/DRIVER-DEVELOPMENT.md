# Adding a New Attendance Device Driver

This document explains how to add support for a new fingerprint/attendance device vendor to the HRM Attendance Integration Module.

## Principles

1. You never modify any existing business logic in the Attendance, Users, or HR modules
2. All vendor-specific code lives inside a single Driver directory
3. The rest of the application communicates only through Contracts (interfaces)

## Step-by-Step Guide

### Step 1: Create the Driver Directory

Create a new directory under `Modules/AttendanceIntegration/app/Drivers/`:

```
Drivers/
  YourVendor/
    YourVendorAdapter.php
    YourVendorPunchNormalizer.php      (optional)
    YourVendorPushParser.php           (optional)
    YourVendorProvider.php
```

### Step 2: Implement `DeviceAdapterInterface`

Create `YourVendorAdapter.php`:

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
        // Implement connection test via your vendor's API/SDK
    }

    public function getDeviceInfo(string $ip, int $port, string $commKey = '', int $timeout = 30): ?DeviceInfo
    {
        // Pull device metadata (serial, firmware, user count, etc.)
        // Return null on failure
    }

    public function getUsers(string $ip, int $port, string $commKey = '', int $timeout = 30): array
    {
        // Return array of ['uid' => int, 'user_id' => string, 'name' => string, ...]
    }

    public function addUser(string $ip, int $port, string $commKey, int $timeout, UserData $user): bool
    {
        // Register a user on the physical device
    }

    public function deleteUser(string $ip, int $port, string $commKey, int $timeout, int $uid): bool
    {
        // Remove a user from the physical device
    }

    public function getAttendance(string $ip, int $port, string $commKey, int $timeout, ?DateRange $range = null): array
    {
        // Pull raw attendance logs from device
        // Return array of raw records
    }

    public function getFingerprintTemplates(string $ip, int $port, string $commKey, int $timeout, int $uid): array
    {
        // Pull fingerprint templates for a user
    }

    public function setFingerprintTemplate(string $ip, int $port, string $commKey, int $timeout, FingerprintTemplateData $template): bool
    {
        // Upload a fingerprint to the device
    }

    public function clearAttendance(string $ip, int $port, string $commKey, int $timeout): bool
    {
        // Clear attendance logs from device memory
    }

    public function getDriverName(): string
    {
        return 'yourvendor';  // lowercase, no spaces
    }
}
```

### Step 3: Implement `PunchNormalizerInterface` (Optional)

If your vendor has a different raw punch format, create `YourVendorPunchNormalizer.php`:

```php
<?php

namespace Modules\AttendanceIntegration\Drivers\YourVendor;

use Modules\AttendanceIntegration\Contracts\PunchNormalizerInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\DTOs\VerifyMethod;

class YourVendorPunchNormalizer implements PunchNormalizerInterface
{
    public function normalize(array $rawPunch): NormalizedPunch
    {
        return new NormalizedPunch(
            deviceUserId: (string) ($rawPunch['user_id'] ?? ''),
            timestamp: new \DateTimeImmutable($rawPunch['timestamp'] ?? 'now'),
            punchType: $this->mapPunchType($rawPunch),
            verifyMethod: $this->mapVerifyMethod($rawPunch),
            deviceSerial: $rawPunch['serial'] ?? null,
            uid: isset($rawPunch['uid']) ? (int) $rawPunch['uid'] : null,
            workCode: (int) ($rawPunch['work_code'] ?? 0),
            rawStatus: $rawPunch['status'] ?? null,
            rawData: $rawPunch,
        );
    }

    public function getDriverName(): string
    {
        return 'yourvendor';
    }

    private function mapPunchType(array $raw): PunchType
    {
        // Map your vendor's punch codes to PunchType enums
        return match ((int) ($raw['direction'] ?? 0)) {
            1 => PunchType::CheckIn,
            2 => PunchType::CheckOut,
            default => PunchType::Unknown,
        };
    }

    private function mapVerifyMethod(array $raw): VerifyMethod
    {
        // Map your vendor's verify codes to VerifyMethod enums
        return match ((int) ($raw['verify'] ?? 0)) {
            1 => VerifyMethod::Fingerprint,
            2 => VerifyMethod::Card,
            3 => VerifyMethod::Face,
            default => VerifyMethod::Fingerprint,
        };
    }
}
```

### Step 4: Implement `PushPayloadParserInterface` (Optional)

If your vendor uses a custom push/ADMS format, create `YourVendorPushParser.php`:

```php
<?php

namespace Modules\AttendanceIntegration\Drivers\YourVendor;

use Modules\AttendanceIntegration\Contracts\PushPayloadParserInterface;

class YourVendorPushParser implements PushPayloadParserInterface
{
    public function parse(array $requestBody, array $requestHeaders): array
    {
        // Parse your vendor's push payload format
        // Return array of raw punch rows
        return [];
    }

    public function getDriverName(): string
    {
        return 'yourvendor';
    }
}
```

### Step 5: Create `DriverProviderInterface` Implementation

This is how the module discovers your driver:

```php
<?php

namespace Modules\AttendanceIntegration\Drivers\YourVendor;

use Modules\AttendanceIntegration\Contracts\DriverProviderInterface;

class YourVendorProvider implements DriverProviderInterface
{
    public function driverName(): string
    {
        return 'yourvendor';
    }

    public function providesAdapter(): bool { return true; }
    public function providesNormalizer(): bool { return true; }
    public function providesPushParser(): bool { return true; }

    public function adapterClass(): string { return YourVendorAdapter::class; }
    public function normalizerClass(): string { return YourVendorPunchNormalizer::class; }
    public function pushParserClass(): string { return YourVendorPushParser::class; }
}
```

### Step 6: Register the Driver

In `Modules/AttendanceIntegration/app/Providers/AttendanceIntegrationServiceProvider.php`, add:

```php
use Modules\AttendanceIntegration\Drivers\YourVendor\YourVendorProvider;

// In the boot() method:
$this->app->make(DeviceAdapterResolver::class)
    ->registerProvider(new YourVendorProvider);
```

### Step 7: Create a Device Type in the Database

Create a `FingerprintDeviceType` with `manufacturer` containing your vendor name:

```php
FingerprintDeviceType::create([
    'name' => 'YourModelName',
    'manufacturer' => 'YourVendor',  // Must match DeviceAdapter::getDriverName() logic
    'protocol' => 'HTTP',  // or 'ADMS', 'TCP', etc.
    'default_port' => 4370,
    'supports_fingerprint' => true,
    'supports_face' => false,
    'max_fingerprints' => 10,
    'max_users' => 1000,
    'is_active' => true,
]);
```

### Step 8: Write Tests

Create tests for:
- Your adapter implements `DeviceAdapterInterface`
- Your normalizer implements `PunchNormalizerInterface`
- Your parser implements `PushPayloadParserInterface`
- Your provider registers correctly
- Punch normalization maps correctly
- Payload parsing handles edge cases

## Contracts (Never Modify These)

| Contract | Purpose |
|---|---|
| `DeviceAdapterInterface` | Pull operations (get users, attendance, templates) |
| `PunchNormalizerInterface` | Normalize raw vendor data → `NormalizedPunch` DTO |
| `PushPayloadParserInterface` | Parse incoming push payload from device |
| `AttendanceDeviceInterface` | Read-only view of a device (already implemented) |
| `DeviceRepositoryInterface` | Device persistence operations (already implemented) |
| `DriverProviderInterface` | Auto-discovery of drivers (implemented per driver) |

## Architecture Boundary

```
                    ┌────────────────────────────┐
                    │   Contracts (interfaces)    │  ← Application depends on THIS
                    └────────────┬───────────────┘
                                 │
              ┌──────────────────┼──────────────────┐
              │                  │                  │
    ┌─────────▼──────┐  ┌───────▼────────┐  ┌──────▼──────────┐
    │  ZKTeco Driver │  │ YourVendor     │  │  Hikvision       │
    │  (implemented) │  │ (new driver)   │  │  (placeholder)   │
    └────────────────┘  └────────────────┘  └─────────────────┘
```

## Checklist

- [ ] Created driver directory under `Drivers/YourVendor/`
- [ ] Implemented `DeviceAdapterInterface`
- [ ] Implemented `PunchNormalizerInterface` (optional)
- [ ] Implemented `PushPayloadParserInterface` (optional)
- [ ] Created `YourVendorProvider` implementing `DriverProviderInterface`
- [ ] Registered provider in `AttendanceIntegrationServiceProvider`
- [ ] Created `FingerprintDeviceType` with matching manufacturer name
- [ ] Wrote tests for all implemented interfaces
- [ ] Verified punches flow through end-to-end
- [ ] Verified attendance sessions are created correctly
