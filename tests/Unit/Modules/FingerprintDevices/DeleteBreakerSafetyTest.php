<?php

namespace Tests\Unit\Modules\FingerprintDevices;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Delete-breaker + dangerous-command refusal suite.
 *
 * Guarantees:
 *  - restart / clear_users / clear_logs can never be queued by accident
 *  - a single employee deletion still propagates to devices untouched
 *  - a burst of deletions trips the breaker: excess deletes are HELD
 *    (logged + replayable), never silently dropped, never sent
 */
class DeleteBreakerSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_dangerous_command_types_are_refused(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceCommandService::class);

        foreach (['restart', 'clear_users', 'clear_logs'] as $type) {
            try {
                $service->queueCommand($device->id, $type, 'C:60');
                $this->fail("dangerous type [{$type}] was queued");
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, DeviceCommand::query()->count());
    }

    public function test_queue_restart_helper_is_refused(): void
    {
        $device = $this->makeDevice();

        $this->expectException(\RuntimeException::class);
        app(DeviceCommandService::class)->queueRestart($device->id);
    }

    public function test_dangerous_types_allowed_only_with_explicit_flag(): void
    {
        config(['fingerprintdevices.allow_dangerous_commands' => true]);
        $device = $this->makeDevice();

        $cmd = app(DeviceCommandService::class)->queueRestart($device->id);

        $this->assertTrue($cmd->wasRecentlyCreated);
    }

    public function test_safe_types_unaffected_by_refusal(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceCommandService::class);

        $service->queueUserCreate($device->id, '700', 'Safe User');
        $service->queueUserDelete($device->id, '701');

        $this->assertSame(2, DeviceCommand::query()->count());
    }

    public function test_single_delete_still_propagates_to_devices(): void
    {
        $this->makeDevice();
        $user = $this->makeUser('DEL-1');
        $user->delete();

        $cmd = DeviceCommand::query()
            ->where('command_type', DeviceCommand::TYPE_USER_DELETE)
            ->where('command_body', 'like', '%PIN=DEL-1%')
            ->first();

        $this->assertNotNull($cmd);
    }

    public function test_burst_of_deletes_trips_breaker_and_holds_excess(): void
    {
        $this->makeDevice();

        $pins = [];
        for ($i = 1; $i <= 6; $i++) {
            $pins[] = 'BURST-'.$i;
            $this->makeUser('BURST-'.$i);
        }
        foreach ($pins as $pin) {
            User::where('employee_code', $pin)->first()->delete();
        }

        foreach (array_slice($pins, 0, 5) as $pin) {
            $this->assertNotNull(
                DeviceCommand::query()
                    ->where('command_type', DeviceCommand::TYPE_USER_DELETE)
                    ->where('command_body', 'like', "%PIN={$pin}%")
                    ->first(),
                "pin {$pin} should have propagated"
            );
        }

        $this->assertNull(
            DeviceCommand::query()
                ->where('command_type', DeviceCommand::TYPE_USER_DELETE)
                ->where('command_body', 'like', '%PIN=BURST-6%')
                ->first(),
            '6th rapid delete must be HELD, not sent'
        );

        $this->assertArrayHasKey('BURST-6', Cache::get('adms:skipped_user_deletes', []));
    }

    public function test_held_delete_is_replayable_by_operator(): void
    {
        $this->makeDevice();

        for ($i = 1; $i <= 6; $i++) {
            $this->makeUser('REPLAY-'.$i);
        }
        foreach (range(1, 6) as $i) {
            User::where('employee_code', 'REPLAY-'.$i)->first()->delete();
        }

        // Dry run replays nothing.
        $this->artisan('fingerprints:process-skipped-deletes', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull(
            DeviceCommand::query()
                ->where('command_type', DeviceCommand::TYPE_USER_DELETE)
                ->where('command_body', 'like', '%PIN=REPLAY-6%')
                ->first()
        );

        // Real replay queues the held delete and clears the held list.
        $this->artisan('fingerprints:process-skipped-deletes')->assertSuccessful();
        $this->assertNotNull(
            DeviceCommand::query()
                ->where('command_type', DeviceCommand::TYPE_USER_DELETE)
                ->where('command_body', 'like', '%PIN=REPLAY-6%')
                ->first()
        );
        $this->assertSame([], Cache::get('adms:skipped_user_deletes', []));
    }

    public function test_breaker_resets_after_window(): void
    {
        $this->makeDevice();

        for ($i = 1; $i <= 5; $i++) {
            $this->makeUser('WIN-'.$i);
        }
        foreach (range(1, 5) as $i) {
            User::where('employee_code', 'WIN-'.$i)->first()->delete();
        }

        Carbon::setTestNow(now()->addMinutes(11));

        $this->makeUser('WIN-6');
        User::where('employee_code', 'WIN-6')->first()->delete();

        $this->assertNotNull(
            DeviceCommand::query()
                ->where('command_type', DeviceCommand::TYPE_USER_DELETE)
                ->where('command_body', 'like', '%PIN=WIN-6%')
                ->first(),
            'delete after window expiry must propagate'
        );
    }

    private function makeDevice(): FingerprintDevice
    {
        $type = FingerprintDeviceType::create([
            'name' => 'ZKTeco Guard',
            'manufacturer' => 'ZKTeco',
            'default_port' => 4370,
            'protocol' => 'zkteco',
        ]);

        return FingerprintDevice::create([
            'device_type_id' => $type->id,
            'name' => 'Guard Device',
            'serial_number' => 'GUARD-001-'.uniqid(),
            'ip_address' => '192.168.40.10',
            'port' => 4370,
            'comm_key' => '0',
            'timeout' => 30,
            'status' => 'online',
            'is_push_enabled' => true,
        ]);
    }

    private function makeUser(string $employeeCode): User
    {
        return User::create([
            'employee_code' => $employeeCode,
            'name' => $employeeCode,
            'full_name_ar' => $employeeCode,
            'email' => strtolower($employeeCode).'@test.local',
            'password' => bcrypt('password'),
            'status' => 1,
            'is_active_employee' => true,
        ]);
    }
}
