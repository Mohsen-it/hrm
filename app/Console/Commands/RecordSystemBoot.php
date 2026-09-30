<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Settings\Services\SystemStatusService;

/**
 * تسجيل إقلاع الـ stack في سجل دورة الحياة (يُستدعى من سكربت التشغيل
 * production/start-production.ps1 بعد بدء الخدمات، ويعمل أيضاً كإصلاح
 * يدوي عند الحاجة).
 */
class RecordSystemBoot extends Command
{
    protected $signature = 'system:record-boot {--reason=boot : سبب الإقلاع}';

    protected $description = 'Record a system stack boot event in system_lifecycles';

    public function handle(SystemStatusService $status): int
    {
        try {
            $row = $status->recordBootIfNeeded();
            if ($row) {
                $this->info("Boot recorded #{$row->id} at {$row->created_at}");
            } else {
                $this->info('Boot already recorded — nothing to do.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
