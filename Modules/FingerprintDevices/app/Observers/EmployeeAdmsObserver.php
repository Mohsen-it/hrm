<?php

namespace Modules\FingerprintDevices\Observers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Jobs\SyncUserToDeviceViaBridgeJob;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\BridgeBiometricSyncService;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\Users\Models\User;

class EmployeeAdmsObserver
{
    public function __construct(
        private BridgeBiometricSyncService $bridgeSync,
        private ?DeviceCommandService $commandService = null,
    ) {
        $this->commandService ??= app(DeviceCommandService::class);
    }

    /**
     * PIN renames observed in updating(), consumed in updated().
     *
     * Eloquent syncs original attributes before the updated event fires,
     * so the previous employee_code is only visible during updating().
     * Keyed by user id and always consumed (or discarded) in updated(),
     * so a cancelled update can never leak into a later one.
     *
     * @var array<int, string>
     */
    private array $pendingPinRenames = [];

    /**
     * Handle the User "created" event.
     *
     * Queues DATA UPDATE USER command for all ADMS-enabled ZKTeco devices.
     */
    public function created(User $user): void
    {
        if (! $this->shouldProcess($user)) {
            return;
        }

        $this->queueUserCommands($user, 'created');
    }

    /**
     * Capture the previous employee_code before Eloquent syncs attributes.
     */
    public function updating(User $user): void
    {
        if (! $user->isDirty('employee_code')) {
            return;
        }

        $oldPin = (string) $user->getOriginal('employee_code');
        if ($oldPin !== '' && $user->id !== null) {
            $this->pendingPinRenames[(int) $user->id] = $oldPin;
        }
    }

    /**
     * Handle the User "updated" event.
     *
     * Queues DATA UPDATE USER command for all ADMS-enabled ZKTeco devices
     * when relevant fields change (employee_code, name, privilege).
     *
     * When employee_code (PIN) changes:
     *  1. Renames biometrics in DB (old PIN → new PIN)
     *  2. Creates new user on devices with new PIN
     *  3. Pushes all templates to devices with new PIN
     *  The old user stays on the device (user can manually delete later).
     */
    public function updated(User $user): void
    {
        if (! $this->shouldProcess($user)) {
            return;
        }

        // Only queue if relevant fields changed. NOTE: getChanges(), not
        // getDirty() — Eloquent syncs attributes before the updated event
        // fires, so getDirty() is always empty here and updates would
        // silently never propagate.
        $relevantFields = ['employee_code', 'name', 'full_name_ar', 'full_name_en', 'privilege', 'device_privilege', 'status', 'is_active_employee'];
        $changed = array_intersect($relevantFields, array_keys($user->getChanges()));

        if (empty($changed)) {
            unset($this->pendingPinRenames[(int) $user->id]);

            return;
        }

        // When employee_code (PIN) changes: copy biometrics to new PIN
        if (in_array('employee_code', $changed, true) && $user->wasChanged('employee_code')) {
            $oldPin = $this->pendingPinRenames[(int) $user->id] ?? '';
            unset($this->pendingPinRenames[(int) $user->id]);
            $newPin = (string) $user->employee_code;

            if ($oldPin !== '' && $oldPin !== $newPin) {
                $this->copyBiometricsToNewPin($user, $oldPin, $newPin);
            }
        } else {
            unset($this->pendingPinRenames[(int) $user->id]);
        }

        $this->queueUserCommands($user, 'updated');
    }

