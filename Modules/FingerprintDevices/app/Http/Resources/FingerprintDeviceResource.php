<?php

namespace Modules\FingerprintDevices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FingerprintDevices\Models\FingerprintDevice;

/**
 * @mixin FingerprintDevice
 */
class FingerprintDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_type_id' => $this->device_type_id,
            'branch_id' => $this->branch_id,
            'default_company_id' => $this->default_company_id,
            'default_branch_id' => $this->default_branch_id,
            'default_subordination_id' => $this->default_subordination_id,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'comm_key' => $this->comm_key,
            'timezone' => $this->timezone,
            'connection_type' => $this->connection_type,
            'timeout' => $this->timeout,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at?->toDateTimeString(),
            'last_seen_at_human' => $this->last_seen_at?->diffForHumans(),
            'last_synced_at' => $this->last_synced_at?->toDateTimeString(),
            'last_synced_at_human' => $this->last_synced_at?->diffForHumans(),

            // ===== Bidirectional sync additions =====
            'last_pushed_at' => $this->last_pushed_at?->toDateTimeString(),
            'last_pushed_at_human' => $this->last_pushed_at?->diffForHumans(),
            'sync_log_count' => (int) ($this->sync_log_count ?? 0),
            'can_push_users' => (bool) $this->can_push_users,
            'can_push_fingerprints' => (bool) $this->can_push_fingerprints,
            'can_push_face_photos' => (bool) $this->can_push_face_photos,
            'push_capabilities' => [
                'users' => (bool) $this->can_push_users,
                'fingerprints' => (bool) $this->can_push_fingerprints,
                'face_photos' => (bool) $this->can_push_face_photos,
            ],
            'last_sync_log' => $this->whenLoaded('syncLogs', function () {
                $log = $this->syncLogs->sortByDesc('started_at')->first();

                return $log ? [
                    'id' => $log->id,
                    'direction' => $log->direction,
                    'status' => $log->status,
                    'started_at' => $log->started_at?->toDateTimeString(),
                    'duration_seconds' => $log->duration_seconds,
                    'duration_human' => $log->duration_human,
                    'totals' => $log->totals,
                ] : null;
            }),

            'capabilities' => $this->capabilities,
            'user_count' => $this->user_count,
            'fingerprint_count' => $this->fingerprint_count,
            'attendance_log_count' => $this->attendance_log_count,
            'notes' => $this->notes,
            'is_push_enabled' => (bool) $this->is_push_enabled,
            'push_url' => $this->push_url,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'device_type' => $this->whenLoaded('deviceType', fn () => new FingerprintDeviceTypeResource($this->deviceType)),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
                'branch_code' => $this->branch->branch_code,
            ] : null),
            'default_company' => $this->whenLoaded('defaultCompany', fn () => $this->defaultCompany ? [
                'id' => $this->defaultCompany->id,
                'company_name' => $this->defaultCompany->company_name,
            ] : null),
            'default_branch' => $this->whenLoaded('defaultBranch', fn () => $this->defaultBranch ? [
                'id' => $this->defaultBranch->id,
                'branch_name' => $this->defaultBranch->branch_name,
            ] : null),
            'default_subordination' => $this->whenLoaded('defaultSubordination', fn () => $this->defaultSubordination ? [
                'id' => $this->defaultSubordination->id,
                'name_ar' => $this->defaultSubordination->name_ar,
                'code' => $this->defaultSubordination->code,
            ] : null),
        ];
    }
}
