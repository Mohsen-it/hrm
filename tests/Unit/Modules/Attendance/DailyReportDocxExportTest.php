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
                ['status' => 'absent', 'name' => 'موظف غياب', 'department_name' => 'القسم', 'notes' => 'عدد أيام الغياب خلال الشهر: 3'],
                ['status' => 'late', 'name' => 'موظف تأخر', 'department_name' => 'القسم', 'check_in' => '09:15', 'notes' => 'عدد مرات التأخر خلال الشهر: 4'],
                ['status' => 'leave', 'name' => 'موظف إجازة', 'department_name' => 'القسم', 'notes' => 'عدد أيام الإجازة خلال الشهر: 5'],
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
        $this->assertStringContainsString('عدد أيام الغياب خلال الشهر: 3', $xml);
        $this->assertStringContainsString('عدد مرات التأخر خلال الشهر: 4', $xml);
        $this->assertStringContainsString('عدد أيام الإجازة خلال الشهر: 5', $xml);
        $this->assertStringContainsString('موظف تأخر', $xml);
        $this->assertStringContainsString('09:15', $xml);
    }
}
