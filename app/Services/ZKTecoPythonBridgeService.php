<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ZKTecoPythonBridgeService — thin HTTP bridge to the Python ZKTeco service.
 *
 * The Python service (zkteco-service/app.py) exposes a Flask API that wraps the
 * pyzk library. The bridge does:
 *  - Health checks (is the service up?)
 *  - Auto-start the Python service when a request is issued and the process
 *    is not running yet (best-effort).
 *  - Proxy all device operations (test connection, attendance, users, etc.).
 *
 * Errors are caught and returned as a structured payload so the caller can
 * decide how to react (e.g. fall back to the PHP-only adapter).
 */
class ZKTecoPythonBridgeService
{
    protected string $baseUrl;

    protected int $timeout;

    protected string $pidFile;

    protected string $startScript;

    /**
     * Create a new bridge instance.
     */
    public function __construct()
    {
        $config = (array) config('services.zkteco_python', []);

        $this->baseUrl = rtrim((string) ($config['url'] ?? 'http://127.0.0.1:5000'), '/');
        $this->timeout = (int) ($config['timeout'] ?? 60);
        $this->pidFile = (string) ($config['pid_file'] ?? storage_path('app/zkteco-service.pid'));
        $this->startScript = (string) ($config['start_script'] ?? base_path('zkteco-service/start.bat'));
    }

    /**
     * Lightweight health probe against the Python service.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get($this->baseUrl.'/health');

            return $response->successful() && $response->json('status') === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Best-effort start of the Python service when it is not running.
     *
     * Returns true if the service is reachable after the attempt.
     */
    public function ensureServiceRunning(): bool
    {
        if ($this->isAvailable()) {
            return true;
        }

        if (! $this->isProcessRunning()) {
            $this->startServiceProcess();
        }

        // Give the service a couple of seconds to bind the port.
        for ($i = 0; $i < 10; $i++) {
            if ($this->isAvailable()) {
                return true;
            }
            usleep(200_000);
        }

        return $this->isAvailable();
    }

    /**
     * Test a connection to a physical device through the Python service.
     *
     * @return array{connected: bool, info?: array<string, mixed>, error?: string}
     */
    public function testConnection(string $ip, int $port = 4370, int $password = 0, int $timeout = 30): array
    {
        return $this->post('/device/test-connection', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
            'timeout' => $timeout,
        ]);
    }

    /**
     * Pull attendance rows from the device.
     *
     * @return array{records?: array<int, array<string, mixed>>, error?: string, success?: bool}
     */
    public function getAttendance(string $ip, int $port = 4370, int $password = 0, int $timeout = 60, ?bool $forceUdp = null, ?bool $ommitPing = null): array
    {
        return $this->post('/device/get-attendance', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
            'timeout' => $timeout,
            'force_udp' => $forceUdp,
            'ommit_ping' => $ommitPing,
        ]);
    }

    /**
     * Pull users stored on the device.
     *
     * @return array{users?: array<int, array<string, mixed>>, error?: string}
     */
    public function getUsers(string $ip, int $port = 4370, int $password = 0): array
    {
        return $this->post('/device/get-users', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
        ]);
    }

    /**
     * Pull fingerprint templates. Pass `null` for `uid` to pull every template.
     *
     * @return array{templates?: array<int, array<string, mixed>>, error?: string}
     */
    public function getTemplates(string $ip, int $port = 4370, int $password = 0, ?int $uid = null): array
    {
        $payload = [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
        ];

        if ($uid !== null) {
            $payload['uid'] = $uid;
        }

        return $this->post('/device/get-templates', $payload);
    }

    /**
     * Read device metadata.
     *
     * @return array<string, mixed>
     */
    public function getDeviceInfo(string $ip, int $port = 4370, int $password = 0): array
    {
        return $this->post('/device/info', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
        ]);
    }

    /**
     * Add a user on the device.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function addUser(string $ip, int $port, int $password, int $uid, string $userId, string $name, array $extra = []): array
    {
        return $this->post('/device/add-user', array_merge([
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
            'uid' => $uid,
            'user_id' => $userId,
            'name' => $name,
        ], $extra));
    }

    /**
     * Add multiple users on the device in a single call.
     *
     * @param  array<int, array<string, mixed>>  $users
     * @return array<string, mixed>
     */
    public function addUsersBatch(string $ip, int $port, int $password, array $users): array
    {
        return $this->post('/device/add-users-batch', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
            'users' => $users,
        ]);
    }

    /**
     * Delete a user by their device UID.
     *
     * @return array<string, mixed>
     */
    public function deleteUser(string $ip, int $port, int $password, int $uid): array
    {
        return $this->post('/device/delete-user', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
            'uid' => $uid,
        ]);
    }

    /**
     * Upload a single fingerprint template.
     *
     * @return array<string, mixed>
     */
    public function exportTemplate(string $ip, int $port, int $password, int $uid, int $fingerId, string $templateData): array
    {
        return $this->post('/device/export-template', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
            'uid' => $uid,
            'finger_id' => $fingerId,
            'template_data' => $templateData,
        ]);
    }

    /**
     * Upload a batch of fingerprint templates.
     *
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<string, mixed>
     */
    public function exportTemplatesBatch(string $ip, int $port, int $password, array $templates): array
    {
        return $this->post('/device/export-templates-batch', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
            'templates' => $templates,
        ]);
    }

    /**
     * Wipe the device's attendance log.
     *
     * @return array<string, mixed>
     */
    public function clearAttendance(string $ip, int $port, int $password): array
    {
        return $this->post('/device/clear-attendance', [
            'ip' => $ip,
            'port' => $port,
            'password' => $password,
        ]);
    }

    /**
     * Best-effort post that gracefully returns an error payload on failure.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(string $path, array $payload): array
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->baseUrl.$path, $payload);
        } catch (Throwable $e) {
            Log::warning('ZKTecoPythonBridgeService: HTTP failure', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'error' => $response->json('error') ?? 'HTTP '.$response->status(),
                'status' => $response->status(),
            ];
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * Determine whether the Python service is currently running.
     */
    protected function isProcessRunning(): bool
    {
        $pid = $this->readPid();
        if (! $pid) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        // On Windows, fall back to tasklist grep — best effort.
        $output = [];
        $code = 0;
        @exec('tasklist /FI "PID eq '.$pid.'" 2>nul', $output, $code);

        return $code === 0 && count($output) > 1;
    }

    /**
     * Read the PID file contents (or null if missing).
     */
    protected function readPid(): ?int
    {
        if (! is_file($this->pidFile)) {
            return null;
        }

        $raw = trim((string) @file_get_contents($this->pidFile));
        if ($raw === '' || ! ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * Start the Python service in the background.
     */
    protected function startServiceProcess(): void
    {
        $script = $this->startScript;
        if (! is_file($script)) {
            Log::warning('ZKTecoPythonBridgeService: start script not found', ['script' => $script]);

            return;
        }

        $logFile = (string) config('services.zkteco_python.log_file', storage_path('logs/zkteco-service.log'));
        $logDir = dirname($logFile);
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $isWindows = DIRECTORY_SEPARATOR === '\\';

        if ($isWindows) {
            $command = 'cmd /c "start /B "" "'.$script.'" > "'.$logFile.'" 2>&1"';
        } else {
            $command = 'nohup "'.$script.'" > "'.$logFile.'" 2>&1 & echo $! > "'.$this->pidFile.'"';
        }

        @exec($command);
    }
}
