<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Settings\Services\SystemStatusService;

/**
 * تسجيل إطفاء صريح للـ stack قبل إيقاف الخدمات (يُستدعى يدوياً أو من
 * سكربت الإيقاف حتى يظهر "آخر إيقاف" نظيفاً في صفحة الإعدادات).
 */
class RecordSystemShutdown extends Command
{
    protected $signature = 'system:record-shutdown {--reason=manual : سبب الإطفاء}';

    protected $description = 'Record a system stack shutdown event in system_lifecycles';

    public function handle(SystemStatusService $status): int
    {
        try {
            $row = $status->recordShutdown((string) $this->option('reason'));
            $this->info("Shutdown recorded #{$row->id} at {$row->created_at}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
