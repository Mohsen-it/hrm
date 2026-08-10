<?php

namespace Tests\Unit\Modules\Attendance;

use Modules\Attendance\Exports\DailyReportDocxExport;
use Tests\TestCase;
use ZipArchive;

class DailyReportDocxExportTest extends TestCase
{
    public function test_it_builds_a_word_document_from_the_daily_report_template(): void
    {
        $export = new DailyReportDocxExport([
            'date' => '2026-08-05',
            'rows' => [
                ['status' => 'absent', 'name' => 'موظف غياب', 'department_name' => 'القسم', 'notes' => 'عدد أيام الغياب خلال الشهر: ‏٣'],
                ['status' => 'late', 'name' => 'موظف تأخر', 'department_name' => 'القسم', 'rotation' => 'دورية الأمن (أ)', 'check_in' => '09:15', 'notes' => 'عدد مرات التأخر خلال الشهر: ‏٤'],
                ['status' => 'leave', 'name' => 'موظف إجازة', 'department_name' => 'القسم', 'notes' => 'عدد أيام الإجازة خلال الشهر: ‏٥'],
                ['status' => 'present', 'name' => 'موظف بلا بصمة', 'department_name' => 'القسم', 'has_no_fingerprint' => true, 'notes' => 'الموظف غير مسجل في جهاز البصمة'],
                ['status' => 'present', 'name' => 'موظف دخول دون خروج', 'department_name' => 'القسم', 'rotation' => 'دورية النقل (ب)', 'check_in' => '08:30', 'has_incomplete_punch' => true, 'notes' => 'لم يسجل بصمة الخروج حتى نهاية نافذة الخروج 18:30'],
            ],
        ]);

        $binary = $export->toBinary();
        $file = tempnam(sys_get_temp_dir(), 'daily-report-test-');
        file_put_contents($file, $binary);

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($file) === true);
        $xml = $archive->getFromName('word/document.xml');
        $archive->close();
        @unlink($file);

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('05 / 08 /2026', $xml);
        $this->assertStringContainsString('موظف غياب', $xml);
        $this->assertStringContainsString('عدد أيام الغياب خلال الشهر: ‏٣', $xml);
        $this->assertStringContainsString('عدد مرات التأخر خلال الشهر: ‏٤', $xml);
        $this->assertStringContainsString('عدد أيام الإجازة خلال الشهر: ‏٥', $xml);
        $this->assertStringContainsString('موظف بلا بصمة', $xml);
        $this->assertStringContainsString('موظف دخول دون خروج', $xml);
        $this->assertStringContainsString('موظف تأخر', $xml);
        $this->assertStringContainsString('دورية الأمن (أ)', $xml);
        $this->assertStringContainsString('09:15', $xml);

        // The missing-checkout table now sits right after the lateness table and
        // carries the rotation plus check-in columns like the lateness table.
        $this->assertStringContainsString('تقرير عدم تسجيل بصمة الخروج حسب نافذة الخروج', $xml);
        $this->assertStringContainsString('دورية النقل (ب)', $xml);
        $this->assertStringContainsString('08:30', $xml);
        $this->assertStringContainsString('لم يسجل بصمة الخروج حتى نهاية نافذة الخروج 18:30', $xml);

        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($xml));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tables = $xpath->query('//w:tbl');
        $this->assertSame(6, $tables->length);
        $incomplete = $tables->item(2);
        $this->assertInstanceOf(\DOMElement::class, $incomplete);
        $header = $xpath->query('./w:tr[1]/w:tc', $incomplete);
        $this->assertSame(6, $header->length);
        $this->assertStringContainsString('الدورية', $header->item(3)->textContent);
        $this->assertStringContainsString('وقت الحضور', $header->item(4)->textContent);

        // The lateness and missing-checkout data rows must be vertically
        // centered (previously bottom-aligned, which pushed the text up and
        // made the rows look uneven in Word) and their cell widths must match
        // the table grid so the columns do not collapse.
        foreach ([1, 2] as $tableIndex) {
            $table = $tables->item($tableIndex);
            $this->assertInstanceOf(\DOMElement::class, $table);
            $dataRow = $xpath->query('./w:tr[2]', $table)->item(0);
            $this->assertInstanceOf(\DOMElement::class, $dataRow);
            foreach ($xpath->query('./w:tc', $dataRow) as $cell) {
                $align = $xpath->query('./w:tcPr/w:vAlign', $cell)->item(0);
                $this->assertInstanceOf(\DOMElement::class, $align);
                $this->assertSame('center', $align->getAttribute('w:val'));
            }
            $grid = $xpath->query('./w:tblGrid/w:gridCol', $table);
            $this->assertSame(6, $grid->length);
            foreach ($xpath->query('./w:tr[2]/w:tc', $table) as $index => $cell) {
                $cellWidth = $xpath->query('./w:tcPr/w:tcW', $cell)->item(0);
                $this->assertInstanceOf(\DOMElement::class, $cellWidth);
                $this->assertSame($grid->item($index)->getAttribute('w:w'), $cellWidth->getAttribute('w:w'));
            }
        }
    }
}
