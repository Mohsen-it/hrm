<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Http\Requests;

use Modules\AttendanceIntegration\Http\Requests\StoreDevicePunchRequest;
use Tests\TestCase;

class StoreDevicePunchRequestTest extends TestCase
{
    public function test_validates_required_fields_for_single_punch(): void
    {
        $request = new StoreDevicePunchRequest;

        $rules = $request->rules();

        $this->assertArrayHasKey('SN', $rules);
        $this->assertArrayHasKey('serial_number', $rules);
        $this->assertArrayHasKey('user_id', $rules);
        $this->assertArrayHasKey('timestamp', $rules);
        $this->assertArrayHasKey('punch_type', $rules);
        $this->assertArrayHasKey('status', $rules);
        $this->assertArrayHasKey('work_code', $rules);
        $this->assertArrayHasKey('Body', $rules);
        $this->assertArrayHasKey('attendance', $rules);
        $this->assertArrayHasKey('punches', $rules);
    }

    public function test_punch_type_only_accepts_valid_values(): void
    {
        $request = new StoreDevicePunchRequest;
        $rules = $request->rules();

        $allowed = $rules['punch_type'][2];
        $this->assertStringContainsString('check_in', $allowed);
        $this->assertStringContainsString('check_out', $allowed);
        $this->assertStringContainsString('auto', $allowed);
        $this->assertStringContainsString('break_in', $allowed);
        $this->assertStringContainsString('break_out', $allowed);
    }

    public function test_attendance_batch_max_limit(): void
    {
        $request = new StoreDevicePunchRequest;
        $rules = $request->rules();

        $this->assertSame('max:500', $rules['attendance'][2]);
    }

    public function test_punches_batch_max_limit(): void
    {
        $request = new StoreDevicePunchRequest;
        $rules = $request->rules();

        $this->assertSame('max:500', $rules['punches'][2]);
    }

    public function test_body_max_size(): void
    {
        $request = new StoreDevicePunchRequest;
        $rules = $request->rules();

        $this->assertSame('max:524288', $rules['Body'][2]);
    }

    public function test_work_code_range(): void
    {
        $request = new StoreDevicePunchRequest;
        $rules = $request->rules();

        $this->assertContains('min:0', $rules['work_code']);
        $this->assertContains('max:65535', $rules['work_code']);
    }
}
