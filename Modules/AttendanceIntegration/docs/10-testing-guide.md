# 10. Testing Guide

## Test Suite Overview

The Attendance Integration module has **111 dedicated tests** across the following suites:

| Suite | Location | Tests | Type |
|---|---|---|---|
| Driver Tests | `tests/Unit/.../Drivers/ZKTeco/` | 31 | Unit |
| Service Tests | `tests/Unit/.../Services/` | 35 | Unit |
| Parser Tests | `tests/Unit/.../Parsers/` | 8 | Unit |
| Repository Tests | `tests/Unit/.../Repositories/` | 3 | Unit |
| DTO Tests | `tests/Unit/.../DTOs/` | 17 | Unit |
| Event Tests | `tests/Unit/.../Events/` | 4 | Unit |
| Request Tests | `tests/Unit/.../Http/Requests/` | 6 | Unit |
| Middleware Tests | `tests/Unit/.../Http/Middleware/` | 5 | Unit |
| Feature Tests | `tests/Feature/.../AttendanceIntegration/` | 24 | Feature |

## Running Tests

### All Attendance Integration Tests
```bash
php artisan test --filter=AttendanceIntegration
```

### Specific Suite
```bash
# Driver tests only
php artisan test --filter=ZKTecoProvider

# Service tests only
php artisan test --filter="Tests\\Unit\\Modules\\AttendanceIntegration\\Services"

# Feature tests only
php artisan test --filter="Tests\\Feature\\Modules\\AttendanceIntegration"
```

### Full Project Suite
```bash
php artisan test
```

## Test Environment

Tests run against an **in-memory SQLite** database (`phpunit.xml`):
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="BROADCAST_CONNECTION" value="null"/>
```

## Writing New Tests

### Unit Test Template

```php
<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Services;

use Tests\TestCase;

class MyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: create mocks, set up test data
    }

    public function test_something(): void
    {
        // Act
        $result = $this->service->someMethod();

        // Assert
        $this->assertSame('expected', $result);
    }
}
```

### Feature Test Template

```php
<?php

namespace Tests\Feature\Modules\AttendanceIntegration;

use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Tests\TestCase;

class MyFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        // Create device
        $type = FingerprintDeviceType::create([...]);
        $this->device = FingerprintDevice::create([...]);
    }

    public function test_endpoint_works(): void
    {
        $response = $this->postJson(route('attendance-integration.push'), [...]);
        $response->assertOk();
    }
}
```

## Mocking Device Interfaces

```php
$device = $this->createMock(AttendanceDeviceInterface::class);
$device->method('getId')->willReturn(1);
$device->method('getSerialNumber')->willReturn('SN001');
$device->method('getStatus')->willReturn('online');
```

## Test Coverage Targets

| Layer | Target |
|---|---|
| Contracts | 100% (all methods exercised) |
| Services | >= 80% |
| Drivers (ZKTeco) | >= 85% |
| Middleware | >= 80% |
| Controllers | >= 75% via feature tests |

## Debugging Failing Tests

```bash
# Run specific test with verbose output
php artisan test --filter=MyTest --verbose

# Stop on first failure
php artisan test --stop-on-failure

# Enable SQL logging during test
DB::enableQueryLog();
// ... test code ...
dd(DB::getQueryLog());
```
