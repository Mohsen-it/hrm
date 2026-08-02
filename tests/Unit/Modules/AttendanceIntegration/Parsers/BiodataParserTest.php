<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Parsers;

use Modules\AttendanceIntegration\Parsers\BiodataParser;
use PHPUnit\Framework\TestCase;

class BiodataParserTest extends TestCase
{
    public function test_parse_single_face_biodata(): void
    {
        $body = "BIODATA\nPin=20079\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=AAAA1111BBBB\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('20079', $records[0]['pin']);
        $this->assertSame(2, $records[0]['type']);
        $this->assertSame(12, $records[0]['major_ver']);
        $this->assertSame(0, $records[0]['minor_ver']);
        $this->assertSame(0, $records[0]['format']);
        $this->assertSame('AAAA1111BBBB', $records[0]['tmp']);
    }

    public function test_parse_multiple_biodata_records(): void
    {
        $body = "BIODATA\nPin=100\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=FACE1DATA\n\nBIODATA\nPin=200\nType=2\nMajorVer=12\nMinorVer=1\nFormat=0\nTmp=FACE2DATA\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(2, $records);
        $this->assertSame('100', $records[0]['pin']);
        $this->assertSame('200', $records[1]['pin']);
        $this->assertSame('FACE1DATA', $records[0]['tmp']);
        $this->assertSame('FACE2DATA', $records[1]['tmp']);
    }

    public function test_parse_fingerprint_biodata_type_0(): void
    {
        $body = "BIODATA\nPin=300\nType=0\nMajorVer=9\nMinorVer=0\nFormat=0\nTmp=FPDATA\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame(0, $records[0]['type']);
        $this->assertSame('FPDATA', $records[0]['tmp']);
    }

    public function test_parse_biodata_with_crlf_line_endings(): void
    {
        $body = "BIODATA\r\nPin=500\r\nType=2\r\nMajorVer=10\r\nMinorVer=5\r\nFormat=0\r\nTmp=CRLFTEMPLATE\r\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('500', $records[0]['pin']);
        $this->assertSame('CRLFTEMPLATE', $records[0]['tmp']);
    }

    public function test_parse_skips_non_biodata_lines_before_record(): void
    {
        $body = "SOME HEADER\nANOTHER LINE\nBIODATA\nPin=700\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DATA\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('700', $records[0]['pin']);
    }

    public function test_parse_empty_body_returns_empty(): void
    {
        $records = BiodataParser::parse('');
        $this->assertEmpty($records);
    }

    public function test_parse_no_biodata_returns_empty(): void
    {
        $records = BiodataParser::parse("ATT\t\tEMP001\t2026-01-15 08:00:00\t0\n");
        $this->assertEmpty($records);
    }

    public function test_parse_skips_record_without_pin(): void
    {
        $body = "BIODATA\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DATA\n";
        $records = BiodataParser::parse($body);

        $this->assertEmpty($records);
    }

    public function test_parse_mixed_att_and_biodata(): void
    {
        $body = "ATT\t\tEMP001\t2026-01-15 08:00:00\t0\nBIODATA\nPin=1000\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=FACE\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('1000', $records[0]['pin']);
    }

    public function test_parse_large_tmp_data(): void
    {
        $tmpData = str_repeat('A', 10000);
        $body = "BIODATA\nPin=999\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp={$tmpData}\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame($tmpData, $records[0]['tmp']);
        $this->assertSame(10000, strlen($records[0]['tmp']));
    }

    public function test_is_biodata_returns_true_for_biodata_content(): void
    {
        $body = "BIODATA\nPin=20079\nType=2\nTmp=AAAA\n";
        $this->assertTrue(BiodataParser::isBiodata($body));
    }

    public function test_is_biodata_returns_true_for_pin_and_tmp(): void
    {
        $body = "Pin=20079\nTmp=AAAA\n";
        $this->assertTrue(BiodataParser::isBiodata($body));
    }

    public function test_is_biodata_returns_false_for_att(): void
    {
        $body = "ATT\t\tEMP001\t2026-01-15 08:00:00\t0";
        $this->assertFalse(BiodataParser::isBiodata($body));
    }

    public function test_is_biodata_returns_false_for_empty(): void
    {
        $this->assertFalse(BiodataParser::isBiodata(''));
    }

    public function test_type_label_face(): void
    {
        $this->assertSame('face', BiodataParser::typeLabel(2));
    }

    public function test_type_label_fingerprint(): void
    {
        $this->assertSame('fingerprint', BiodataParser::typeLabel(0));
    }

