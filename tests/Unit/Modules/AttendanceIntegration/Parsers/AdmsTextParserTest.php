<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Parsers;

use Modules\AttendanceIntegration\Parsers\AdmsTextParser;
use PHPUnit\Framework\TestCase;

class AdmsTextParserTest extends TestCase
{
    public function test_parse_single_adms_line(): void
    {
        $body = "ATT\t\tEMP001\t2026-01-15 08:00:00\t0\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(1, $rows);
        $this->assertSame('EMP001', $rows[0]['user_id']);
        $this->assertSame('2026-01-15 08:00:00', $rows[0]['timestamp']);
        $this->assertSame(0, $rows[0]['status']);
    }

    public function test_parse_adms_with_numeric_user_id(): void
    {
        $body = "ATT\t\t1001\t2026-01-15 08:00:00\t0\nATT\t\t1001\t2026-01-15 17:00:00\t1\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(2, $rows);
        $this->assertSame('1001', $rows[0]['user_id']);
        $this->assertSame('2026-01-15 08:00:00', $rows[0]['timestamp']);
        $this->assertSame(0, $rows[0]['status']);
        $this->assertSame('1001', $rows[1]['user_id']);
        $this->assertSame(1, $rows[1]['status']);
    }

    public function test_parse_multiple_adms_lines(): void
    {
        $body = "ATT\t\tEMP001\t2026-01-15 08:00:00\t0\nATT\t\tEMP002\t2026-01-15 08:01:00\t0\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(2, $rows);
        $this->assertSame('EMP001', $rows[0]['user_id']);
        $this->assertSame('EMP002', $rows[1]['user_id']);
    }

    public function test_parse_skips_non_att_lines(): void
    {
        $body = "SOME HEADER\nATT\t\tEMP001\t2026-01-15 08:00:00\t0\nFOOTER\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(1, $rows);
        $this->assertSame('EMP001', $rows[0]['user_id']);
    }

    public function test_parse_skips_empty_lines(): void
    {
        $body = "\n\nATT\t\tEMP001\t2026-01-15 08:00:00\t0\n\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(1, $rows);
    }

    public function test_parse_empty_body_returns_empty(): void
    {
        $rows = AdmsTextParser::parse('');
        $this->assertEmpty($rows);
    }

    public function test_parse_body_with_no_att_lines_returns_empty(): void
    {
        $rows = AdmsTextParser::parse("NO ATT LINE HERE\nSTILL NO ATT\n");
        $this->assertEmpty($rows);
    }

    public function test_parse_preserves_status_integer(): void
    {
        $body = "ATT\t\tEMP001\t2026-01-15 08:00:00\t1\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertSame(1, $rows[0]['status']);
    }

    public function test_parse_handles_missing_columns(): void
    {
        $body = "ATT\t\tEMP001\t2026-01-15 08:00:00\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['status']);
    }

    public function test_parse_full_device_record_preserves_verify_and_work_code(): void
    {
        $body = "20035\t2026-07-29 18:11:11\t0\t1\t0\t0\t0\t0\t0\t0\t3165\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(1, $rows);
        $this->assertSame('20035', $rows[0]['user_id']);
        $this->assertSame('2026-07-29 18:11:11', $rows[0]['timestamp']);
        $this->assertSame(0, $rows[0]['status']);
        $this->assertSame(1, $rows[0]['punch']);
        $this->assertSame(3165, $rows[0]['work_code']);
    }

    public function test_parse_tagged_line_preserves_verify_column(): void
    {
        $body = "ATT\t\tEMP001\t2026-01-15 08:00:00\t0\t2\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(1, $rows);
        $this->assertSame('EMP001', $rows[0]['user_id']);
        $this->assertSame(0, $rows[0]['status']);
        $this->assertSame(2, $rows[0]['punch']);
    }

    public function test_parse_glued_att_prefix(): void
    {
        $body = "ATT20035\t2026-07-29 18:11:11\t1\t0\n";
        $rows = AdmsTextParser::parse($body);

        $this->assertCount(1, $rows);
        $this->assertSame('20035', $rows[0]['user_id']);
        $this->assertSame('2026-07-29 18:11:11', $rows[0]['timestamp']);
        $this->assertSame(1, $rows[0]['status']);
    }

    public function test_parse_skips_ownerless_lines(): void
    {
        $rows = AdmsTextParser::parse("ATT\t\t\t2026-01-15 08:00:00\t0\n");

        $this->assertEmpty($rows);
    }
}
