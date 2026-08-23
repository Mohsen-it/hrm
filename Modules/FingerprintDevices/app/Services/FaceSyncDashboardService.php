<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Users\Models\User;

/**
 * FaceSyncDashboardService — aggregates face-sync health data across all devices.
 *
 * Provides a read-only snapshot used by the FaceSyncDashboardController so the
 * operator can verify that face templates have been distributed to every
 * target device and are correctly assigned to the right employee.
 */
class FaceSyncDashboardService
{
    public function __construct(
        private DeviceCommandService $commandService,
    ) {}

    /**
     * Full dashboard payload.
     */
    public function getDashboardData(): array
    {
        return [
            'summary' => $this->getGlobalSummary(),
            'devices' => $this->getDeviceFaceSyncStatus(),
            'employees' => $this->getEmployeeFaceCoverage(),
            'commandQueue' => $this->getCommandQueueStats(),
            'recentSyncLogs' => $this->getRecentSyncLogs(),
            'failedCommands' => $this->getFailedCommands(),
        ];
    }

    /**
     * Global summary stats: total face templates, devices, employees, sync health.
     */
    public function getGlobalSummary(): array
    {
        $totalFaceTemplates = UserFingerprint::query()
            ->where('template_type', 'face')
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->count();

        $employeesWithFaces = UserFingerprint::query()
            ->where('template_type', 'face')
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->distinct('user_id')
            ->count('user_id');

        $totalActiveEmployees = User::query()
            ->where('is_active_employee', true)
            ->count();

        $totalDevices = FingerprintDevice::query()->active()->count();
        $onlineDevices = FingerprintDevice::query()->online()->count();
        $offlineDevices = FingerprintDevice::query()->offline()->count();

        $pendingFaceCommands = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->count();

        $failedFaceCommands = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->where('status', DeviceCommand::STATUS_FAILED)
            ->count();

        $completedFaceCommands = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->where('status', DeviceCommand::STATUS_COMPLETED)
            ->count();

        $totalFaceCommands = $pendingFaceCommands + $failedFaceCommands + $completedFaceCommands;

        // Coverage %: what % of active employees have at least one face template
        $coveragePercent = $totalActiveEmployees > 0
            ? round(($employeesWithFaces / $totalActiveEmployees) * 100, 1)
            : 0;

        // Distribution completeness: of all active online devices, what % have face templates from all employees
        $totalFaceDeviceLinks = UserFingerprint::query()
            ->where('template_type', 'face')
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->distinct('device_id')
            ->count('device_id');

        $distributionPercent = $totalDevices > 0
            ? round(($totalFaceDeviceLinks / $totalDevices) * 100, 1)
            : 0;

        return [
            'total_face_templates' => $totalFaceTemplates,
            'employees_with_faces' => $employeesWithFaces,
            'total_active_employees' => $totalActiveEmployees,
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'offline_devices' => $offlineDevices,
            'pending_face_commands' => $pendingFaceCommands,
            'failed_face_commands' => $failedFaceCommands,
            'completed_face_commands' => $completedFaceCommands,
            'total_face_commands' => $totalFaceCommands,
            'coverage_percent' => $coveragePercent,
            'distribution_percent' => $distributionPercent,
        ];
    }

    /**
     * Per-device face sync status: how many face templates each device has,
     * how many pending/failed commands, last sync time.
     */
    public function getDeviceFaceSyncStatus(): Collection
    {
        $devices = FingerprintDevice::query()
            ->active()
            ->with(['deviceType', 'branch'])
            ->get();

        return $devices->map(function (FingerprintDevice $device) {
            $faceCount = UserFingerprint::query()
                ->where('device_id', $device->id)
                ->where('template_type', 'face')
                ->whereNotNull('template_data')
                ->where('template_data', '!=', '')
                ->count();

            $employeeCount = UserFingerprint::query()
                ->where('device_id', $device->id)
                ->where('template_type', 'face')
                ->whereNotNull('template_data')
                ->where('template_data', '!=', '')
                ->distinct('user_id')
                ->count('user_id');

            $pendingCommands = DeviceCommand::query()
                ->where('device_id', $device->id)
                ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
                ->where('status', DeviceCommand::STATUS_PENDING)
                ->count();

            $sendingCommands = DeviceCommand::query()
                ->where('device_id', $device->id)
                ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
                ->where('status', DeviceCommand::STATUS_SENDING)
                ->count();

            $failedCommands = DeviceCommand::query()
                ->where('device_id', $device->id)
                ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
                ->where('status', DeviceCommand::STATUS_FAILED)
                ->count();

            $completedCommands = DeviceCommand::query()
                ->where('device_id', $device->id)
                ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
                ->where('status', DeviceCommand::STATUS_COMPLETED)
                ->count();

            $totalDeviceCommands = $pendingCommands + $sendingCommands + $failedCommands + $completedCommands;

            // Sync health: completed vs total
            $healthPercent = $totalDeviceCommands > 0
                ? round(($completedCommands / $totalDeviceCommands) * 100, 1)
                : ($faceCount > 0 ? 100.0 : 0.0);

            return [
                'id' => $device->id,
                'name' => $device->name,
                'serial_number' => $device->serial_number,
                'ip_address' => $device->ip_address,
                'port' => $device->port,
                'status' => $device->status,
                'branch' => $device->branch?->branch_name,
                'device_type' => $device->deviceType?->name,
                'is_push_enabled' => $device->is_push_enabled,
                'last_seen_at' => $device->last_seen_at?->toISOString(),
                'last_synced_at' => $device->last_synced_at?->toISOString(),
                'last_pushed_at' => $device->last_pushed_at?->toISOString(),
                'face_count' => $faceCount,
                'employee_count' => $employeeCount,
                'pending_commands' => $pendingCommands,
                'sending_commands' => $sendingCommands,
                'failed_commands' => $failedCommands,
                'completed_commands' => $completedCommands,
                'total_commands' => $totalDeviceCommands,
                'health_percent' => $healthPercent,
            ];
        });
    }

