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
                ['status' => 'late', 'name' => 'موظف تأخر', 'department_name' => 'القسم', 'check_in' => '09:15', 'notes' => 'عدد مرات التأخر خلال الشهر: ‏٤'],
                ['status' => 'leave', 'name' => 'موظف إجازة', 'department_name' => 'القسم', 'notes' => 'عدد أيام الإجازة خلال الشهر: ‏٥'],
                ['status' => 'present', 'name' => 'موظف بلا بصمة', 'department_name' => 'القسم', 'has_no_fingerprint' => true, 'notes' => 'الموظف غير مسجل في جهاز البصمة'],
                ['status' => 'present', 'name' => 'موظف دخول دون خروج', 'department_name' => 'القسم', 'has_incomplete_punch' => true, 'notes' => 'بعد انتهاء الدوام المتوقع 17:00'],
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
        $this->assertStringContainsString('09:15', $xml);
    }
}
