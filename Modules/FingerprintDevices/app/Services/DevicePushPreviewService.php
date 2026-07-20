<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Users\Models\User;

/**
 * DevicePushPreviewService — read-only impact analysis for a push operation.
 *
 * This service NEVER writes to the database and NEVER writes to the device.
 * It only reads:
 *   - the device's current user list (via the bridge adapter, GET-only)
 *   - the local user_fingerprints table
 *   - the local users table
 *
 * The result is a structured report that the operator reviews before
 * committing to an actual push.
 */
class DevicePushPreviewService
{
    public function __construct(
        private DeviceAdapterResolver $adapterResolver,
    ) {}

    /**
     * Build a preview of what an actual push would do, without performing it.
     *
     * @param  array<string, mixed>  $options  Same shape as DevicePushService::push
     * @return array<string, mixed>
     */
    public function preview(FingerprintDevice $device, array $options): array
    {
        $options = $this->normalizeOptions($options);
        $mode = (string) ($options['select_mode'] ?? 'all');

        $candidateUserIds = $this->resolveCandidateUserIds($mode, $options);

        $deviceReadOk = false;
        $deviceReadError = null;
        $existingCodes = [];
        try {
            $adapter = $this->resolveAdapter($device);
            $deviceUsers = $adapter->getUsers(
                $device->ip_address,
                $device->port,
                (string) $device->comm_key,
                (int) $device->timeout,
            );
            foreach ($deviceUsers as $du) {
                $code = (string) ($du['user_id'] ?? '');
                if ($code !== '') {
                    $existingCodes[$code] = true;
                }
            }
            $deviceReadOk = true;
        } catch (\Throwable $e) {
            $deviceReadError = $e->getMessage();
            Log::warning('DevicePushPreviewService: device getUsers failed', [
                'device_id' => $device->id,
                'error' => $deviceReadError,
            ]);
        }

        $candidates = User::query()
            ->whereIn('id', $candidateUserIds)
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->get(['id', 'name', 'employee_code']);

        $existingSet = array_keys($existingCodes);
        $existingNormalized = array_flip(array_map('strval', $existingSet));

        $wouldAddUsers = [];
        $wouldSkipUsers = [];
        $skippedNoCode = 0;

        foreach ($candidates as $user) {
            $code = (string) $user->employee_code;
            if ($code === '') {
                $skippedNoCode++;

                continue;
            }
            if (isset($existingNormalized[$code])) {
                $wouldSkipUsers[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_code' => $code,
                ];
            } else {
                $wouldAddUsers[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_code' => $code,
                ];
            }
        }

        $fpQuery = UserFingerprint::query()
            ->whereIn('user_id', $candidates->pluck('id'))
            ->where('device_id', $device->id)
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '');

        $fpsForThisDevice = (clone $fpQuery)->count();
        $fpsForThisDeviceUsers = (clone $fpQuery)->distinct('user_id')->count('user_id');

        $fpsAnyDevice = UserFingerprint::query()
            ->whereIn('user_id', $candidates->pluck('id'))
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->count();

        $noFingerprints = $candidates->isNotEmpty() && $fpsForThisDevice === 0 && $fpsAnyDevice === 0;
        $fingerprintsOnOtherDevices = $candidates->isNotEmpty() && $fpsForThisDevice === 0 && $fpsAnyDevice > 0;

