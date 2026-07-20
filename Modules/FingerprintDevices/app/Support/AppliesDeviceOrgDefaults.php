<?php

namespace Modules\FingerprintDevices\Support;

use Modules\FingerprintDevices\Models\FingerprintDevice;

/**
 * Resolves the company / branch / subordination defaults that should be
 * applied to a freshly imported user, based on the device they were
 * recovered from.
 *
 * Existing values are never overwritten — we only fill the empty slots.
 */
trait AppliesDeviceOrgDefaults
{
    /**
     * Apply device-level defaults to a user-create payload.
     *
     * @param  array<string, mixed>  $userData  The fields that will be passed to User::create().
     * @return array<string, mixed> The same array with defaults merged in for null fields.
     */
    protected function applyDeviceOrgDefaults(FingerprintDevice $device, array $userData): array
    {
        if (empty($userData['company_id']) && ! empty($device->default_company_id)) {
            $userData['company_id'] = $device->default_company_id;
        }

        if (empty($userData['branch_id']) && ! empty($device->default_branch_id)) {
            $userData['branch_id'] = $device->default_branch_id;
        }

        if (empty($userData['subordination_id']) && ! empty($device->default_subordination_id)) {
            $userData['subordination_id'] = $device->default_subordination_id;
        }

        return $userData;
    }
}
