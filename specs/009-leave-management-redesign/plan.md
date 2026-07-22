# خطة التنفيذ - إعادة تصميم وحدة إدارة الإجازات (Enterprise Leave Management)

**التاريخ:** 2026-07-22
**الحالة:** Phase 1 Complete
**المواصفة:** [spec.md](spec.md)

---

## Technical Context

### Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend Framework | Laravel | 13 |
| Language | PHP | 8.3+ |
| Database | MySQL (prod) / SQLite (dev) | 8.x |
| Frontend | Blade + Inertia.js + Vue 3 | - |
| State Management | Pinia | - |
| CSS Framework | Tailwind CSS | 4.3 |
| Real-time | Socket.IO | - |
| Module System | nwidart/laravel-modules | - |

### Architecture Pattern

```
Controller → Service → Repository → Model → Database
```

### Key Constraints

- **Production Safety:** Additive-only migrations, no data loss
- **Backward Compatibility:** All existing APIs must continue working
- **Arabic Primary:** RTL support, Arabic-first UI
- **Modular:** Follow nwidart/laravel-modules structure

---

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| No data deletion | ✅ Pass | Additive migrations only |
| Layer architecture | ✅ Pass | Controller → Service → Repository → Model |
| Permission system | ✅ Pass | Use existing permission format |
| Bilingual support | ✅ Pass | Arabic primary, English secondary |
| RTL support | ✅ Pass | Use logical properties |

---

## Gate Evaluation

| Gate | Status | Justification |
|------|--------|---------------|
| Data Safety | ✅ PASS | No destructive operations |
| Backward Compat | ✅ PASS | Existing APIs preserved |
| Architecture | ✅ PASS | Follows layered pattern |
| Permissions | ✅ PASS | Uses existing system |

---

## Phase 0: Research Summary

### Research Completed

| Topic | Decision | Rationale |
|-------|----------|-----------|
| State Machine | Linear chain | Matches existing approval workflow |
| Balance Calculation | Tenure-based | Per spec requirements |
| Notification System | Socket.IO + DB | Existing infrastructure |
| File Storage | Local filesystem | Per existing pattern |
| PDF Generation | Arabic-aware | RTL support required |

### Best Practices Identified

1. **Leave Request Service:** Centralize all business logic in `LeaveService`
2. **Approval Chain:** Use strategy pattern for different role-based chains
3. **Balance Management:** Atomic operations for balance updates
4. **Audit Logging:** Event-driven for consistency
5. **Real-time Updates:** Broadcast events via Socket.IO

---

## Phase 1: Design Summary

### Data Model

See [data-model.md](data-model.md) for complete entity definitions.

**Key Entities:**
- `leave_requests` - Main leave request table
- `leave_types` - Leave type definitions
- `leave_balances` - Employee leave balances
- `leave_approvals` - Approval chain steps
- `leave_attachments` - File attachments
- `leave_balance_adjustments` - Manual balance changes
- `tenure_leave_config` - Tenure-based balance rules

### API Contracts

See [contracts/](contracts/) for endpoint definitions.

**Key Endpoints:**
- `POST /api/leaves` - Create leave request
- `PUT /api/leaves/:id` - Update draft
- `POST /api/leaves/:id/submit` - Submit for approval
- `POST /api/approvals/:id/process` - Process approval
- `GET /api/approvals/pending` - List pending approvals
- `GET /api/leaves` - List my requests
- `GET /api/leaves/:id` - Get request details

### Quickstart

See [quickstart.md](quickstart.md) for validation scenarios.

---

## Implementation Phases

### Phase 1: Core Leave Request Management
- Create/update leave requests
- Draft management
- Basic validation

### Phase 2: Approval Workflow
- Multi-level approval chain
- Role-based routing
- Approval processing

### Phase 3: Balance Management
- Automatic balance calculation
- Tenure-based rules
- Manual adjustments

### Phase 4: Notifications & Real-time
- Socket.IO integration
- Database notifications
- Toast notifications

### Phase 5: Reports & Export
- Excel export with filters
- PDF export with Arabic support
- Import from Excel

### Phase 6: Dashboard & Analytics
- Statistics widgets
- Charts and graphs
- Recent activity

---

*Last updated: 2026-07-22*
