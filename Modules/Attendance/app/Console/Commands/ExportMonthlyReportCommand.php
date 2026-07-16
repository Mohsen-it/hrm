<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Attendance\Services\MonthlyReportService;

/**
 * Attendance:ExportMonthlyReport — write a CSV snapshot of the monthly KPIs.
 */
class ExportMonthlyReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:export-monthly-report
                            {--year= : Year to export (defaults to current year)}
                            {--month= : Month to export (defaults to current month)}
                            {--disk=local : Filesystem disk to write the CSV to}
                            {--path=attendance/exports : Directory inside the disk}';

    /**
     * The console command description.
     */
    protected $description = 'Export a CSV monthly attendance report for one month';

    /**
     * Execute the console command.
     */
    public function handle(MonthlyReportService $reports): int
    {
        $year = (int) ($this->option('year') ?? (int) now()->format('Y'));
        $month = (int) ($this->option('month') ?? (int) now()->format('n'));
        $disk = (string) $this->option('disk');
        $path = (string) $this->option('path');

        $kpis = $reports->getMonthlyKpis($year, $month);
        $breakdown = $reports->getMonthlyDailyBreakdown($year, $month);

        $fileName = "{$path}/monthly-{$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.csv';

        $csv = "metric,value\n";
        foreach ($kpis as $key => $value) {
            $csv .= "{$key},".(is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))."\n";
        }
        $csv .= "\ndate,kpi_value\n";
        foreach ($breakdown as $date => $value) {
            $csv .= "{$date},".(is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))."\n";
        }

        Storage::disk($disk)->put($fileName, $csv);

        $this->info("Exported monthly report for {$year}-{$month} to disk://{$disk}/{$fileName}");

        return self::SUCCESS;
    }
}
