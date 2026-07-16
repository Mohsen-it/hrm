<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessUserImportChunk — processes a chunk of user data from a device.
 */
class ProcessUserImportChunk implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        protected int $deviceId,
        protected array $users,
    ) {}

    public function handle(): void
    {
        Log::info('ProcessUserImportChunk: processing chunk', [
            'device_id' => $this->deviceId,
            'user_count' => count($this->users),
        ]);
    }
}
