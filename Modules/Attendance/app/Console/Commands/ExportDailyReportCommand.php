<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Attendance\Services\AttendanceReportService;

/**
 * Attendance:ExportDailyReport — persist a CSV snapshot of the daily report.
 */
class ExportDailyReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:export-daily-report
                            {--date= : Date to export (YYYY-MM-DD). Defaults to today.}
                            {--disk=local : Filesystem disk to write the CSV to}
                            {--path=attendance/exports : Directory inside the disk}';

    /**
     * The console command description.
     */
    protected $description = 'Export a CSV daily attendance report for a single date';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceReportService $reports): int
    {
        $date = (string) ($this->option('date') ?? now()->toDateString());
        $disk = (string) $this->option('disk');
        $path = (string) $this->option('path');

        $kpis = $reports->getDailyKpis($date);

        $fileName = "{$path}/daily-{$date}.csv";
        $csv = "metric,value\n";
        foreach ($kpis as $key => $value) {
            $csv .= "{$key},".(is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))."\n";
        }

        Storage::disk($disk)->put($fileName, $csv);

        $this->info("Exported daily report for {$date} to disk://{$disk}/{$fileName}");

        return self::SUCCESS;
    }
}
