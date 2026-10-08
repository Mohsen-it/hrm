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
                ['status' => 'leave', 'name' => 'موظف إجازة', 'department_name' => 'القسم', 'notes' => 'عدد أيام الإجازة خلال السنة: ‏٥'],
                ['status' => 'absent', 'name' => 'موظف بلا بصمة', 'department_name' => 'القسم', 'has_no_fingerprint' => true, 'notes' => 'الموظف غير مسجل في جهاز البصمة'],
                ['status' => 'rest', 'name' => 'موظف راحة بلا بصمة', 'department_name' => 'القسم', 'has_no_fingerprint' => true, 'notes' => 'الموظف غير مسجل في جهاز البصمة'],
                ['status' => 'absent', 'name' => 'موظف غياب مسجل بالبصمة', 'department_name' => 'القسم', 'has_no_fingerprint' => false],
                ['status' => 'present', 'name' => 'موظف دخول دون خروج', 'department_name' => 'القسم', 'rotation' => 'دورية النقل (ب)', 'check_in' => '08:30', 'has_incomplete_punch' => true, 'prev_check_in' => '07:55', 'prev_check_out' => '', 'prev_last_punch' => '13:40', 'expected_check_in' => '08:00', 'expected_check_out' => '17:00', 'expected_check_out_next_day' => false, 'notes' => 'لم يسجل بصمة الخروج حتى نهاية نافذة الخروج 10:00'],
                ['status' => 'present', 'name' => 'موظف بلا مسائية', 'department_name' => 'القسم', 'rotation' => 'دورية 1-3 (أ)', 'check_in' => '08:05', 'prev_last_punch' => '21:35', 'has_missing_evening_punch' => true, 'notes' => 'لم يسجل البصمة المسائية أمس'],
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
        $this->assertStringContainsString('عدد أيام الإجازة خلال السنة: ‏٥', $xml);
        $this->assertStringContainsString('موظف بلا بصمة', $xml);
        $this->assertStringContainsString('موظف راحة بلا بصمة', $xml);
        $this->assertStringContainsString('موظف دخول دون خروج', $xml);
        $this->assertStringContainsString('موظف تأخر', $xml);
        $this->assertStringContainsString('دورية الأمن (أ)', $xml);
        $this->assertStringContainsString('09:15', $xml);

        // The missing-checkout table now sits right after the lateness table and
        // carries the PREVIOUS day's actual entry and exit times (a genuinely
        // recorded checkout, else the last punch of that day — e.g. an early
        // exit the pipeline never counted as a checkout), never the expected
        // schedule times.
        $this->assertStringContainsString('تقرير عدم تسجيل بصمة الخروج حسب جداول الوقت', $xml);
        $this->assertStringContainsString('دورية النقل (ب)', $xml);
        $this->assertStringContainsString('07:55', $xml);
        $this->assertStringContainsString('13:40', $xml);
        $this->assertStringContainsString('لم يسجل بصمة الخروج حتى نهاية نافذة الخروج 10:00', $xml);

        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($xml));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $tables = $xpath->query('//w:tbl');
        $this->assertSame(7, $tables->length);

        // The "عدم تسجيل البصمة على الجهاز" table (index 4) lists every
        // employee without a fingerprint template — including rest-day
        // employees — and never a registered absent employee.
        $noFingerprint = $tables->item(4);
        $this->assertInstanceOf(\DOMElement::class, $noFingerprint);
        $this->assertSame(3, $xpath->query('./w:tr', $noFingerprint)->length); // header + 2 unregistered employees
        $this->assertStringContainsString('موظف راحة بلا بصمة', $noFingerprint->textContent);
        $this->assertStringNotContainsString('موظف غياب مسجل بالبصمة', $noFingerprint->textContent);

        // The غياب table (index 0) hides employees without an enrolled
        // fingerprint — they can never punch and stay visible in the
        // no-fingerprint table instead.
        $absent = $tables->item(0);
        $this->assertInstanceOf(\DOMElement::class, $absent);
        $this->assertStringContainsString('موظف غياب', $absent->textContent);
        $this->assertStringNotContainsString('موظف بلا بصمة', $absent->textContent);

        // Every data cell must render right-to-left: the template left the
        // القسم prototype cell of the no-fingerprint table (and the الدورية
        // cells of the lateness/missing-checkout tables) LTR, so multi-word
        // Arabic rendered with flipped word order.
        foreach ([0, 1, 2, 3, 4, 5, 6] as $tableIndex) {
            $table = $tables->item($tableIndex);
            $this->assertInstanceOf(\DOMElement::class, $table);
            $firstDataRow = $xpath->query('./w:tr[2]', $table)->item(0);
            if (! $firstDataRow instanceof \DOMElement) {
                continue;
            }
            foreach ($xpath->query('./w:tc', $firstDataRow) as $cell) {
                $this->assertInstanceOf(\DOMElement::class, $cell);
                $this->assertGreaterThan(0, $xpath->query('./w:p/w:pPr/w:bidi', $cell)->length);
                $this->assertGreaterThan(0, $xpath->query('./w:p/w:pPr/w:rPr/w:rtl', $cell)->length);
            }
        }

        // The lateness table keeps its six columns; the missing-checkout table
        // gains the expected-exit column (seven columns).
        $late = $tables->item(1);
        $this->assertInstanceOf(\DOMElement::class, $late);
        $lateHeader = $xpath->query('./w:tr[1]/w:tc', $late);
        $this->assertSame(6, $lateHeader->length);
        $this->assertStringContainsString('الدورية', $lateHeader->item(3)->textContent);
        $this->assertStringContainsString('وقت الحضور', $lateHeader->item(4)->textContent);

        $incomplete = $tables->item(2);
        $this->assertInstanceOf(\DOMElement::class, $incomplete);
        $header = $xpath->query('./w:tr[1]/w:tc', $incomplete);
        $this->assertSame(7, $header->length);
        $this->assertStringContainsString('الدورية', $header->item(3)->textContent);
        $this->assertStringContainsString('وقت الدخول الفعلي', $header->item(4)->textContent);
        $this->assertStringContainsString('وقت الخروج الفعلي', $header->item(5)->textContent);
        // The previous day's actual entry must be rendered (not today's
        // 08:30 check-in and not the 08:00 schedule time).
        $this->assertStringContainsString('07:55', $incomplete->textContent);

        // The evening-punch table (index 6) mirrors the lateness table:
        // rotation + the previous day's last recorded punch + notes carrying
        // the missing evening punch.
        $this->assertStringContainsString('تقرير عدم تسجيل البصمة المسائية لهذا اليوم', $xml);
        $evening = $tables->item(6);
        $this->assertInstanceOf(\DOMElement::class, $evening);
        $eveningHeader = $xpath->query('./w:tr[1]/w:tc', $evening);
        $this->assertSame(6, $eveningHeader->length);
        $this->assertStringContainsString('آخر بصمة مسجلة', $eveningHeader->item(4)->textContent);
        $this->assertStringContainsString('موظف بلا مسائية', $evening->textContent);
        $this->assertStringContainsString('دورية 1-3 (أ)', $evening->textContent);
        $this->assertStringContainsString('21:35', $evening->textContent);
        $this->assertStringContainsString('لم يسجل البصمة المسائية أمس', $evening->textContent);

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
            $this->assertSame($tableIndex === 1 ? 6 : 7, $grid->length);
            foreach ($xpath->query('./w:tr[2]/w:tc', $table) as $index => $cell) {
                $cellWidth = $xpath->query('./w:tcPr/w:tcW', $cell)->item(0);
                $this->assertInstanceOf(\DOMElement::class, $cellWidth);
                $this->assertSame($grid->item($index)->getAttribute('w:w'), $cellWidth->getAttribute('w:w'));
            }
        }
    }
}
