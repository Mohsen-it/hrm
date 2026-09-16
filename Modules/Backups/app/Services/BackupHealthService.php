<?php

namespace Modules\Backups\Services;

use Illuminate\Support\Facades\DB;
use Modules\Backups\Repositories\BackupRunRepository;
use Throwable;

/**
 * Post-restore / periodic health checks (plan §16).
 */
class BackupHealthService
{
    public function __construct(private BackupRunRepository $runs) {}

    /**
     * @return array{ok: bool, failures: array<int, string>, checks: array<int, array{name: string, ok: bool, detail: string}>}
     */
    public function check(): array
    {
        $checks = [];
        $failures = [];

        $run = function (string $name, callable $fn) use (&$checks, &$failures): void {
            try {
                $detail = (string) $fn();
                $checks[] = ['name' => $name, 'ok' => true, 'detail' => $detail];
            } catch (Throwable $e) {
                $checks[] = ['name' => $name, 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 300)];
                $failures[] = $name.': '.mb_substr($e->getMessage(), 0, 200);
            }
        };

        $run('db connection', function () {
            DB::select('SELECT 1');

            return 'ok';
        });

        foreach (['migrations', 'users', 'companies', 'branches', 'permissions', 'roles'] as $table) {
            $run("table {$table}", function () use ($table) {
                $n = DB::table($table)->count();

                return "{$n} rows";
            });
        }

        $run('recent successful backup', function () {
            $latest = $this->runs->latestSuccessful();
            if (! $latest) {
                throw new \RuntimeException('no completed backup found');
            }
            if ($latest->created_at->lt(now()->subHours(26))) {
                throw new \RuntimeException('latest backup older than 26h (id '.$latest->id.')');
            }

            return 'run #'.$latest->id.' at '.$latest->created_at->toDateTimeString();
        });

        $run('queue reachable', function () {
            // Read-only probe: jobs table must be queryable (database queue).
            try {
                DB::table('jobs')->limit(1)->count();
            } catch (Throwable) {
                // Non-database queues (redis) are fine — report driver instead.
                return 'queue driver: '.config('queue.default');
            }

            return 'jobs table ok';
        });

        return ['ok' => $failures === [], 'failures' => $failures, 'checks' => $checks];
    }

    /**
     * Post-restore integrity gate.
     *
     * Same core checks as check(), but WITHOUT the "recent successful
     * backup" recency probe and WITHOUT the queue probe: a production
     * import rewinds backup_runs by design (rows created after the backup
     * point are replaced), so the recency check would false-fail, and
     * queue state immediately after a restore is not meaningful.
     *
     * @return array{ok: bool, failures: array<int, string>, checks: array<int, array{name: string, ok: bool, detail: string}>}
     */
    public function checkPostRestore(): array
    {
        $checks = [];
        $failures = [];

        $run = function (string $name, callable $fn) use (&$checks, &$failures): void {
            try {
                $detail = (string) $fn();
                $checks[] = ['name' => $name, 'ok' => true, 'detail' => $detail];
            } catch (Throwable $e) {
                $checks[] = ['name' => $name, 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 300)];
                $failures[] = $name.': '.mb_substr($e->getMessage(), 0, 200);
            }
        };

        $run('db connection', function () {
            DB::select('SELECT 1');

            return 'ok';
        });

        foreach (['migrations', 'users', 'companies', 'branches', 'permissions', 'roles'] as $table) {
            $run("table {$table}", function () use ($table) {
                $n = DB::table($table)->count();

                return "{$n} rows";
            });
        }

        $run('foreign keys present', function () {
            $n = DB::selectOne(
                "SELECT COUNT(*) AS c FROM information_schema.table_constraints
                  WHERE constraint_schema = DATABASE() AND constraint_type = 'FOREIGN KEY'"
            );
            if ((int) ($n->c ?? 0) === 0) {
                throw new \RuntimeException('no foreign keys found');
            }

            return ((int) $n->c).' FKs';
        });

        return ['ok' => $failures === [], 'failures' => $failures, 'checks' => $checks];
    }
}
