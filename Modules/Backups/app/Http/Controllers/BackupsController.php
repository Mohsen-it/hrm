<?php

namespace Modules\Backups\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Backups\Http\Requests\RestoreBackupRequest;
use Modules\Backups\Repositories\BackupRunRepository;
use Modules\Backups\Services\BackupHealthService;
use Modules\Backups\Services\BackupRestoreService;
use Modules\Backups\Services\BackupRetentionService;
use Modules\Backups\Services\BackupService;
use Modules\Backups\Services\BackupVerificationService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Thin HTTP layer — all logic lives in Services (plan §5).
 */
class BackupsController extends Controller
{
    public function __construct(
        private BackupService $backupService,
        private BackupVerificationService $verification,
        private BackupRestoreService $restore,
        private BackupRetentionService $retention,
        private BackupHealthService $health,
        private BackupRunRepository $runs,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('view-backups');

        return Inertia::render('Backups/Index', [
            'filters' => fn () => $request->only(['status', 'type', 'verification_status']),
            'backups' => fn () => $this->runs->paginate(
                $request->only(['status', 'type', 'verification_status']),
                $request->input('per_page', 20)
            ),
            'health' => fn () => $this->backupService->healthProbe(),
        ]);
    }

    public function settings(): Response
    {
        $this->authorize('manage-backup-settings');

        // Use the first (or default) config row; create if missing.
        $config = \Modules\Backups\Models\BackupConfig::firstOrCreate(
            ['name' => 'default'],
            [
                'frequency' => 'daily',
                'timezone' => config('backups.timezone', 'Asia/Damascus'),
                'retention_daily' => config('backups.retention_daily', 14),
                'retention_weekly' => config('backups.retention_weekly', 12),
                'retention_monthly' => config('backups.retention_monthly', 12),
                'encryption_enabled' => config('backups.encryption_enabled', true),
                'verification_enabled' => config('backups.verification_enabled', true),
                'notify_on_failure' => config('backups.notify_on_failure', true),
                'include_files' => false,
            ]
        );

        $lastBackup = $this->runs->latestSuccessful();

        return Inertia::render('Backups/Settings', [
            'config' => fn () => $this->formatConfig($config),
            'lastBackup' => $lastBackup ? $this->formatRunSummary($lastBackup) : null,
        ]);
    }

