<?php

namespace Modules\Backups\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Repositories\BackupRunRepository;

class BackupListCommand extends Command
{
    protected $signature = 'backup:list {--limit=20 : Rows to show}';

    protected $description = 'List recent backups with status and verification';

    public function handle(BackupRunRepository $runs): int
    {
        $rows = $runs->query()->latest()->limit((int) $this->option('limit'))->get()
            ->map(fn ($r) => [
                $r->id,
                $r->type,
                $r->status,
                $r->file_name,
                $r->file_size ? round($r->file_size / 1024 / 1024, 1).' MB' : '-',
                $r->verification_status,
                $r->created_at?->toDateTimeString(),
            ])->all();

        $this->table(['ID', 'Type', 'Status', 'File', 'Size', 'Verified', 'Created'], $rows);

        return self::SUCCESS;
    }
}
