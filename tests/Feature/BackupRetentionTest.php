<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Backups\Models\BackupRun;
use Modules\Backups\Services\BackupRetentionService;
use Tests\TestCase;

/**
 * Retention guard: the last verified backup is NEVER deleted (plan §17).
 */
class BackupRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_never_deletes_last_verified_backup(): void
    {
        $run = BackupRun::create([
            'type' => 'manual',
            'status' => 'completed',
            'database_driver' => 'mysql',
            'database_name' => 'hrmair',
            'file_path' => 'backups/fake.sql.gz.enc',
            'file_name' => 'fake.sql.gz.enc',
            'checksum_algorithm' => 'sha256',
            'checksum' => str_repeat('a', 64),
            'compressed' => true,
            'encrypted' => true,
            'verification_status' => 'verified',
        ]);

        $result = app(BackupRetentionService::class)->deleteRun($run, 'test');

        $this->assertSame('kept', $result);
        $this->assertDatabaseHas('backup_runs', ['id' => $run->id]);
    }

    public function test_retention_class_monthly_weekly_daily(): void
    {
        $monthly = BackupRun::create([
            'type' => 'automatic', 'status' => 'completed', 'database_driver' => 'mysql',
            'database_name' => 'hrmair', 'file_path' => 'x', 'file_name' => 'x',
            'checksum_algorithm' => 'sha256', 'checksum' => str_repeat('b', 64),
            'compressed' => true, 'encrypted' => false, 'verification_status' => 'verified',
        ]);
        $monthly->created_at = '2026-09-01 02:00:00'; // 1st of month
        $monthly->save();
        $this->assertSame('monthly', $monthly->fresh()->retentionClass());

        $sunday = BackupRun::create([
            'type' => 'automatic', 'status' => 'completed', 'database_driver' => 'mysql',
            'database_name' => 'hrmair', 'file_path' => 'y', 'file_name' => 'y',
            'checksum_algorithm' => 'sha256', 'checksum' => str_repeat('c', 64),
            'compressed' => true, 'encrypted' => false, 'verification_status' => 'verified',
        ]);
        $sunday->created_at = '2026-09-13 02:00:00'; // a Sunday
        $sunday->save();
        $this->assertSame('weekly', $sunday->fresh()->retentionClass());
    }
}
