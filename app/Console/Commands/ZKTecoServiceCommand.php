<?php

namespace App\Console\Commands;

use App\Services\ZKTecoPythonBridgeService;
use Illuminate\Console\Command;

/**
 * ZKTecoServiceCommand — manage the Python ZKTeco bridge.
 *
 * The Laravel side talks to ZKTeco devices through a Python microservice
 * (zkteco-service/app.py) that wraps the pyzk library. This command gives
 * operators a quick way to start, stop and inspect that background process.
 *
 * Usage:
 *   php artisan zkteco:service start
 *   php artisan zkteco:service stop
 *   php artisan zkteco:service status
 *   php artisan zkteco:service restart
 */
class ZKTecoServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'zkteco:service
                            {action : Action to perform (start|stop|status|restart)}
                            {--no-wait : Do not wait for the service to become reachable after start}';

    /**
     * The console command description.
     */
    protected $description = 'Manage the ZKTeco Python bridge service (start/stop/status/restart)';

    /**
     * Execute the console command.
     */
    public function handle(ZKTecoPythonBridgeService $bridge): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'start' => $this->startService($bridge),
            'stop' => $this->stopService(),
            'restart' => $this->restartService($bridge),
            'status' => $this->showStatus($bridge),
            default => $this->unknownAction($action),
        };
    }

    /**
     * Start the Python service in the background.
     */
    protected function startService(ZKTecoPythonBridgeService $bridge): int
    {
        $this->info('Starting ZKTeco Python service...');

        if ($bridge->isAvailable()) {
            $this->components->info('Service is already up and reachable.');

            return self::SUCCESS;
        }

        $bridge->ensureServiceRunning();

        if ($this->option('no-wait') || $bridge->isAvailable()) {
            $this->components->info('Service start command issued.');

            return $bridge->isAvailable() ? self::SUCCESS : self::FAILURE;
        }

        $this->components->error('Service did not become reachable in time.');

        return self::FAILURE;
    }

    /**
     * Stop the Python service using the PID file when possible.
     */
    protected function stopService(): int
    {
        $pidFile = (string) config('services.zkteco_python.pid_file', storage_path('app/zkteco-service.pid'));

        if (! is_file($pidFile)) {
            $this->components->warn('No PID file found — service is probably not running.');

            return self::SUCCESS;
        }

        $pid = (int) trim((string) @file_get_contents($pidFile));
        if ($pid <= 0) {
            @unlink($pidFile);
            $this->components->warn('PID file was empty or invalid; removed.');

            return self::SUCCESS;
        }

        $isWindows = DIRECTORY_SEPARATOR === '\\';

        if (function_exists('posix_kill') && @posix_kill($pid, SIGTERM)) {
            $this->components->info("Sent SIGTERM to PID {$pid}.");
        } else {
            $command = $isWindows
                ? "taskkill /F /PID {$pid} 2>nul"
                : "kill -TERM {$pid} 2>/dev/null";
            @exec($command);
            $this->components->info("Issued stop command for PID {$pid}.");
        }

        @unlink($pidFile);

        return self::SUCCESS;
    }

    /**
     * Restart by stopping first and then starting.
     */
    protected function restartService(ZKTecoPythonBridgeService $bridge): int
    {
        $this->stopService();
        sleep(1);

        return $this->startService($bridge);
    }

    /**
     * Print the running PID and health status.
     */
    protected function showStatus(ZKTecoPythonBridgeService $bridge): int
    {
        $pidFile = (string) config('services.zkteco_python.pid_file', storage_path('app/zkteco-service.pid'));
        $pid = is_file($pidFile) ? (int) trim((string) @file_get_contents($pidFile)) : null;

        $rows = [
            ['pid_file', $pidFile],
            ['pid', $pid ?: '(not set)'],
            ['reachable', $bridge->isAvailable() ? 'yes' : 'no'],
            ['url', (string) config('services.zkteco_python.url')],
        ];

        $this->table(['key', 'value'], $rows);

        return $bridge->isAvailable() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Render the unknown-action error.
     */
    protected function unknownAction(string $action): int
    {
        $this->components->error("Unknown action: {$action}");
        $this->line('Available actions: start, stop, status, restart');

        return self::INVALID;
    }
}