    /**
     * Handle the User "deleted" event.
     *
     * Queues DATA DELETE USER command for all ADMS-enabled ZKTeco devices.
     * Deletion must always propagate, regardless of the user's active status
     * (an inactive or soft-deleted employee may still exist on the terminals).
     */
    public function deleted(User $user): void
    {
        $pin = (string) ($user->employee_code ?? '');

        if ($pin === '' || $user->isSuperAdmin()) {
            return;
        }

        // Bulk-delete circuit breaker: a burst of deletions (bad import,
        // mistaken bulk archive) must never wipe terminals unattended.
        // Single deletions flow exactly as before.
        if ($this->deleteBreakerTripped($user, $pin)) {
            return;
        }

        $devices = $this->zktecoDevices();

        foreach ($devices as $device) {
            try {
                $this->commandService->queueUserDelete(
                    $device->id,
                    $pin,
                );

                Log::info('ADMS_USER_DELETE_QUEUED', [
                    'user_id' => $user->id,
                    'employee_code' => $user->employee_code,
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                ]);
            } catch (\Throwable $e) {
                Log::error('ADMS_USER_DELETE_QUEUE_FAILED', [
                    'user_id' => $user->id,
                    'employee_code' => $user->employee_code,
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the User "restored" event (soft delete restoration).
     *
     * Re-creates the user on all devices.
     */
    public function restored(User $user): void
    {
        $this->created($user);
    }

    /**
     * Bulk-delete circuit breaker (fail-safe, never deletes data).
     *
     * Counts User delete EVENTS (not per-device commands) in a sliding
     * window. Under the threshold this is a pure counter increment and the
     * caller proceeds unchanged. Over the threshold the delete is HELD:
     * nothing is queued, the PIN is recorded for 24h operator replay
     * (fingerprints:process-skipped-deletes), and a critical log is written.
     */
    private function deleteBreakerTripped(User $user, string $pin): bool
    {
        $threshold = max(1, (int) config('fingerprintdevices.delete_breaker_threshold', 5));
        $windowMinutes = max(1, (int) config('fingerprintdevices.delete_breaker_window_minutes', 10));

        $count = (int) Cache::get('adms:user_delete_breaker', 0);

        if ($count >= $threshold) {
            $skipped = Cache::get('adms:skipped_user_deletes', []);
            $skipped[$pin] = now()->toDateTimeString();
            Cache::put('adms:skipped_user_deletes', $skipped, now()->addDay());

            Log::critical('ADMS_USER_DELETE_BREAKER_TRIPPED', [
                'user_id' => $user->id,
                'employee_code' => $pin,
                'deletes_in_window' => $count,
                'threshold' => $threshold,
                'action' => 'Delete HELD for devices. Replay: php artisan fingerprints:process-skipped-deletes',
            ]);

            return true;
        }

        Cache::put('adms:user_delete_breaker', $count + 1, now()->addMinutes($windowMinutes));

        return false;
    }

    /**
     * Queue user creation/update via the configured channel (ADMS unification).
     *
     * Channel is controlled by `fingerprintdevices.push_user_via`:
     *  - adms  : queue ADMS command only (requested by user, no TCP burst)
     *  - bridge: direct TCP via pyzk (legacy)
     *  - both  : ADMS + bridge (max reliability)
     */
    private function queueUserCommands(User $user, string $action): void
    {
        $pin = (string) $user->employee_code;
        $name = $this->getDisplayName($user);
        $privilege = $user->devicePrivilege();

        $devices = $this->zktecoDevices();
        $via = config('fingerprintdevices.push_user_via', 'adms');

        foreach ($devices as $device) {
            try {
                $admsQueued = false;
                $bridgeOk = null;

                if (in_array($via, ['adms', 'both'], true)) {
                    // Created identities use user_create; later edits use
                    // user_update so pending rows merge instead of stacking.
                    // Wire bodies are identical (DATA UPDATE USERINFO upsert).
                    if ($action === 'updated') {
                        $this->commandService->queueUserUpdate(
                            $device->id,
                            $pin,
                            $name,
                            $privilege,
                        );
                    } else {
                        $this->commandService->queueUserCreate(
                            $device->id,
                            $pin,
                            $name,
                            $privilege,
                        );
                    }
                    $admsQueued = true;
                }

                if (in_array($via, ['bridge', 'both'], true)) {
                    // Bridge must be async: otherwise a powered-off device blocks the HTTP request for minutes
                    SyncUserToDeviceViaBridgeJob::dispatch($device->id, $pin, $name, $privilege);
                    $bridgeOk = 'queued';
                }

                Log::info('ADMS_USER_'.strtoupper($action), [
                    'user_id' => $user->id,
                    'employee_code' => $pin,
                    'name' => $name,
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                    'via' => $via,
                    'adms_queued' => $admsQueued,
                    'bridge_queued' => $bridgeOk === 'queued',
                ]);
            } catch (\Throwable $e) {
                Log::error('ADMS_USER_'.strtoupper($action).'_QUEUE_FAILED', [
                    'user_id' => $user->id,
                    'employee_code' => $pin,
                    'device_id' => $device->id,
                    'via' => $via ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * All ADMS-enabled ZKTeco terminals (driver name is resolved through the
     * device-type relation, not a column on the devices table).
     *
     * @return Collection<int, FingerprintDevice>
     */
    private function zktecoDevices(): Collection
    {
        return FingerprintDevice::query()
            ->with('deviceType')
            ->where('is_push_enabled', true)
            ->get()
            ->filter(fn (FingerprintDevice $device) => $device->getDriverName() === 'zkteco');
    }

    /**
     * Determine if this user should trigger ADMS commands.
     */
    private function shouldProcess(User $user): bool
    {
        // Must have an employee code (PIN)
        if (empty($user->employee_code)) {
            return false;
        }

        // Skip super admin
        if ($user->isSuperAdmin()) {
            return false;
        }

        // Only process active employees
        if (! $user->isActive()) {
            // Still allow deleted users to trigger DELETE commands
            return false;
        }

        return true;
    }

    /**
     * Get the display name for the device (prefers Arabic).
     */
    private function getDisplayName(User $user): string
    {
        return $user->full_name_ar
            ?? $user->full_name_en
            ?? $user->name
            ?? $user->employee_code;
    }

    /**
     * Copy biometrics from old PIN to new PIN.
     *
     * When employee_code changes:
     *  1. Renames biometrics in DB (old PIN → new PIN)
     *  2. Pushes all templates to all devices with the new PIN
     *  The old user stays on the device (not deleted).
     */
    private function copyBiometricsToNewPin(User $user, string $oldPin, string $newPin): void
    {
        $devices = $this->zktecoDevices();
        $via = config('fingerprintdevices.push_user_via', 'adms');

        // Step 1: Rename biometrics in DB
        $renamed = DB::table('biometrics')
            ->where('employee_pin', $oldPin)
            ->whereNull('deleted_at')
            ->update(['employee_pin' => $newPin]);

        Log::info('BIOMETRICS_PIN_RENAMED', [
            'user_id' => $user->id,
            'old_pin' => $oldPin,
            'new_pin' => $newPin,
            'templates_renamed' => $renamed,
        ]);

        // Step 2: Push all templates to devices with the new PIN
        $templates = DB::table('biometrics')
            ->where('employee_pin', $newPin)
            ->whereNull('deleted_at')
            ->get();

        if ($templates->isEmpty()) {
            Log::info('BIOMETRICS_PIN_COPY_NO_TEMPLATES', [
                'user_id' => $user->id,
                'new_pin' => $newPin,
            ]);

            return;
        }

        foreach ($devices as $device) {
            foreach ($templates as $template) {
                try {
                    $attributes = [
                        'no' => 0,
                        'index' => $template->finger_index ?? 0,
                        'valid' => $template->valid ?? 1,
                        'duress' => 0,
                        'major_ver' => $template->major_ver ?? 0,
                        'minor_ver' => $template->minor_ver ?? 0,
                        'format' => $template->format ?? 0,
                    ];

                    if ((int) $template->bio_type === 1) {
                        // Fingerprint
                        if (in_array($via, ['adms', 'both'], true)) {
                            $this->commandService->queueFingerprintTemplate(
                                $device->id,
                                $newPin,
                                $template->template_data,
                                $attributes,
                                $template->template_hash,
                            );
                        }
                    } else {
                        // Face template (bio_type=9 or 2)
                        if (in_array($via, ['adms', 'both'], true)) {
                            $this->commandService->queueFaceTemplate(
                                $device->id,
                                $newPin,
                                $template->template_data,
                                $attributes,
                                $template->template_hash,
                            );
                        }
                    }

                    Log::info('BIOMETRICS_TEMPLATE_QUEUED', [
                        'user_id' => $user->id,
                        'new_pin' => $newPin,
                        'bio_type' => $template->bio_type,
                        'finger_index' => $template->finger_index,
                        'device_id' => $device->id,
                        'device_serial' => $device->serial_number,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('BIOMETRICS_TEMPLATE_QUEUE_FAILED', [
                        'user_id' => $user->id,
                        'new_pin' => $newPin,
                        'bio_type' => $template->bio_type,
                        'finger_index' => $template->finger_index,
                        'device_id' => $device->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('BIOMETRICS_PIN_COPY_COMPLETE', [
            'user_id' => $user->id,
            'old_pin' => $oldPin,
            'new_pin' => $newPin,
            'templates_count' => $templates->count(),
            'devices_count' => $devices->count(),
        ]);
    }
}