    /**
     * Per-employee face coverage: which employees have faces, on which devices,
     * and which devices are still missing their faces.
     */
    public function getEmployeeFaceCoverage(): Collection
    {
        // All active employees
        $employees = User::query()
            ->where('is_active_employee', true)
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->get(['id', 'employee_code', 'name', 'full_name_ar']);

        // All active devices
        $activeDeviceIds = FingerprintDevice::query()
            ->active()
            ->pluck('id')
            ->toArray();

        $activeDeviceCount = count($activeDeviceIds);

        // Face templates grouped by user
        $faceByUser = UserFingerprint::query()
            ->where('template_type', 'face')
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->whereIn('device_id', $activeDeviceIds)
            ->get()
            ->groupBy('user_id');

        return $employees->map(function (User $employee) use ($faceByUser, $activeDeviceIds, $activeDeviceCount) {
            $templates = $faceByUser->get($employee->id, collect());

            $syncedDeviceIds = $templates->pluck('device_id')->unique()->filter()->values()->toArray();
            $missingDeviceIds = array_values(array_diff($activeDeviceIds, $syncedDeviceIds));

            $deviceCount = count($syncedDeviceIds);
            $missingCount = count($missingDeviceIds);
            $isFullySynced = $activeDeviceCount > 0 && $missingCount === 0;
            $coveragePercent = $activeDeviceCount > 0
                ? round(($deviceCount / $activeDeviceCount) * 100, 1)
                : 0;

            return [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->name ?? $employee->full_name_ar ?? $employee->employee_code,
                'total_templates' => $templates->count(),
                'synced_devices' => $deviceCount,
                'missing_devices' => $missingCount,
                'missing_device_ids' => $missingDeviceIds,
                'total_active_devices' => $activeDeviceCount,
                'is_fully_synced' => $isFullySynced,
                'coverage_percent' => $coveragePercent,
                'source_devices' => $templates->pluck('device_serial')->filter()->unique()->values()->toArray(),
            ];
        });
    }

    /**
     * Aggregate command queue stats across all devices.
     */
    public function getCommandQueueStats(): array
    {
        $stats = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        // Per-device breakdown
        $perDevice = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->join('fingerprint_devices', 'device_commands.device_id', '=', 'fingerprint_devices.id')
            ->select(
                'device_commands.device_id',
                'fingerprint_devices.name as device_name',
                'device_commands.status',
                DB::raw('COUNT(*) as count'),
            )
            ->groupBy('device_commands.device_id', 'fingerprint_devices.name', 'device_commands.status')
            ->get()
            ->groupBy('device_name');

        $deviceBreakdown = [];
        foreach ($perDevice as $deviceName => $rows) {
            $deviceBreakdown[$deviceName] = [
                'pending' => 0,
                'sending' => 0,
                'completed' => 0,
                'failed' => 0,
            ];
            foreach ($rows as $row) {
                $deviceBreakdown[$deviceName][$row->status] = (int) $row->count;
            }
        }

        return [
            'pending' => $stats['pending'] ?? 0,
            'sending' => $stats['sending'] ?? 0,
            'completed' => $stats['completed'] ?? 0,
            'failed' => $stats['failed'] ?? 0,
            'cancelled' => $stats['cancelled'] ?? 0,
            'expired' => $stats['expired'] ?? 0,
            'per_device' => $deviceBreakdown,
        ];
    }

    /**
     * Recent face sync logs.
     */
    public function getRecentSyncLogs(): array
    {
        return DB::table('device_sync_logs')
            ->join('fingerprint_devices', 'device_sync_logs.device_id', '=', 'fingerprint_devices.id')
            ->leftJoin('users', 'device_sync_logs.user_id', '=', 'users.id')
            ->select(
                'device_sync_logs.id',
                'device_sync_logs.device_id',
                'fingerprint_devices.name as device_name',
                'device_sync_logs.direction',
                'device_sync_logs.status',
                'device_sync_logs.totals',
                'device_sync_logs.errors',
                'device_sync_logs.started_at',
                'device_sync_logs.finished_at',
                'device_sync_logs.duration_seconds',
                'users.name as operator_name',
            )
            ->orderByDesc('device_sync_logs.started_at')
            ->limit(20)
            ->get()
            ->map(fn ($row) => array_merge((array) $row, [
                'duration_seconds' => $row->duration_seconds !== null ? (float) $row->duration_seconds : null,
            ]))
            ->toArray();
    }

    /**
     * Recently failed face commands for troubleshooting.
     */
    public function getFailedCommands(): array
    {
        return DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_FACE_TEMPLATE)
            ->where('status', DeviceCommand::STATUS_FAILED)
            ->with('device:id,name,serial_number,ip_address')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (DeviceCommand $cmd) => [
                'id' => $cmd->id,
                'device_id' => $cmd->device_id,
                'device_name' => $cmd->device?->name,
                'device_serial' => $cmd->device?->serial_number,
                'device_ip' => $cmd->device?->ip_address,
                'error_message' => $cmd->error_message,
                'retry_count' => $cmd->retry_count,
                'max_retries' => $cmd->max_retries,
                'created_at' => $cmd->created_at?->toISOString(),
                'updated_at' => $cmd->updated_at?->toISOString(),
                'command_body_preview' => substr($cmd->command_body, 0, 120),
            ])
            ->toArray();
    }
}
