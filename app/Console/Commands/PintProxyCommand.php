<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PintProxyCommand extends Command
{
    protected $signature = 'pint
        {paths?* : Files or directories to format (defaults to the whole project)}
        {--test : Test for style violations without fixing them}
        {--dirty : Only process files with uncommitted changes}';

    protected $description = 'Run Laravel Pint (vendor/bin/pint) via Artisan';

    /**
     * Proxy to the Pint binary so "php artisan pint" works in every environment.
     */
    public function handle(): int
    {
        $binary = base_path('vendor/bin/pint');

        if (! is_file($binary)) {
            $this->error('Pint is not installed (expected at vendor/bin/pint).');
            $this->line('Install dev dependencies with: composer install');

            return Command::FAILURE;
        }

        $args = [];
        if ($this->option('test')) {
            $args[] = '--test';
        }
        if ($this->option('dirty')) {
            $args[] = '--dirty';
        }
        foreach ((array) $this->argument('paths') as $path) {
            $args[] = (string) $path;
        }

        $command = '"'.PHP_BINARY.'" '.escapeshellarg($binary);
        foreach ($args as $arg) {
            $command .= ' '.escapeshellarg($arg);
        }

        $exitCode = 0;
        passthru($command, $exitCode);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
