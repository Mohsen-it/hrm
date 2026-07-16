<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\AttendanceMonitoringService;

/**
 * Attendance:DetectAnomalies — surface mass-lateness / mass-absence events.
 */
class DetectAnomaliesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:detect-anomalies
                            {--date= : Date to scan (YYYY-MM-DD). Defaults to today.}
                            {--lateness-ratio=0.30 : Trigger threshold for mass lateness}
                            {--absence-ratio=0.25 : Trigger threshold for mass absence}';

    /**
     * The console command description.
     */
    protected $description = 'Detect attendance anomalies (mass lateness / mass absence) for a date';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceMonitoringService $monitoring): int
    {
        $date = (string) ($this->option('date') ?? now()->toDateString());
        $lateness = (float) $this->option('lateness-ratio');
        $absence = (float) $this->option('absence-ratio');

        $this->info("Detecting anomalies for {$date}...");

        $late = $monitoring->detectMassLateness($date, $lateness);
        $absent = $monitoring->detectMassAbsence($date, $absence);

        $rows = [
            ['anomaly', 'lateness', 'date' => $date, 'ratio' => $late['ratio'] ?? null, 'count' => $late['count'] ?? 0],
            ['anomaly', 'absence', 'date' => $date, 'ratio' => $absent['ratio'] ?? null, 'count' => $absent['count'] ?? 0],
        ];

        $this->table(['type', 'date', 'ratio', 'count'], $rows);

        return self::SUCCESS;
    }
}
