<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\RawAttendanceLogService;

/**
 * Attendance:ProcessRawLog — convert one specific raw log into a session.
 */
class ProcessRawLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:process-raw-log
                            {id : Raw log id to process}';

    /**
     * The console command description.
     */
    protected $description = 'Process a single raw attendance log by id';

    /**
     * Execute the console command.
     */
    public function handle(RawAttendanceLogService $service): int
    {
        $id = (int) $this->argument('id');

        $log = RawAttendanceLog::find($id);
        if (! $log) {
            $this->components->error("Raw log #{$id} not found.");

            return self::FAILURE;
        }

        $session = $service->processLog($log);

        if ($session === null) {
            $this->components->warn("Raw log #{$id} did not produce a session (no matching user?).");

            return self::SUCCESS;
        }

        $this->components->info("Raw log #{$id} produced session #{$session->id}.");

        return self::SUCCESS;
    }
}
