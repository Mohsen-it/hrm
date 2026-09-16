<?php

namespace Modules\Backups\Services;

use RuntimeException;

/**
 * MySQL dump / restore engine.
 *
 * Safety rules (plan §9):
 * - Uses the official mysqldump binary, never HeidiSQL exports for automation.
 * - Password is passed via the MYSQL_PWD child-process environment only,
 *   never in argv and never written to logs.
 * - Output streams directly to disk (no in-memory buffering) to support
 *   500MB+ databases such as hrmair.
 */
class MysqlDumpService
{
    public function __construct(
        private string $mysqldumpPath,
        private string $mysqlPath,
        private string $host,
        private int $port,
        private string $username,
        private ?string $password,
        /** @var array<int, string> */
        private array $defaultOptions,
    ) {}

    public static function fromConfig(): self
    {
        $connection = config('backups.database_connection', 'mysql');
        $dbConfig = config("database.connections.{$connection}", []);

        return new self(
            mysqldumpPath: (string) config('backups.mysqldump_path'),
            mysqlPath: (string) config('backups.mysql_path'),
            host: (string) ($dbConfig['host'] ?? '127.0.0.1'),
            port: (int) ($dbConfig['port'] ?? 3306),
            username: (string) ($dbConfig['username'] ?? 'root'),
            password: isset($dbConfig['password']) ? (string) $dbConfig['password'] : null,
            defaultOptions: (array) config('backups.mysqldump_options', []),
        );
    }

    public function mysqldumpPath(): string
    {
        return $this->mysqldumpPath;
    }

    public function assertBinariesExist(): void
    {
        if (! is_file($this->mysqldumpPath)) {
            throw new RuntimeException("mysqldump binary not found: {$this->mysqldumpPath}");
        }
        if (! is_file($this->mysqlPath)) {
            throw new RuntimeException("mysql binary not found: {$this->mysqlPath}");
        }
    }

    /**
     * Stream a full database dump to $destinationSqlFile.
     *
     * @throws RuntimeException on any failure (partial file is removed).
     */
    public function dumpToFile(string $database, string $destinationSqlFile): void
    {
        $this->assertBinariesExist();

        $dir = dirname($destinationSqlFile);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create dump directory: {$dir}");
        }

        $command = array_merge(
            [$this->mysqldumpPath],
            $this->defaultOptions,
            ['-h', $this->host, '-P', (string) $this->port, '-u', $this->username, $database]
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $destinationSqlFile, 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = $this->childEnv();
        $process = proc_open($command, $descriptors, $pipes, null, $env);

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start mysqldump process.');
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            @unlink($destinationSqlFile);
            throw new RuntimeException('mysqldump failed (exit '.$exitCode.'): '.$this->sanitize($stderr ?: 'unknown error'));
        }

        if (! is_file($destinationSqlFile) || filesize($destinationSqlFile) === 0) {
            @unlink($destinationSqlFile);
            throw new RuntimeException('mysqldump produced an empty file.');
        }
    }

    /**
     * Stream a .sql file into $database via the mysql client.
     *
     * @throws RuntimeException
     */
    public function importFile(string $database, string $sqlFile): void
    {
        $this->assertBinariesExist();

        if (! is_file($sqlFile)) {
            throw new RuntimeException("SQL file not found: {$sqlFile}");
        }

        $command = [$this->mysqlPath, '-h', $this->host, '-P', (string) $this->port, '-u', $this->username, '--default-character-set=utf8mb4', $database];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, $this->childEnv());

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start mysql process.');
        }

        $input = fopen($sqlFile, 'rb');
        if ($input === false) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            throw new RuntimeException('Unable to open SQL file for import.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Failed reading SQL file during import.');
                }
                if ($chunk !== '' && fwrite($pipes[0], $chunk) === false) {
                    throw new RuntimeException('Failed writing to mysql stdin during import.');
                }
            }
        } finally {
            fclose($input);
            fclose($pipes[0]);
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('mysql import failed (exit '.$exitCode.'): '.$this->sanitize(trim((string) $stderr.' '.(string) $stdout)));
        }
    }

    /**
     * Read-only server version probe (used for metadata).
     */
    public function serverVersion(string $database): ?string
    {
        try {
            $command = [$this->mysqlPath, '-h', $this->host, '-P', (string) $this->port, '-u', $this->username, '-N', '-e', 'SELECT VERSION();', $database];
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = proc_open($command, $descriptors, $pipes, null, $this->childEnv());
            if (! is_resource($process)) {
                return null;
            }
            fclose($pipes[0]);
            $out = trim((string) stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $out !== '' ? $out : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function childEnv(): array
    {
        // Start from a minimal safe environment; MYSQL_PWD is honoured by
        // both mysqldump and mysql clients. Never log this array.
        $env = [];
        foreach (['SystemRoot', 'TEMP', 'TMP', 'PATH'] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $env[$key] = $value;
            }
        }
        if ($this->password !== null && $this->password !== '') {
            $env['MYSQL_PWD'] = $this->password;
        }

        return $env;
    }

    /**
     * Strip anything that could leak credentials from process output.
     */
    private function sanitize(string $message): string
    {
        if ($this->password !== null && $this->password !== '') {
            $message = str_replace($this->password, '***', $message);
        }

        return mb_substr($message, 0, 2000);
    }
}