    public function updateSettings(\Illuminate\Http\Request $request): RedirectResponse
    {
        $this->authorize('manage-backup-settings');

        $validated = $request->validate([
            'frequency' => ['required', 'in:daily,weekly,monthly,manual_only'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'retention_daily' => ['required', 'integer', 'min:1', 'max:365'],
            'retention_weekly' => ['nullable', 'integer', 'min:1', 'max:52'],
            'retention_monthly' => ['nullable', 'integer', 'min:1', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'encryption_enabled' => ['boolean'],
            'verification_enabled' => ['boolean'],
            'notify_on_failure' => ['boolean'],
            'include_files' => ['boolean'],
        ]);

        $config = \Modules\Backups\Models\BackupConfig::firstOrCreate(
            ['name' => 'default'],
            [
                'is_enabled' => true,
                'frequency' => $validated['frequency'],
                'scheduled_time' => $validated['scheduled_time'],
                'day_of_week' => $validated['day_of_week'] ?? 0,
                'retention_daily' => $validated['retention_daily'],
                'retention_weekly' => $validated['retention_weekly'] ?? 12,
                'retention_monthly' => $validated['retention_monthly'] ?? 12,
                'timezone' => $validated['timezone'] ?? config('backups.timezone', 'Asia/Damascus'),
                'encryption_enabled' => $validated['encryption_enabled'] ?? true,
                'verification_enabled' => $validated['verification_enabled'] ?? true,
                'notify_on_failure' => $validated['notify_on_failure'] ?? true,
                'include_files' => $validated['include_files'] ?? false,
            ]
        );
        $config->update([
            'frequency' => $validated['frequency'],
            'scheduled_time' => $validated['scheduled_time'],
            'day_of_week' => $validated['day_of_week'] ?? $config->day_of_week,
            'retention_daily' => $validated['retention_daily'],
            'retention_weekly' => $validated['retention_weekly'] ?? $config->retention_weekly,
            'retention_monthly' => $validated['retention_monthly'] ?? $config->retention_monthly,
            'timezone' => $validated['timezone'] ?? $config->timezone,
            'encryption_enabled' => $validated['encryption_enabled'] ?? $config->encryption_enabled,
            'verification_enabled' => $validated['verification_enabled'] ?? $config->verification_enabled,
            'notify_on_failure' => $validated['notify_on_failure'] ?? $config->notify_on_failure,
            'include_files' => $validated['include_files'] ?? $config->include_files,
            'updated_by' => auth()->id(),
        ]);

        // Also refresh laravel config so the command immediately picks it up.
        $fresh = config('backups');
        $fresh['schedule']['daily_time'] = $validated['scheduled_time'];
        $fresh['schedule']['weekly_day'] = $validated['day_of_week'] ?? 0;
        $fresh['retention_daily'] = $validated['retention_daily'];
        $fresh['retention_weekly'] = $validated['retention_weekly'] ?? 12;
        $fresh['retention_monthly'] = $validated['retention_monthly'] ?? 12;
        $fresh['timezone'] = $validated['timezone'] ?? $fresh['timezone'] ?? 'Asia/Damascus';
        $fresh['encryption_enabled'] = $validated['encryption_enabled'] ?? true;
        $fresh['verification_enabled'] = $validated['verification_enabled'] ?? true;
        $fresh['notify_on_failure'] = $validated['notify_on_failure'] ?? true;
        $fresh['include_files'] = $validated['include_files'] ?? false;
        config(['backups' => $fresh]);
        \Illuminate\Support\Facades\Cache::forget('backups.config');

        return redirect()->route('backups.settings')->with('success', __('backups.settings_saved'));
    }

    private function formatConfig($config): array
    {
        return [
            'id' => $config->id,
            'name' => $config->name,
            'is_enabled' => (bool) $config->is_enabled,
            'frequency' => $config->frequency ?? 'daily',
            'scheduled_time' => $config->scheduled_time ?? '02:00',
            'day_of_week' => $config->day_of_week,
            'timezone' => $config->timezone ?? config('backups.timezone', 'Asia/Damascus'),
            'database_connection' => $config->database_connection ?? config('backups.database_connection', 'mysql'),
            'database_name' => $config->database_name ?? config('backups.database_name', 'hrmair'),
            'local_disk' => $config->local_disk ?? config('backups.local_disk', 'backups'),
            'remote_disk' => $config->remote_disk ?? config('backups.remote_disk'),
            'retention_daily' => (int) ($config->retention_daily ?? config('backups.retention_daily', 14)),
            'retention_weekly' => (int) ($config->retention_weekly ?? config('backups.retention_weekly', 12)),
            'retention_monthly' => (int) ($config->retention_monthly ?? config('backups.retention_monthly', 12)),
            'encryption_enabled' => (bool) ($config->encryption_enabled ?? config('backups.encryption_enabled', true)),
            'verification_enabled' => (bool) ($config->verification_enabled ?? config('backups.verification_enabled', true)),
            'notify_on_success' => (bool) ($config->notify_on_success ?? false),
            'notify_on_failure' => (bool) ($config->notify_on_failure ?? config('backups.notify_on_failure', true)),
            'include_files' => (bool) ($config->include_files ?? false),
        ];
    }

    private function getConfigArray(\Modules\Backups\Models\BackupConfig $config): array
    {
        return [
            'id' => $config->id ?? null,
            'name' => $config->name ?? 'default',
            'is_enabled' => $config->is_enabled ?? true,
            'frequency' => $config->frequency ?? 'daily',
            'scheduled_time' => $config->scheduled_time ?? config('backups.schedule.daily_time', '02:00'),
            'day_of_week' => $config->day_of_week ?? config('backups.schedule.weekly_day', 0),
            'timezone' => $config->timezone ?? config('backups.timezone', 'Asia/Damascus'),
            'database_connection' => $config->database_connection ?? config('backups.database_connection', 'mysql'),
            'database_name' => $config->database_name ?? config('backups.database_name', 'hrmair'),
            'local_disk' => $config->local_disk ?? config('backups.local_disk', 'backups'),
            'remote_disk' => $config->remote_disk ?? config('backups.remote_disk'),
            'retention_daily' => $config->retention_daily ?? config('backups.retention_daily', 14),
            'retention_weekly' => $config->retention_weekly ?? config('backups.retention_weekly', 12),
            'retention_monthly' => $config->retention_monthly ?? config('backups.retention_monthly', 12),
            'encryption_enabled' => $config->encryption_enabled ?? config('backups.encryption_enabled', true),
            'verification_enabled' => $config->verification_enabled ?? config('backups.verification_enabled', true),
            'notify_on_success' => $config->notify_on_success ?? false,
            'notify_on_failure' => $config->notify_on_failure ?? true,
            'include_files' => $config->include_files ?? false,
            'created_at' => $config->created_at?->toDateTimeString(),
            'updated_at' => $config->updated_at?->toDateTimeString(),
        ];
    }

    private function formatRunSummary($run): array
    {
        return [
            'id' => $run->id,
            'file_name' => $run->file_name,
            'file_size' => $run->file_size,
            'verification_status' => $run->verification_status,
            'status' => $run->status,
            'created_at' => $run->created_at?->toDateTimeString(),
        ];
    }

    public function show(int $id): Response
    {
        $this->authorize('view-backups');

        $run = $this->runs->findById($id);
        if (! $run) {
            abort(404);
        }

        return Inertia::render('Backups/Show', [
            'backup' => $run,
            'audit' => $run->auditLogs()->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create-backups');

        try {
            $run = $this->backupService->createBackup(['type' => 'manual']);
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('backups.show', $run->id)
            ->with('success', __('backups.created_successfully'));
    }

    public function verify(int $id): RedirectResponse
    {
        $this->authorize('verify-backups');

        $run = $this->runs->findById($id);
        if (! $run) {
            abort(404);
        }

        $ok = $this->verification->verify($run);

        return redirect()->back()->with(
            $ok ? 'success' : 'error',
            $ok ? __('backups.verified') : __('backups.verification_failed')
        );
    }

    public function download(int $id): StreamedResponse
    {
        $this->authorize('download-backups');

        $run = $this->runs->findById($id);
        if (! $run) {
            abort(404);
        }

        $disk = (string) config('backups.local_disk', 'backups');
        if (! \Storage::disk($disk)->exists($run->file_name)) {
            abort(404);
        }

        return \Storage::disk($disk)->download($run->file_name);
    }

    public function restoreTest(int $id): RedirectResponse
    {
        $this->authorize('restore-backups');

        $run = $this->runs->findById($id);
        if (! $run) {
            abort(404);
        }

        try {
            $this->restore->restoreTest($run, auth()->id(), 'manual UI test');
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('backups.restore_test_passed'));
    }

    public function restoreProduction(RestoreBackupRequest $request, int $id): RedirectResponse
    {
        $this->authorize('restore-backups-production');

        $run = $this->runs->findById($id);
        if (! $run) {
            abort(404);
        }

        try {
            $this->restore->restoreProduction($run, $request->validated()['reason'], auth()->id());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('backups.show', $run->id)
            ->with('success', __('backups.restored_production'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-backups');

        $run = $this->runs->findById($id);
        if (! $run) {
            abort(404);
        }

        $result = $this->retention->deleteRun($run, 'manual delete by '.(auth()->user()?->email ?? '?'));

        if ($result !== 'deleted') {
            return redirect()->back()->with('error', __('backups.delete_blocked'));
        }

        return redirect()->route('backups.index')->with('success', __('backups.deleted'));
    }
}
