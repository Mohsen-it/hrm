<?php

namespace Modules\FingerprintDevices\Observers;

use Illuminate\Support\Collection;
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
     * Handle the User "updated" event.
     *
     * Queues DATA UPDATE USER command for all ADMS-enabled ZKTeco devices
     * when relevant fields change (employee_code, name, privilege).
     *
     * When employee_code (PIN) changes, the OLD PIN is deleted from all
     * devices first, then the new PIN is created — preventing ghost entries.
     */
    public function updated(User $user): void
    {
        if (! $this->shouldProcess($user)) {
            return;
        }

        // Only queue if relevant fields changed
        $relevantFields = ['employee_code', 'name', 'full_name_ar', 'full_name_en', 'privilege', 'status', 'is_active_employee'];
        $changed = array_intersect($relevantFields, array_keys($user->getDirty()));

        if (empty($changed)) {
            return;
        }

        // When employee_code (PIN) changes, delete the old PIN from all devices
        // before creating the new one — prevents duplicate/ghost entries.
        if (in_array('employee_code', $changed, true)) {
            $oldPin = (string) $user->getOriginal('employee_code');
            $newPin = (string) $user->employee_code;

            if ($oldPin !== '' && $oldPin !== $newPin) {
                $this->deleteOldPin($user, $oldPin);
            }
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
        $privilege = $user->isSuperAdmin() ? 14 : 0;

        $devices = $this->zktecoDevices();
        $via = config('fingerprintdevices.push_user_via', 'adms');

        foreach ($devices as $device) {
            try {
                $admsQueued = false;
                $bridgeOk = null;

                if (in_array($via, ['adms', 'both'], true)) {
                    // ADMS is the canonical path per user request
                    $this->commandService->queueUserCreate(
                        $device->id,
                        $pin,
                        $name,
                        $privilege,
                    );
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
     * Delete the old PIN from all ADMS-enabled devices.
     *
     * Called when employee_code changes to prevent ghost entries on terminals.
     */
    private function deleteOldPin(User $user, string $oldPin): void
    {
        $devices = $this->zktecoDevices();
        $via = config('fingerprintdevices.push_user_via', 'adms');

        foreach ($devices as $device) {
            try {
                if (in_array($via, ['adms', 'both'], true)) {
                    $this->commandService->queueUserDelete($device->id, $oldPin);
                }

                Log::info('ADMS_USER_OLD_PIN_DELETED', [
                    'user_id' => $user->id,
                    'old_pin' => $oldPin,
                    'new_pin' => $user->employee_code,
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                ]);
            } catch (\Throwable $e) {
                Log::error('ADMS_USER_OLD_PIN_DELETE_FAILED', [
                    'user_id' => $user->id,
                    'old_pin' => $oldPin,
                    'device_id' => $device->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
