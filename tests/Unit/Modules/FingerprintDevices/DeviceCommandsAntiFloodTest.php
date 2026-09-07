<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\FingerprintDevices\Jobs\VerifyFaceTemplateOnDevice;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Anti-flood regression suite.
 *
 * Guarantees that no future change can re-introduce sudden bulk syncs of
 * identical old biometric data into device_commands:
 *  - identical payloads never create a second row (pending/sending/completed/failed)
 *  - genuinely new data (new hash / pin / index / renamed employee) still queues
 *  - retries and post-delivery verification are bounded and never delete data
 *  - bulk distributors stay manual-only (no schedule), retry stays hourly+bounded
 *  - new-employee event sync (observer) is untouched and still works
 */
class DeviceCommandsAntiFloodTest extends TestCase
{
    use RefreshDatabase;

    // ── Face template idempotency ──────────────────────────────────────

    public function test_same_face_bytes_never_duplicate_in_any_status(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);
        $hash = hash('sha256', 'same-face');

        foreach ([null, 'sending', 'completed', 'failed'] as $terminal) {
            DeviceCommand::query()->delete();

            $first = $service->queueFaceTemplate($device->id, 'EMP-1', 'same-face', ['index' => 3, 'valid' => 1], $hash);
            $this->assertTrue($first->wasRecentlyCreated);

            if ($terminal === 'sending') {
                $first->markSending();
            } elseif ($terminal === 'completed') {
                $first->markSending();
                $first->markCompleted();
            } elseif ($terminal === 'failed') {
                $first->markSending();
                $first->update(['status' => DeviceCommand::STATUS_FAILED, 'error_message' => 'Device returned -3']);
            }

            $second = $service->queueFaceTemplate($device->id, 'EMP-1', 'same-face', ['index' => 3, 'valid' => 1], $hash);

            $this->assertSame($first->id, $second->id, "duplicated in status [{$terminal}]");
            $this->assertSame(1, DeviceCommand::query()->count(), "row count grew in status [{$terminal}]");
        }
    }

    public function test_new_face_hash_pin_or_index_still_queues(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);

        $base = $service->queueFaceTemplate($device->id, 'EMP-1', 'face-A', ['index' => 0, 'valid' => 1], hash('sha256', 'face-A'));
        $base->markSending();
        $base->markCompleted();

        $newHash = $service->queueFaceTemplate($device->id, 'EMP-1', 'face-B', ['index' => 0, 'valid' => 1], hash('sha256', 'face-B'));
        $newPin = $service->queueFaceTemplate($device->id, 'EMP-2', 'face-A', ['index' => 0, 'valid' => 1], hash('sha256', 'face-A'));
        $newIndex = $service->queueFaceTemplate($device->id, 'EMP-1', 'face-A', ['index' => 1, 'valid' => 1], hash('sha256', 'face-A'));

        $this->assertTrue($newHash->wasRecentlyCreated);
        $this->assertTrue($newPin->wasRecentlyCreated);
        $this->assertTrue($newIndex->wasRecentlyCreated);
        $this->assertSame(4, DeviceCommand::query()->count());
    }

    public function test_same_fingerprint_bytes_never_duplicate(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);
        $hash = hash('sha256', 'same-fp');

        $first = $service->queueFingerprintTemplate($device->id, 'EMP-9', 'same-fp', ['index' => 2], $hash);
        $first->markSending();
        $first->markCompleted();

        $second = $service->queueFingerprintTemplate($device->id, 'EMP-9', 'same-fp', ['index' => 2], $hash);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DeviceCommand::query()->count());
    }

    public function test_flood_100_identical_calls_produce_single_row(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);
        $hash = hash('sha256', 'flood-face');

        for ($i = 0; $i < 100; $i++) {
            $service->queueFaceTemplate($device->id, 'EMP-F', 'flood-face', ['index' => 0, 'valid' => 1], $hash);
        }

        $this->assertSame(1, DeviceCommand::query()->count());
    }

    // ── User idempotency ───────────────────────────────────────────────

    public function test_identical_user_payload_never_duplicates_but_rename_queues(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);

        $first = $service->queueUserCreate($device->id, 'U-1', 'Same Name');
        $first->markSending();
        $first->markCompleted();

        $repeat = $service->queueUserCreate($device->id, 'U-1', 'Same Name');
        $this->assertSame($first->id, $repeat->id);

        $renamed = $service->queueUserCreate($device->id, 'U-1', 'Changed Name');
        $this->assertNotSame($first->id, $renamed->id);
        $this->assertTrue($renamed->wasRecentlyCreated);
    }

    // ── Bulk distributor loops ─────────────────────────────────────────

    public function test_repeated_bulk_distribution_never_grows_queue(): void
    {
        // NOTE: user first (no devices yet → observer queues nothing), then devices.
        $user = $this->makeUser('EMP-BULK');
        [$source, $target] = $this->makeDevices(2);
        $this->makeTemplate($user, $source, 'bulk-face-data', null, 2);

        $distribution = app(FaceTemplateDistributionService::class);

        for ($i = 0; $i < 10; $i++) {
            $distribution->queueForDevice($target, [$user->id]);
            $distribution->queueSetForDevice($target, $user->id, $source->serial_number, 'any-set');
        }

        $this->assertSame(1, DeviceCommand::query()->count());
    }

    // ── Bounded retry ──────────────────────────────────────────────────

    public function test_retry_preserves_budget_backoff_and_data(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);

        $ok = $service->queueFaceTemplate($device->id, 'EMP-R1', 'retry-me', ['index' => 0, 'valid' => 1], hash('sha256', 'retry-me'));
        $ok->markSending();
        $ok->update(['status' => DeviceCommand::STATUS_FAILED, 'retry_count' => 4, 'error_message' => 'Device returned -3']);

        $done = $service->queueFaceTemplate($device->id, 'EMP-R2', 'already-done', ['index' => 0, 'valid' => 1], hash('sha256', 'already-done'));
        $done->markSending();
        $done->markCompleted();

        $exhausted = $service->queueFaceTemplate($device->id, 'EMP-R3', 'give-up', ['index' => 0, 'valid' => 1], hash('sha256', 'give-up'));
        $exhausted->markSending();
        $exhausted->update(['status' => DeviceCommand::STATUS_FAILED, 'retry_count' => 30, 'max_retries' => 30, 'error_message' => 'Device returned -3']);

        $before = DeviceCommand::query()->count();

        $result = $service->retryFailedFaceCommands(limit: 50, hours: 72);

        $this->assertSame(1, $result['requeued']);
        $this->assertSame(2, $result['total_failed']);
        $this->assertSame($before, DeviceCommand::query()->count(), 'retry must never delete rows');

        $this->assertSame(DeviceCommand::STATUS_PENDING, $ok->fresh()->status);
        $this->assertSame(4, $ok->fresh()->retry_count, 'retry budget must be preserved');
        $this->assertSame(30, $ok->fresh()->max_retries);
        $this->assertNotNull($ok->fresh()->available_at, 'backoff must be scheduled');

        $this->assertSame(DeviceCommand::STATUS_FAILED, $exhausted->fresh()->status);
        $this->assertSame(DeviceCommand::STATUS_COMPLETED, $done->fresh()->status);
    }

    public function test_console_retry_command_is_bounded_by_default(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);

        $cmd = $service->queueFaceTemplate($device->id, 'EMP-C1', 'console-retry', ['index' => 0, 'valid' => 1], hash('sha256', 'console-retry'));
        $cmd->markSending();
        $cmd->update(['status' => DeviceCommand::STATUS_FAILED, 'retry_count' => 2, 'error_message' => 'Device returned -3']);

        $this->artisan('fingerprints:retry-failed-faces')->assertSuccessful();

        $this->assertSame(DeviceCommand::STATUS_PENDING, $cmd->fresh()->status);
        $this->assertSame(2, $cmd->fresh()->retry_count);
    }

    // ── Bounded post-delivery verification ─────────────────────────────

    public function test_verify_job_requeues_dropped_template_once_with_budget(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);
        $pin = 'EMP-V1';

        // Device silently dropped the template → single bounded requeue.
        $dropped = $service->queueFaceTemplate($device->id, $pin, 'verify-face', ['index' => 0, 'valid' => 1], hash('sha256', 'verify-face'));
        $dropped->markSending();
        $dropped->markCompleted();
        $dropped->update(['retry_count' => 0]);

        Http::fake(function ($request) use ($pin) {
            $url = (string) $request->url();
            if (str_contains($url, 'get-users')) {
                return Http::response(['users' => [['user_id' => $pin, 'uid' => 7]]], 200);
            }

            return Http::response(['templates' => [['fid' => 1], ['fid' => 2]]], 200);
        });

        (new VerifyFaceTemplateOnDevice($device->id, $pin, $dropped->id))->handle();

        $this->assertSame(DeviceCommand::STATUS_PENDING, $dropped->fresh()->status);
        $this->assertSame(1, $dropped->fresh()->retry_count);
    }

    public function test_verify_job_never_resurrects_exhausted_budget(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);
        $pin = 'EMP-V2';

        $exhausted = $service->queueFaceTemplate($device->id, $pin, 'verify-done', ['index' => 1, 'valid' => 1], hash('sha256', 'verify-done'));
        $exhausted->markSending();
        $exhausted->markCompleted();
        $exhausted->update(['retry_count' => 30, 'max_retries' => 30]);

        Http::fake(function ($request) use ($pin) {
            $url = (string) $request->url();
            if (str_contains($url, 'get-users')) {
                return Http::response(['users' => [['user_id' => $pin, 'uid' => 7]]], 200);
            }

            return Http::response(['templates' => [['fid' => 1], ['fid' => 2]]], 200);
        });

        (new VerifyFaceTemplateOnDevice($device->id, $pin, $exhausted->id))->handle();

        $this->assertSame(DeviceCommand::STATUS_COMPLETED, $exhausted->fresh()->status);
    }

    public function test_verify_job_keeps_completed_when_bridge_unreachable(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);

        Http::fake(fn () => Http::response('error', 500));
        $offline = $service->queueFaceTemplate($device->id, 'EMP-V3', 'verify-offline', ['index' => 2, 'valid' => 1], hash('sha256', 'verify-offline'));
        $offline->markSending();
        $offline->markCompleted();

        (new VerifyFaceTemplateOnDevice($device->id, 'EMP-V3', $offline->id))->handle();

        $this->assertSame(DeviceCommand::STATUS_COMPLETED, $offline->fresh()->status);
    }

    public function test_verify_job_keeps_completed_when_face_present(): void
    {
        [$device] = $this->makeDevices(1);
        $service = app(DeviceCommandService::class);
        $pin = 'EMP-V4';

        $cmd = $service->queueFaceTemplate($device->id, $pin, 'verify-ok', ['index' => 0, 'valid' => 1], hash('sha256', 'verify-ok'));
        $cmd->markSending();
        $cmd->markCompleted();

        Http::fake(function ($request) use ($pin) {
            $url = (string) $request->url();
            if (str_contains($url, 'get-users')) {
                return Http::response(['users' => [['user_id' => $pin, 'uid' => 9]]], 200);
            }

            return Http::response(['templates' => [['fid' => 50], ['fid' => 51]]], 200);
        });

        (new VerifyFaceTemplateOnDevice($device->id, $pin, $cmd->id))->handle();

        $this->assertSame(DeviceCommand::STATUS_COMPLETED, $cmd->fresh()->status);
    }

    // ── Schedule safety net ────────────────────────────────────────────

    public function test_bulk_distributors_are_manual_only_and_retry_is_bounded(): void
    {
        $provider = file_get_contents(module_path('FingerprintDevices', 'app/Providers/FingerprintDevicesServiceProvider.php'));
        $this->assertIsString($provider);

        $this->assertStringNotContainsString(
            "Schedule::command('fingerprints:distribute-missing-faces')",
            $provider,
            'distribute-missing must stay manual-only or it will re-flood old data'
        );
        $this->assertStringNotContainsString(
            "Schedule::command('fingerprints:distribute-all-faces')",
            $provider,
            'distribute-all must stay manual-only or it will re-flood old data'
        );
        $this->assertStringContainsString('fingerprints:retry-failed-faces --limit=50 --hours=72', $provider);
        $this->assertStringContainsString('->hourly()', $provider);

        $service = new \ReflectionMethod(DeviceCommandService::class, 'retryFailedFaceCommands');
        $params = [];
        foreach ($service->getParameters() as $p) {
            $params[$p->getName()] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
        }
        $this->assertSame(50, $params['limit']);
        $this->assertSame(72, $params['hours']);
    }

    // ── New-employee sync untouched ────────────────────────────────────

    public function test_new_employee_still_syncs_to_all_devices_immediately(): void
    {
        $devices = $this->makeDevices(2);

        $user = User::create([
            'employee_code' => 'EMP-NEW-1',
            'name' => 'EMP-NEW-1',
            'full_name_ar' => 'موظف جديد',
            'email' => 'emp-new-1@test.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);

        $pins = DeviceCommand::query()
            ->whereIn('device_id', array_map(fn ($d) => $d->id, $devices))
            ->whereIn('command_type', [DeviceCommand::TYPE_USER_CREATE, DeviceCommand::TYPE_USER_UPDATE])
            ->where('command_body', 'like', '%PIN=EMP-NEW-1%')
            ->count();

        $this->assertSame(2, $pins, 'new employee must reach every device via observer');
        $this->assertTrue($user->exists);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** @return array<int, FingerprintDevice> */
    private function makeDevices(int $count = 2): array
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ZKTeco AntiFlood',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);

        $devices = [];
        for ($i = 0; $i < $count; $i++) {
            $serial = 'ANTIFLOOD-'.$i.'-'.uniqid();
            $devices[] = FingerprintDevice::create([
                'device_type_id' => $type->id,
                'name' => "AntiFlood Device {$i}",
                'serial_number' => $serial,
                'ip_address' => '192.168.99.'.($i + 10),
                'port' => 4370,
                'comm_key' => '0',
                'timeout' => 30,
                'status' => 'online',
                'is_push_enabled' => true,
            ]);
        }

        return $devices;
    }

    private function makeUser(string $employeeCode): User
    {
        return User::withoutEvents(function () use ($employeeCode) {
            return User::create([
                'employee_code' => $employeeCode,
                'name' => $employeeCode,
                'full_name_ar' => $employeeCode,
                'email' => strtolower($employeeCode).'@test.local',
                'password' => bcrypt('password'),
                'status' => 1,
                'is_active_employee' => true,
            ]);
        });
    }

    private function makeTemplate(User $user, FingerprintDevice $source, string $data, ?string $setId = null, int $index = 2): UserFingerprint
    {
        return UserFingerprint::create([
            'user_id' => $user->id,
            'device_id' => $source->id,
            'device_serial' => $source->serial_number,
            'finger_id' => 50 + $index,
            'template_data' => $data,
            'template_hash' => hash('sha256', $data),
            'template_format' => 'zkteco-face-push',
            'template_type' => 'face',
            'template_index' => $index,
            'face_template_set_id' => $setId,
            'template_version' => 120,
            'template_metadata' => [
                'No' => 1,
                'Index' => $index,
                'Valid' => 1,
                'Duress' => 0,
                'MajorVer' => 12,
                'MinorVer' => 0,
                'Format' => 0,
            ],
            'is_master' => true,
        ]);
    }
}
