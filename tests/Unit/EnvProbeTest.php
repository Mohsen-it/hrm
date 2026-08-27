<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * TEMPORARY probe test — prints what env vars the PHPUnit test process sees.
 * Deleted after diagnosing the DB isolation problem.
 */
class EnvProbeTest extends BaseTestCase
{
    public function test_probe_environment(): void
    {
        fwrite(STDERR, "\n[PROBE] getenv DB_CONNECTION = ".var_export(getenv('DB_CONNECTION'), true)."\n");
        fwrite(STDERR, '[PROBE] getenv DB_DATABASE   = '.var_export(getenv('DB_DATABASE'), true)."\n");
        fwrite(STDERR, '[PROBE] getenv APP_ENV       = '.var_export(getenv('APP_ENV'), true)."\n");
        fwrite(STDERR, '[PROBE] $_ENV DB_CONNECTION  = '.var_export($_ENV['DB_CONNECTION'] ?? 'unset', true)."\n");
        fwrite(STDERR, '[PROBE] $_SERVER DB_CONNECTION = '.var_export($_SERVER['DB_CONNECTION'] ?? 'unset', true)."\n");

        $this->assertTrue(true);
    }
}