        $warnings = [];
        if (! $deviceReadOk) {
            $warnings[] = [
                'code' => 'device_unreachable',
                'message' => __('fingerprint_devices.preview_device_unreachable', [
                    'error' => $deviceReadError ?? 'unknown',
                ]),
            ];
        }
        if ($noFingerprints) {
            $warnings[] = [
                'code' => 'no_fingerprints_anywhere',
                'message' => __('fingerprint_devices.preview_no_fingerprints_anywhere'),
            ];
        } elseif ($fingerprintsOnOtherDevices) {
            $warnings[] = [
                'code' => 'fingerprints_on_other_devices',
                'message' => __('fingerprint_devices.preview_fingerprints_on_other_devices', [
                    'count' => $fpsAnyDevice,
                ]),
            ];
        }
        if ($candidates->isEmpty()) {
            $warnings[] = [
                'code' => 'no_candidates',
                'message' => __('fingerprint_devices.preview_no_candidates'),
            ];
        }
        if ($skippedNoCode > 0) {
            $warnings[] = [
                'code' => 'users_without_employee_code',
                'message' => __('fingerprint_devices.preview_users_without_employee_code', [
                    'count' => $skippedNoCode,
                ]),
            ];
        }

        $payload = [
            'success' => true,
            'read_only' => true,
            'device_id' => $device->id,
            'device_name' => $device->name,
            'mode' => $mode,
            'branch_id' => $options['branch_id'] ?? null,
            'options' => [
                'push_users' => (bool) ($options['push_users'] ?? false),
                'push_fingerprints' => (bool) ($options['push_fingerprints'] ?? false),
                'push_face_photos' => (bool) ($options['push_face_photos'] ?? false),
            ],
            'totals' => [
                'candidates' => $candidates->count(),
                'would_add_users' => count($wouldAddUsers),
                'would_skip_existing_users' => count($wouldSkipUsers),
                'skipped_no_employee_code' => $skippedNoCode,
                'would_push_fingerprints' => $fpsForThisDevice,
                'fingerprints_for_distinct_users' => $fpsForThisDeviceUsers,
                'fingerprints_on_other_devices' => $fpsAnyDevice,
                'device_user_count' => count($existingCodes),
            ],
            'samples' => [
                'add' => array_slice($wouldAddUsers, 0, 10),
                'skip' => array_slice($wouldSkipUsers, 0, 10),
            ],
            'warnings' => $warnings,
        ];

        if (! empty($options['user_ids']) && is_array($options['user_ids'])) {
            $payload['user_ids'] = array_map('intval', $options['user_ids']);
        }

        return $payload;
    }

    /**
     * Mirror of {@see DevicePushService::resolveUserIds()} — kept private
     * and isolated so the preview path can never accidentally trigger writes.
     *
     * @return array<int, int>
     */
    private function resolveCandidateUserIds(string $mode, array $options): array
    {
        if (! empty($options['user_ids']) && is_array($options['user_ids'])) {
            return array_map('intval', $options['user_ids']);
        }

        if ($mode === 'branch' || ! empty($options['branch_id'])) {
            $branchId = (int) ($options['branch_id'] ?? 0);
            if ($branchId > 0) {
                return User::query()
                    ->where('branch_id', $branchId)
                    ->where('is_active_employee', true)
                    ->pluck('id')
                    ->toArray();
            }
        }

        if ($mode === 'all') {
            return User::query()
                ->where('is_active_employee', true)
                ->pluck('id')
                ->toArray();
        }

        return User::query()
            ->where('is_active_employee', true)
            ->where('status', 1)
            ->pluck('id')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $options): array
    {
        if (empty($options['select_mode']) && ! empty($options['branch_id'])) {
            $options['select_mode'] = 'branch';
        } elseif (empty($options['select_mode'])) {
            $options['select_mode'] = 'all';
        }

        return $options;
    }

    private function resolveAdapter(FingerprintDevice $device): DeviceAdapterInterface
    {
        $typeName = strtolower($device->deviceType->manufacturer ?? '');

        $driver = match (true) {
            str_contains($typeName, 'zkteco'), str_contains($typeName, 'zk') => 'zkteco',
            str_contains($typeName, 'suprema') => 'suprema',
            str_contains($typeName, 'hikvision'), str_contains($typeName, 'hik') => 'hikvision',
            default => config('attendanceintegration.default_driver', 'zkteco'),
        };

        return $this->adapterResolver->getAdapter($driver);
    }
}
