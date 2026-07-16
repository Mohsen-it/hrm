# 15. Folder Structure Documentation

```
Modules/AttendanceIntegration/
│
├── ARCHITECTURE.md              # Architecture report
├── DRIVER-DEVELOPMENT.md        # Driver creation guide
├── composer.json                # Module composer config
├── module.json                  # nwidart/laravel-modules config
│
├── config/
│   └── config.php               # Module configuration
│
├── database/
│   └── migrations/
│       ├── 2026_07_15_000001_add_api_token_to_fingerprint_devices.php
│       ├── 2026_07_15_000002_create_audit_logs_table.php
│       ├── 2026_07_15_000003_add_performance_indexes.php
│       └── 2026_07_15_000004_add_audit_foreign_keys.php
│
├── routes/
│   ├── api.php                  # API route definitions
│   └── channels.php             # Broadcast channel authorization
│
├── docs/                        # Documentation (this directory)
│   ├── 01-system-architecture.md
│   ├── 02-sequence-diagrams.md
│   ├── 03-database-schema.md
│   ├── 04-driver-development-guide.md
│   ├── 05-api-documentation.md
│   ├── 06-adms-integration-guide.md
│   ├── 07-deployment-guide.md
│   ├── 08-configuration-guide.md
│   ├── 09-troubleshooting-guide.md
│   ├── 10-testing-guide.md
│   ├── 11-maintenance-guide.md
│   ├── 12-upgrade-guide.md
│   ├── 13-security-guide.md
│   ├── 14-realtime-architecture-guide.md
│   ├── 15-folder-structure.md    # (this file)
│   └── 16-architecture-decision-record.md
│
└── app/
    ├── Contracts/                # Interfaces (Dependency Inversion)
    │   ├── AttendanceDeviceInterface.php
    │   ├── DeviceAdapterInterface.php
    │   ├── DeviceRepositoryInterface.php
    │   ├── DriverProviderInterface.php
    │   ├── PunchNormalizerInterface.php
    │   └── PushPayloadParserInterface.php
    │
    ├── Drivers/                  # Vendor-specific implementations
    │   ├── ZKTeco/
    │   │   ├── ZKTecoAdapter.php
    │   │   ├── ZKTecoAdmsParser.php
    │   │   ├── ZKTecoProvider.php
    │   │   └── ZKTecoPunchNormalizer.php
    │   ├── Suprema/
    │   │   └── SupremaAdapter.php    (placeholder)
    │   └── Hikvision/
    │       └── HikvisionAdapter.php  (placeholder)
    │
    ├── DTOs/                     # Data Transfer Objects
    │   ├── DateRange.php
    │   ├── DeviceConnectionResult.php
    │   ├── DeviceInfo.php
    │   ├── FingerprintTemplateData.php
    │   ├── NormalizedPunch.php
    │   ├── PunchType.php         (enum)
    │   ├── SyncResult.php
    │   ├── UserData.php
    │   └── VerifyMethod.php      (enum)
    │
    ├── Services/                 # Business logic
    │   ├── AuditLogger.php
    │   ├── DeviceAdapterResolver.php
    │   ├── DeviceSyncOrchestrator.php
    │   ├── LivePunchFeedService.php
    │   └── PunchIngestionService.php
    │
    ├── Repositories/             # Data access
    │   └── DeviceRepository.php
    │
    ├── Models/                   # Eloquent models and adapters
    │   ├── AuditLog.php
    │   └── DeviceAdapter.php
    │
    ├── Events/                   # Domain events
    │   ├── DeviceSyncCompleted.php
    │   └── PunchReceived.php      (ShouldBroadcast)
    │
    ├── Listeners/                # Event handlers
    │   ├── PublishLivePunchEvent.php
    │   └── UpdateDeviceSyncTimestamp.php
    │
    ├── Jobs/                     # Queue jobs
    │   └── DeadLetterPunchJob.php
    │
    ├── Parsers/                  # Shared parsing utilities
    │   └── AdmsTextParser.php
    │
    ├── Http/
    │   ├── Controllers/
    │   │   ├── DevicePushController.php
    │   │   └── LivePunchFeedController.php
    │   ├── Middleware/
    │   │   ├── AuthenticateDevice.php
    │   │   └── LogDeviceRequest.php
    │   ├── Requests/
    │   │   └── StoreDevicePunchRequest.php
    │   └── Resources/
    │       └── DevicePunchResource.php
    │
    ├── Exceptions/
    │   ├── DeviceNotFoundException.php
    │   ├── DuplicatePunchException.php
    │   ├── PunchIngestionFailedException.php
    │   └── UnsupportedDriverException.php
    │
    ├── Logs/
    │   └── channels.php          # Log channel definitions
    │
    └── Providers/
        └── AttendanceIntegrationServiceProvider.php
```

## Dependency Rules

```
┌─────────────────────────────────────────────┐
│  Http/        ← depends on →  Services/     │
│  Http/        ← depends on →  Requests/     │
│  Services/    ← depends on →  Contracts/    │
│  Services/    ← depends on →  DTOs/         │
│  Services/    ← depends on →  Repositories/ │
│  Services/    ← depends on →  Models/       │
│  Drivers/     ← implements  →  Contracts/   │
│  Drivers/     ← uses       →  DTOs/         │
│  Events/      ← uses       →  DTOs/         │
│  Repositories/ ← implements → Contracts/    │
│  Models/      ← implements → Contracts/     │
└─────────────────────────────────────────────┘
```

**Never:**
- Services → Drivers (violates Dependency Inversion)
- Contracts → Services (contracts know nothing about implementations)
- Drivers → Services (Drivers only implement Contracts)
