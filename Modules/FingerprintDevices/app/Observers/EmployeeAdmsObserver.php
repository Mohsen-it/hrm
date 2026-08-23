<?php

namespace Modules\FingerprintDevices\Observers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\BridgeBiometricSyncService;
use Modules\Users\Models\User;

class EmployeeAdmsObserver
{
    public function __construct(
        private BridgeBiometricSyncService $bridgeSync,
    ) {}

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
     * Queue DATA UPDATE USER commands for all eligible devices.
     */
    private function queueUserCommands(User $user, string $action): void
    {
        $pin = (string) $user->employee_code;
        $name = $this->getDisplayName($user);
        $privilege = $user->isSuperAdmin() ? 14 : 0; // Super admin gets full privilege

        $devices = $this->zktecoDevices();

        foreach ($devices as $device) {
            try {
                // Terminals reject user/biometric WRITE commands served over
                // the ADMS push channel (Return=-3/-30/-1xx), while the pyzk
                // bridge write is proven across this fleet. Idempotent.
                $ok = $this->bridgeSync->syncUser($device, $pin, $name, $privilege);

                Log::info('ADMS_USER_'.strtoupper($action).'_'.($ok ? 'SYNCED' : 'FAILED'), [
                    'user_id' => $user->id,
                    'employee_code' => $pin,
                    'name' => $name,
                    'device_id' => $device->id,
                    'device_serial' => $device->serial_number,
                ]);
            } catch (\Throwable $e) {
                Log::error('ADMS_USER_'.strtoupper($action).'_QUEUE_FAILED', [
                    'user_id' => $user->id,
                    'employee_code' => $pin,
                    'device_id' => $device->id,
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
}
