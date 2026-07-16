<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Drivers\ZKTeco;

use Modules\AttendanceIntegration\Drivers\ZKTeco\ZKTecoAdmsParser;
use PHPUnit\Framework\TestCase;

class ZKTecoAdmsParserTest extends TestCase
{
    private ZKTecoAdmsParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ZKTecoAdmsParser;
    }

    public function test_get_driver_name(): void
    {
        $this->assertSame('zkteco', $this->parser->getDriverName());
    }

    public function test_parse_adms_text_body(): void
    {
        $body = "ATT\t\t1001\t2026-01-15 08:00:00\t0\nATT\t\t1001\t2026-01-15 17:00:00\t1\n";

        $rows = $this->parser->parse(['Body' => $body], []);

        $this->assertCount(2, $rows);
        $this->assertSame('1001', $rows[0]['user_id']);
        $this->assertSame('2026-01-15 08:00:00', $rows[0]['timestamp']);
        $this->assertSame(0, $rows[0]['status']);
        $this->assertSame('1001', $rows[1]['user_id']);
        $this->assertSame(1, $rows[1]['status']);
    }

    public function test_parse_adms_text_ignores_non_att_lines(): void
    {
        $body = "HEADER\tdata\nATT\t\t1001\t2026-01-15 08:00:00\t0\nFOOTER\tdata\n";

        $rows = $this->parser->parse(['Body' => $body], []);

        $this->assertCount(1, $rows);
    }

    public function test_parse_array_body(): void
    {
        $body = [
            ['user_id' => '2001', 'timestamp' => '2026-01-15 08:00:00', 'status' => 0],
        ];

        $rows = $this->parser->parse(['Body' => $body], []);

        $this->assertCount(1, $rows);
        $this->assertSame('2001', $rows[0]['user_id']);
    }

    public function test_parse_attendance_key(): void
    {
        $data = [
            'attendance' => [
                ['user_id' => '3001', 'timestamp' => '2026-01-15 08:00:00'],
            ],
        ];

        $rows = $this->parser->parse($data, []);

        $this->assertCount(1, $rows);
        $this->assertSame('3001', $rows[0]['user_id']);
    }

    public function test_parse_single_row_fallback(): void
    {
        $data = ['user_id' => '4001', 'timestamp' => '2026-01-15 08:00:00', 'status' => 0];

        $rows = $this->parser->parse($data, []);

        $this->assertCount(1, $rows);
        $this->assertSame('4001', $rows[0]['user_id']);
    }

    public function test_parse_empty_body_returns_empty(): void
    {
        $rows = $this->parser->parse([], []);
        $this->assertCount(0, $rows);
    }

    public function test_parse_empty_string_body_returns_empty(): void
    {
        $rows = $this->parser->parse(['Body' => ''], []);
        $this->assertCount(0, $rows);
    }

    public function test_parse_body_string_with_no_att_lines(): void
    {
        $body = "HEADER\ndata\nFOOTER\n";

        $rows = $this->parser->parse(['Body' => $body], []);

        $this->assertCount(0, $rows);
    }
}
