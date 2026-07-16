# 11. Maintenance Guide

## Routine Tasks

### Daily

- Check `attendance_push` logs for error patterns:
  ```bash
  grep "ERROR\|error" storage/logs/attendance-push.log | tail -20
  ```
- Verify Reverb is running:
  ```bash
  php artisan reverb:status
  ```
- Check queue for failed DeadLetter jobs:
  ```bash
  php artisan queue:failed
  ```

### Weekly

- Review `attendance_integration_audit_logs` for unusual patterns:
  ```sql
  SELECT action, status, COUNT(*) as cnt
  FROM attendance_integration_audit_logs
  WHERE occurred_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
  GROUP BY action, status
  ORDER BY cnt DESC;
  ```
- Check device online status:
  ```sql
  SELECT status, COUNT(*) FROM fingerprint_devices GROUP BY status;
  ```
- Rotate log files if not using logrotate

### Monthly

- Review duplicate punch rates:
  ```sql
  SELECT DATE(occurred_at) as day, COUNT(*) as dupes
  FROM attendance_integration_audit_logs
  WHERE action = 'punch_duplicate' AND occurred_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY DATE(occurred_at);
  ```
- Check raw_attendance_logs growth:
  ```sql
  SELECT COUNT(*), MIN(created_at), MAX(created_at) FROM raw_attendance_logs;
  ```

### Quarterly

- Archive old audit logs (optional):
  ```sql
  -- Example: archive logs older than 90 days
  CREATE TABLE attendance_integration_audit_logs_archive LIKE attendance_integration_audit_logs;
  INSERT INTO attendance_integration_audit_logs_archive
  SELECT * FROM attendance_integration_audit_logs
  WHERE occurred_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
  DELETE FROM attendance_integration_audit_logs
  WHERE occurred_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
  ```
- Review and update device tokens:
  - Rotate tokens for security
  - Remove tokens for deactivated devices

## Database Maintenance

### Index Health
```sql
SHOW INDEX FROM raw_attendance_logs;
SHOW INDEX FROM attendance_integration_audit_logs;
```

### Optimize Tables
```sql
OPTIMIZE TABLE raw_attendance_logs;
OPTIMIZE TABLE attendance_integration_audit_logs;
```

## Backup Considerations

Back up these tables:
- `attendance_integration_audit_logs` — audit trail (optional but recommended)
- `fingerprint_devices` — device configuration (critical)

`raw_attendance_logs` and `attendance_sessions` are backed up as part of the Attendance module.

## Health Monitoring Queries

```sql
-- Devices offline > 24 hours
SELECT name, serial_number, last_seen_at
FROM fingerprint_devices
WHERE status = 'offline'
  AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(NOW(), INTERVAL 24 HOUR));

-- Push failures in last hour
SELECT COUNT(*) as failures
FROM attendance_integration_audit_logs
WHERE action IN ('push_failed', 'punch_skipped')
  AND occurred_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Dead letter queue depth
SELECT COUNT(*) FROM jobs WHERE queue = 'default';

-- Unprocessed raw logs
SELECT COUNT(*) FROM raw_attendance_logs WHERE processed = 0;
```
