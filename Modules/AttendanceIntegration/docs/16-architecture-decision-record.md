# 16. Architecture Decision Record (ADR)

## ADR-001: Driver-Based Architecture for Attendance Device Integration

### Status
**Accepted** — Implemented in Module Version 1.0.0

### Context

The HRM system needs to integrate with multiple brands of fingerprint/biometric attendance devices (ZKTeco, potential future vendors: Suprema, Hikvision, Anviz). Each vendor uses different:
- Communication protocols (HTTP, TCP, ADMS)
- Data formats (tab-separated text, JSON, binary)
- Status code mappings (check-in/out codes)
- Authentication mechanisms

The initial implementation had device-specific code scattered across multiple modules (`FingerprintDevices`, `Attendance`), creating tight coupling and making it impossible to add new device brands without modifying core attendance logic.

### Decision

We will implement a **Driver-based Architecture** using the following patterns:

1. **Strategy Pattern**: Each device vendor implements a common `DeviceAdapterInterface`
2. **Factory Pattern**: `DeviceAdapterResolver` creates the appropriate driver instance
3. **Dependency Inversion**: All application code depends on Contracts (interfaces), not concrete drivers

### Architecture Diagram

```
┌──────────────────────────────────────────────┐
│              Application Core                 │
│  (PunchIngestionService, DevicePushController)│
│                    │                          │
│         depends on ↓                          │
│  ┌─────────────────────────────────┐          │
│  │         Contracts                │          │
│  │  DeviceAdapterInterface          │          │
│  │  PunchNormalizerInterface        │          │
│  │  PushPayloadParserInterface      │          │
│  └────────────┬────────────────────┘          │
│               │ implements                     │
│    ┌──────────┼──────────┐                    │
│    │          │          │                    │
│ ┌──▼───┐ ┌───▼──┐ ┌────▼──────┐              │
│ │ZKTeco│ │Suprema│ │Hikvision  │              │
│ │Driver│ │(stub) │ │(stub)     │              │
│ └──────┘ └──────┘ └───────────┘              │
│           Driver Layer                        │
└──────────────────────────────────────────────┘
```

### Alternatives Considered

#### A. Vendor-Specific Modules (Rejected)

Each vendor gets its own Laravel module with duplicated business logic.

**Pros**: Simple initial implementation
**Cons**: 
- 3+ copies of attendance ingestion logic
- 3+ copies of ADMS parsing
- Adding a new vendor requires cloning an entire module
- Bug fixes must be applied to all vendor modules
- 6+ push endpoints to maintain

#### B. If/Else or Switch on Vendor Type (Rejected)

Single service with conditional logic based on vendor type string.

```php
if ($device->manufacturer === 'ZKTeco') {
    // ZKTeco-specific logic
} elseif ($device->manufacturer === 'Suprema') {
    // Suprema-specific logic
}
```

**Pros**: Single codebase
**Cons**: 
- Violates Open/Closed Principle
- Core business logic contaminated with vendor details
- Adding a vendor requires modifying core services
- Hard to test in isolation

#### C. Plugin/Hook System (Rejected)

Extendable via WordPress-style hooks and filters.

**Pros**: Highly extensible
**Cons**: 
- No compile-time type safety
- Difficult to debug
- Hidden control flow
- Over-engineered for the number of vendors

### Consequences

#### Positive
- **Zero core changes** when adding new device brands
- **Type safety**: Compile-time contract enforcement
- **Test isolation**: Each driver tested independently
- **Single ingestion pipeline**: One code path for all vendors
- **Clean separation**: Vendor code in `Drivers/{Vendor}/` only

#### Negative
- Slightly more upfront code for the abstraction layer (6 interfaces)
- `DeviceAdapter` wrapper needed between `FingerprintDevice` model and `AttendanceDeviceInterface`
- New developers must understand the Driver pattern before contributing

### Compliance

This decision aligns with:
- **SOLID** principles (especially Open/Closed and Dependency Inversion)
- **Clean Architecture** (dependency rule: core → contracts ← drivers)
- **Laravel best practices** (service providers for registration, contracts for binding)

### References

- [Strategy Pattern](https://refactoring.guru/design-patterns/strategy)
- [Dependency Inversion Principle](https://en.wikipedia.org/wiki/Dependency_inversion_principle)
- [Laravel Service Providers](https://laravel.com/docs/providers)
- [Laravel Contracts](https://laravel.com/docs/contracts)

---

## ADR-002: WebSocket Push vs Polling for Live Attendance

### Status
**Accepted** — Replaced polling with Laravel Reverb WebSockets

### Context

The live attendance monitoring page originally used `setInterval` polling every 3-5 seconds to refresh data. This caused:
- Unnecessary server load (2 HTTP requests every 3-5s per connected client)
- Delayed updates (up to 5 seconds of latency)
- No connection status visibility for operators

### Decision

Replace client-side polling with server-pushed events via Laravel Reverb (WebSocket). The `PunchReceived` event implements `ShouldBroadcast` and pushes to a private channel `attendance.live`.

### Consequences
- **Positive**: Instant updates, reduced server load, connection status visible
- **Negative**: Requires Reverb server process, additional WebSocket infrastructure
- **Risk**: WebSocket connection drops require frontend reconnection handling

---

## ADR-003: Audit Trail as Separate Table

### Status
**Accepted**

### Context

Push operations needed traceability for debugging and compliance. Options considered:
1. Log files only
2. Log files + database table
3. Database table with foreign keys

### Decision

Use a dedicated `attendance_integration_audit_logs` table with:
- Structured JSON context
- Correlation IDs for request tracing
- Foreign keys to `fingerprint_devices` and `users` (non-SQLite)
- Separate log channels for real-time monitoring

### Consequences
- Audit data survives log rotation
- Queryable for reporting and alerting
- Slight performance overhead per push request

---

## ADR-004: Dead Letter Queue for Failed Punches

### Status
**Accepted**

### Context

Push requests can fail due to transient issues (database locks, network timeouts, full disks). Dropping punches silently causes data loss.

### Decision

Failed punches (after 3 retries) are dispatched to a `DeadLetterPunchJob` that:
1. Re-attempts ingestion asynchronously
2. Logs failures for manual intervention
3. Uses exponential backoff (5s, 10s, 15s)

### Consequences
- No data loss for transient failures
- Requires queue worker to be running
- Operators must monitor dead letter queue for permanent failures

---

*Module: AttendanceIntegration*
*Date: 2026-07-15*
