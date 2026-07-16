# 5. API Documentation

## Base URL

```
{app_url}/api/attendance-integration
```

## Authentication

All push endpoints require device authentication via Bearer token or `X-Device-Token` header.

```
Authorization: Bearer <device_api_token>
```

---

## Endpoints

### 1. Push Attendance Data

```
POST /api/attendance-integration/push
```

Receives attendance punch data from a fingerprint device.

**Headers:**
| Header | Required | Description |
|---|---|---|
| `X-Device-Serial` | Yes* | Device serial number |
| `Authorization` | Yes* | `Bearer <token>` |
| `X-Request-Id` | No | Correlation ID for tracing |
| `Content-Type` | Yes | `application/json` |

*Not required if device has no `api_token` set.

**Request Body (Single Punch):**
```json
{
    "SN": "ZK_MAIN_001",
    "user_id": "EMP001",
    "timestamp": "2026-07-15 08:00:00",
    "punch_type": "check_in",
    "status": 0
}
```

**Request Body (Batch via attendance array):**
```json
{
    "SN": "ZK_MAIN_001",
    "attendance": [
        {"user_id": "EMP001", "timestamp": "2026-07-15 08:00:00", "status": 0},
        {"user_id": "EMP002", "timestamp": "2026-07-15 08:01:00", "status": 0}
    ]
}
```

**Request Body (ADMS Text Format):**
```json
{
    "SN": "ZK_MAIN_001",
    "Body": "ATT\t\tEMP001\t2026-07-15 08:00:00\t0\nATT\t\tEMP001\t2026-07-15 17:00:00\t1\n"
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Attendance data received",
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
    "received": 2,
    "processed": 2,
    "skipped": 0,
    "duplicates": 0,
    "dead_lettered": 0,
    "duration_ms": 45.23,
    "errors": []
}
```

**Error Responses:**

| Code | error_code | Description |
|---|---|---|
| 401 | `DEVICE_SERIAL_MISSING` | No serial number provided |
| 401 | `DEVICE_NOT_FOUND` | Device serial not in database |
| 401 | `DEVICE_TOKEN_MISSING` | Device has token but none provided |
| 403 | `DEVICE_TOKEN_INVALID` | Token does not match |
| 403 | `DEVICE_DEACTIVATED` | Device is deactivated |
| 422 | (validation) | Invalid payload structure |

### 2. ADMS Push

```
GET|POST /api/attendance-integration/push/adms
```

Identical to `/push` but accepts GET requests (for ADMS GET-based devices). Same request/response format.

### 3. Live Punch Feed Snapshot

```
GET /api/attendance-integration/live/snapshot
```

Returns recent punches for real-time monitoring.

**Query Parameters:**
| Parameter | Default | Max |
|---|---|---|
| `limit` | 30 | 100 |

**Response (200):**
```json
{
    "punches": [
        {
            "device": {"id": 1, "name": "Main Gate", "serial_number": "ZK_MAIN_001"},
            "user": {"id": 42, "name": "Ahmed", "employee_code": "EMP001"},
            "punch_type": "check_in",
            "punched_at": "2026-07-15T08:00:00+03:00",
            "session_id": 100,
            "status": "present"
        }
    ],
    "server_time": "2026-07-15T08:05:00+03:00"
}
```

## Request Limits

| Limit | Value |
|---|---|
| Max batch size (`attendance` array) | 500 records |
| Max ADMS Body size | 512 KB |
| Rate limit | 60 requests/minute per device serial |
| Duplicate window | 30 seconds |
| Max retry attempts | 3 |

## Deprecated Endpoints

These legacy endpoints still work but return a deprecation warning:

| Endpoint | Replacement |
|---|---|
| `POST /api/fingerprint-push/attendance` | `POST /api/attendance-integration/push` |
| `GET\|POST /api/fingerprint-push/adms` | `GET\|POST /api/attendance-integration/push/adms` |
| `POST /fingerprint-push/attendance` | `POST /api/attendance-integration/push` |
| `GET\|POST /fingerprint-push/adms` | `GET\|POST /api/attendance-integration/push/adms` |

Deprecated endpoints include `_deprecated: true` and `_deprecation_message` in the response.
