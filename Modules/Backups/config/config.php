<?php

/*
|--------------------------------------------------------------------------
| HRM Database Backup Configuration
|--------------------------------------------------------------------------
| Canonical source: specs/database-backup-restore-plan.md
| Production DB: MySQL 8.4 (hrmair) on Windows.
| Never store passwords or encryption keys in Git — .env only.
*/
return [
    'name' => 'Backups',

    'enabled' => env('BACKUP_ENABLED', true),

    'timezone' => env('BACKUP_TIMEZONE', 'Asia/Damascus'),

    'database_connection' => env('BACKUP_DATABASE_CONNECTION', 'mysql'),
    'database_name' => env('BACKUP_DATABASE_NAME', 'hrmair'),

    // Real binaries discovered on this host (Laragon MySQL 8.4.3).
    // Override via .env if MySQL moves.
    'mysqldump_path' => env(
        'BACKUP_MYSQLDUMP_PATH',
        'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe'
    ),
    'mysql_path' => env(
        'BACKUP_MYSQL_PATH',
        'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
    ),

    'local_disk' => env('BACKUP_LOCAL_DISK', 'backups'),
    'remote_disk' => env('BACKUP_REMOTE_DISK', null),

    'retention_daily' => (int) env('BACKUP_RETENTION_DAILY', 14),
    'retention_weekly' => (int) env('BACKUP_RETENTION_WEEKLY', 12),
    'retention_monthly' => (int) env('BACKUP_RETENTION_MONTHLY', 12),

    'encryption_enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),
    // 32-byte key, base64-encoded. Generate: php -r "echo base64_encode(random_bytes(32));"
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', null),

    'verification_enabled' => env('BACKUP_VERIFICATION_ENABLED', true),

    'min_free_space_mb' => (int) env('BACKUP_MIN_FREE_SPACE_MB', 20480),

    'restore_test_prefix' => env('BACKUP_RESTORE_TEST_DATABASE_PREFIX', 'hrmair_restore_test_'),

    // mysqldump flags per plan §9 (InnoDB-consistent, binary-safe, utf8mb4).
    'mysqldump_options' => [
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '--events',
        '--hex-blob',
        '--set-gtid-purged=OFF',
        '--default-character-set=utf8mb4',
    ],

    'checksum_algorithm' => 'sha256',

    'schedule' => [
        'daily_time' => env('BACKUP_DAILY_TIME', '02:00'),
        'weekly_day' => env('BACKUP_WEEKLY_DAY', '0'), // Sunday
        'weekly_time' => env('BACKUP_WEEKLY_TIME', '03:00'),
        'monthly_time' => env('BACKUP_MONTHLY_TIME', '04:00'),
        'restore_test_day' => env('BACKUP_RESTORE_TEST_DAY', '0'),
        'restore_test_time' => env('BACKUP_RESTORE_TEST_TIME', '06:00'),
    ],
];
