<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Services\MasterFingerprintService;
use Modules\Users\Models\User;

/**
 * ProcessMasterFingerprints — ensure every user has a single master template.
 *
 * For every user that has at least one fingerprint, this command picks the
 * template with the highest `quality` (or the most recent one when the
 * quality is tied) and marks it as the user's master template. Users with
 * zero templates are left untouched.
 */
class ProcessMasterFingerprints extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fingerprints:process-masters
                            {--user= : Restrict the pass to one user id}
                            {--dry-run : Do not persist changes}';

    /**
     * The console command description.
     */
    protected $description = 'Designate a single master fingerprint per user based on quality / recency';

    /**
     * Execute the console command.
     */
    public function handle(MasterFingerprintService $masters): int
    {
        $userId = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');

        $query = User::query();
        if ($userId !== null) {
            $query->where('id', (int) $userId);
        }

        $candidates = $query
            ->whereIn('id', UserFingerprint::query()->whereNull('deleted_at')->select('user_id'))
            ->get(['id', 'name']);

        $updated = 0;
        foreach ($candidates as $user) {
            $winner = UserFingerprint::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->orderByDesc('quality')
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->first();

            if (! $winner) {
                continue;
            }

            if ($dryRun) {
                $this->line("Would set fingerprint #{$winner->id} as master for user #{$user->id} ({$user->name})");

                continue;
            }

            $masters->setAsMaster($user->id, $winner->id);
            $updated++;
        }

        $this->info('Done. '.($dryRun ? 'Would update' : 'Updated')." {$updated} user(s).");

        return self::SUCCESS;
    }
}
