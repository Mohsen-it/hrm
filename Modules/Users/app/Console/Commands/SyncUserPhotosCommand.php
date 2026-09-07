<?php

namespace Modules\Users\Console\Commands;

use Illuminate\Console\Command;
use Modules\Users\Models\User;

/**
 * Sync HR photos from public/photo into users.avatar.
 *
 * Filename (without extension) must equal users.employee_code, e.g.
 * public/photo/20001.jpg → employee_code 20001.
 *
 * The avatar URL is then a pure O(1) string concat at runtime
 * (see User::getAvatarUrlAttribute) — no file_exists() per request,
 * so lists never slow down regardless of photo count.
 */
class SyncUserPhotosCommand extends Command
{
    protected $signature = 'users:sync-photos
        {--force : Overwrite avatars that were uploaded manually (non photo/* paths)}
        {--dry-run : Report matches without writing to the database}';

    protected $description = 'Link public/photo images to users via employee_code';

    /**
     * @return int Command::SUCCESS|Command::FAILURE
     */
    public function handle(): int
    {
        $photoDir = public_path('photo');

        if (! is_dir($photoDir)) {
            $this->error("Directory not found: {$photoDir}");

            return self::FAILURE;
        }

        // Scan once (no GLOB_BRACE for Windows compat) — case-insensitive extensions.
        $allowed = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];
        $files = [];

        foreach (scandir($photoDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

            if (isset($allowed[$ext])) {
                // Lower-cased key for matching; original name kept for the URL
                // (Linux servers are case-sensitive).
                $files[mb_strtolower(pathinfo($entry, PATHINFO_FILENAME))] = $entry;
            }
        }

        if ($files === []) {
            $this->warn('No photos found in public/photo.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $matched = 0;
        $skippedManual = 0;
        $missing = 0;

        // Single query — all matching is done in memory.
        $users = User::withoutSuperAdmin()->get(['id', 'employee_code', 'avatar']);
        $this->info('Photos: '.count($files).' — Users: '.$users->count());

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $bar->advance();

            $code = $user->employee_code !== null
                ? mb_strtolower(trim((string) $user->employee_code))
                : '';

            if ($code === '' || ! isset($files[$code])) {
                $missing++;

                continue;
            }

            $wanted = 'photo/'.$files[$code];

            if ($user->avatar === $wanted) {
                continue;
            }

            // Preserve manually uploaded avatars unless --force is given.
            if ($user->avatar !== null && ! str_starts_with(ltrim($user->avatar, '/'), 'photo/') && ! $force) {
                $skippedManual++;

                continue;
            }

            $matched++;

            if (! $dryRun) {
                User::where('id', $user->id)->update(['avatar' => $wanted]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("[dry-run] Would link {$matched} users, {$missing} without photo, {$skippedManual} manual avatars kept.");
        } else {
            $this->info("Linked {$matched} users, {$missing} without photo, {$skippedManual} manual avatars kept.");
        }

        return self::SUCCESS;
    }
}