    public function test_type_label_unknown(): void
    {
        $this->assertSame('unknown_type_99', BiodataParser::typeLabel(99));
    }

    public function test_parse_preserves_raw_content(): void
    {
        $body = "BIODATA\nPin=100\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DATA\n";
        $records = BiodataParser::parse($body);

        $this->assertStringContainsString('Pin=100', $records[0]['raw']);
        $this->assertStringContainsString('Type=2', $records[0]['raw']);
        $this->assertStringContainsString('Tmp=DATA', $records[0]['raw']);
    }

    public function test_parse_three_biodata_records(): void
    {
        $body = "BIODATA\nPin=1\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=A\n\nBIODATA\nPin=2\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=B\n\nBIODATA\nPin=3\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=C\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(3, $records);
        $this->assertSame('1', $records[0]['pin']);
        $this->assertSame('2', $records[1]['pin']);
        $this->assertSame('3', $records[2]['pin']);
    }

    public function test_parse_finalizes_last_record_without_trailing_newline(): void
    {
        $body = "BIODATA\nPin=100\nType=2\nMajorVer=12\nMinorVer=0\nFormat=0\nTmp=DATA";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('100', $records[0]['pin']);
    }

    public function test_parse_case_insensitive_keys(): void
    {
        $body = "BIODATA\npin=100\ntype=2\nmajorver=12\nminorver=0\nformat=0\ntmp=DATA\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('100', $records[0]['pin']);
        $this->assertSame(2, $records[0]['type']);
    }

    public function test_parse_inline_format_single_record(): void
    {
        $body = "BIODATA Pin=20079       No=0    Index=0 Valid=1 Duress=0        Type=2  MajorVer=12     MinorVer=0      Format=0\n        Tmp=AAAA1111BBBB\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('20079', $records[0]['pin']);
        $this->assertSame(2, $records[0]['type']);
        $this->assertSame(12, $records[0]['major_ver']);
        $this->assertSame(0, $records[0]['minor_ver']);
        $this->assertSame(0, $records[0]['format']);
        $this->assertSame('AAAA1111BBBB', $records[0]['tmp']);
    }

    public function test_parse_inline_format_multiple_records(): void
    {
        $body = "BIODATA Pin=20079 No=0 Index=0 Valid=1 Duress=0 Type=2 MajorVer=12 MinorVer=0 Format=0\n        Tmp=FACE1DATA\nBIODATA Pin=20080 No=0 Index=0 Valid=1 Duress=0 Type=2 MajorVer=12 MinorVer=0 Format=0\n        Tmp=FACE2DATA\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(2, $records);
        $this->assertSame('20079', $records[0]['pin']);
        $this->assertSame('20080', $records[1]['pin']);
        $this->assertSame('FACE1DATA', $records[0]['tmp']);
        $this->assertSame('FACE2DATA', $records[1]['tmp']);
    }

    public function test_parse_inline_format_with_long_base64_tmp(): void
    {
        $tmpData = str_repeat('A', 40000);
        $body = "BIODATA Pin=20079 No=0 Index=0 Valid=1 Duress=0 Type=2 MajorVer=12 MinorVer=0 Format=0\n        Tmp={$tmpData}\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('20079', $records[0]['pin']);
        $this->assertSame($tmpData, $records[0]['tmp']);
        $this->assertSame(40000, strlen($records[0]['tmp']));
    }

    public function test_parse_inline_format_ignores_unknown_keys(): void
    {
        $body = "BIODATA Pin=500 No=1 Index=2 Valid=1 Duress=0 Type=2 MajorVer=12 MinorVer=0 Format=0\n        Tmp=DATA\n";
        $records = BiodataParser::parse($body);

        $this->assertCount(1, $records);
        $this->assertSame('500', $records[0]['pin']);
        $this->assertSame(2, $records[0]['type']);
    }

    public function test_parse_preserves_unknown_device_fields(): void
    {
        $body = "BIODATA Pin=500 No=1 Index=2 Valid=1 Duress=0 Type=2 MajorVer=12 MinorVer=0 Format=0\nTmp=DATA\n";

        $records = BiodataParser::parse($body);

        $this->assertSame('1', $records[0]['extra_fields']['No']);
        $this->assertSame('2', $records[0]['extra_fields']['Index']);
        $this->assertSame('1', $records[0]['extra_fields']['Valid']);
        $this->assertSame('0', $records[0]['extra_fields']['Duress']);
    }
}
