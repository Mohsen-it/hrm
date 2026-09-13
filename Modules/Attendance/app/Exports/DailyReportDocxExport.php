<?php

namespace Modules\Attendance\Exports;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Produces the daily report from the approved Word template.
 *
 * The template owns every visual detail; this class only replaces its date
 * and the data rows within its six existing report tables.
 */
class DailyReportDocxExport
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function __construct(private array $report) {}

    /** Build the Word document and return its binary contents. */
    public function toBinary(): string
    {
        $template = resource_path('تقرير الغياب اليومي 5-8-2026.docx');
        if (! is_file($template)) {
            throw new RuntimeException('Daily report Word template was not found.');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'daily-report-');
        if ($temporaryFile === false || ! copy($template, $temporaryFile)) {
            throw new RuntimeException('Unable to create the daily report document.');
        }

        $archive = new ZipArchive;
        if ($archive->open($temporaryFile) !== true) {
            @unlink($temporaryFile);
            throw new RuntimeException('Unable to open the daily report document.');
        }

        $isOpen = true;
        try {
            $xml = $archive->getFromName('word/document.xml');
            if ($xml === false) {
                throw new RuntimeException('Daily report document content is missing.');
            }

            $archive->addFromString('word/document.xml', $this->replaceContent($xml));
            $archive->close();
            $isOpen = false;
            $binary = file_get_contents($temporaryFile);
            if ($binary === false) {
                throw new RuntimeException('Unable to read the daily report document.');
            }

            return $binary;
        } finally {
            if ($isOpen) {
                $archive->close();
            }
            @unlink($temporaryFile);
        }
    }

    /** Replace the date and the six template tables. */
    private function replaceContent(string $xml): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NAMESPACE);

        $date = Carbon::parse($this->report['date']);
        $this->replaceParagraph($xpath, 'التاريخ:', 'التاريخ:    '.$date->format('d / m /Y'));

        $groups = [
            'absent', 'late', 'incomplete', 'leave', 'no_fingerprint', 'mission',
        ];
        $tables = $xpath->query('//w:tbl');
        foreach ($groups as $index => $status) {
            $table = $tables?->item($index);
            if ($table instanceof DOMElement) {
                $hasCheckIn = in_array($status, ['late', 'incomplete'], true);
                // The missing-checkout table carries the expected exit time
                // from the rotation's time table as an extra column.
                $hasExpectedExit = $status === 'incomplete';
                if ($hasCheckIn) {
                    // Give the notes column more room so its Arabic text does not
                    // wrap into several lines and inflate the row height.
                    $this->rebalanceGrid($xpath, $table);
                }
                $this->replaceTableRows(
                    $document,
                    $xpath,
                    $table,
                    $this->rowsFor($status),
                    $hasCheckIn,
                    $hasExpectedExit,
                );
            }
        }

        return $document->saveXML() ?: $xml;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(string $status): array
    {
        return collect($this->report['rows'])
            ->filter(fn (array $row) => match ($status) {
                // "عدم تسجيل البصمة على الجهاز" lists every employee in the
                // report scope whose fingerprint is not enrolled on the device
                // — mirroring the "Unregistered Employees" page, the web
                // report filter and the stats counter. Restricting it to
                // absentees made the table silently omit unregistered
                // employees who were on a rest day, on leave, etc.
                'no_fingerprint' => (bool) ($row['has_no_fingerprint'] ?? false),
                // The غياب table hides employees without an enrolled
                // fingerprint: they can never punch, so they would otherwise
                // sit in this table every day as noise. They stay visible in
                // the "عدم تسجيل البصمة على الجهاز" table instead.
                // Employees still inside their arrival window ride along in
                // this table (the fixed template has no seventh table): their
                // notes column carries "بانتظار الوصول — الدوام المتوقع …"
                // instead of an absence count, so nobody mistakes them for
                // absentees.
                'absent' => in_array($row['status'] ?? null, ['absent', 'awaiting'], true) && ! ($row['has_no_fingerprint'] ?? false),
                'incomplete' => (bool) ($row['has_incomplete_punch'] ?? false),
                default => ($row['status'] ?? null) === $status,
            })
            ->values()
            ->all();
    }

    /**
     * Widen the notes column of the check-in tables at the expense of the
     * rotation and check-in columns. Long Arabic notes otherwise wrap inside
     * the narrow notes cell and push the row height far above the others.
     *
     * The six-column grid is the lateness table; the seven-column grid is the
     * missing-checkout table (which also carries the expected exit time). The
     * prototype data row's cell widths are kept in sync with the new grid so
     * every cloned row keeps its columns aligned with the table grid.
     */
    private function rebalanceGrid(DOMXPath $xpath, DOMElement $table): void
    {
        $columns = $xpath->query('./w:tblGrid/w:gridCol', $table);
        if (! $columns || $columns->length < 6) {
            return;
        }

        $adjustments = $columns->length === 7
            ? [0 => -100, 3 => -500, 4 => -350, 5 => -350, 6 => 1300]
            : [0 => -100, 3 => -500, 4 => -350, 5 => 950];
        $widths = [];
        $index = 0;
        foreach ($columns as $column) {
            if ($column instanceof DOMElement) {
                $delta = $adjustments[$index] ?? 0;
                $widths[$index] = max(300, (int) $column->getAttribute('w:w') + $delta);
                $column->setAttribute('w:w', (string) $widths[$index]);
            }
            $index++;
        }

        $rows = $xpath->query('./w:tr', $table);
        $prototype = $rows?->item(1);
        if (! $prototype instanceof DOMElement) {
            return;
        }

        $index = 0;
        foreach ($xpath->query('./w:tc', $prototype) as $cell) {
            if ($cell instanceof DOMElement && isset($widths[$index])) {
                $width = $xpath->query('./w:tcPr/w:tcW', $cell)->item(0);
                if ($width instanceof DOMElement) {
                    $width->setAttribute('w:w', (string) $widths[$index]);
                }
            }
            $index++;
        }
    }

    /**
     * Replace every template data row while retaining its complete styling.
     *
     * For the lateness and missing-checkout tables ($hasCheckIn = true) the
     * template must keep the "الدورية" column immediately before the check-in
     * column: the rotation value is written into that cell and the check-in
     * time into the following one. In the missing-checkout table the check-in
     * column shows the expected entry time from the rotation's time table and
     * $hasExpectedExit adds the expected exit time column after it, so the
     * table reads strictly against جداول الوقت.
     */
    private function replaceTableRows(DOMDocument $document, DOMXPath $xpath, DOMElement $table, array $rows, bool $hasCheckIn, bool $hasExpectedExit = false): void
    {
        $tableRows = $xpath->query('./w:tr', $table);
        $prototype = $tableRows?->item(1);
        if (! $prototype instanceof DOMElement) {
            return;
        }

        // The template left some prototype cells LTR (e.g. the القسم cell of
        // the no-fingerprint table, the الدورية cells of the lateness and
        // missing-checkout tables): multi-word Arabic then renders with
        // flipped word order. Normalizing the prototype fixes every clone.
        $this->ensureRtlRow($document, $xpath, $prototype);

        $rowsToRemove = [];
        for ($i = 1; $i < $tableRows->length; $i++) {
            $rowsToRemove[] = $tableRows->item($i);
        }
        foreach ($rowsToRemove as $row) {
            $table->removeChild($row);
        }

        foreach ($rows as $index => $row) {
            $clone = $prototype->cloneNode(true);
            if ($clone instanceof DOMElement) {
                $values = [
                    (string) ($index + 1),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['department_name'] ?? '—'),
                ];
                if ($hasCheckIn) {
                    // The lateness table carries the employee's rotation before
                    // the check-in time so the reviewer can see which rotation
                    // the late employee belongs to. The missing-checkout table
                    // shows the expected entry time from the time table instead
                    // of the raw punch.
                    $values[] = (string) ($row['rotation'] ?? '—');
                    $values[] = $hasExpectedExit
                        ? (string) ($row['expected_check_in'] ?? '')
                        : (string) ($row['check_in'] ?? '');
                }
                if ($hasExpectedExit) {
                    $exitTime = (string) ($row['expected_check_out'] ?? '');
                    $values[] = $exitTime
                        .(($exitTime !== '' && (bool) ($row['expected_check_out_next_day'] ?? false)) ? ' (اليوم التالي)' : '');
                }
                $values[] = (string) ($row['notes'] ?? '');
                $this->fillRow($xpath, $clone, $values);
                $table->appendChild($clone);
            }
        }
    }

    /**
     * Force a prototype data row's cells to render right-to-left.
     *
     * Adds the paragraph bidi marker and the run rtl marker wherever the
     * template omitted them. Cells that already carry them are untouched,
     * so correctly-styled cells render exactly as before.
     */
    private function ensureRtlRow(DOMDocument $document, DOMXPath $xpath, DOMElement $row): void
    {
        foreach ($xpath->query('./w:tc', $row) as $cell) {
            if (! $cell instanceof DOMElement) {
                continue;
            }
            foreach ($xpath->query('./w:p', $cell) as $paragraph) {
                if (! $paragraph instanceof DOMElement) {
                    continue;
                }
                $pPr = $xpath->query('./w:pPr', $paragraph)->item(0);
                if (! $pPr instanceof DOMElement) {
                    $pPr = $document->createElementNS(self::WORD_NAMESPACE, 'w:pPr');
                    $paragraph->insertBefore($pPr, $paragraph->firstChild);
                }
                if ($xpath->query('./w:bidi', $pPr)->length === 0) {
                    $pPr->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:bidi'));
                }
                $pRpr = $xpath->query('./w:rPr', $pPr)->item(0);
                if (! $pRpr instanceof DOMElement) {
                    $pRpr = $document->createElementNS(self::WORD_NAMESPACE, 'w:rPr');
                    $pPr->appendChild($pRpr);
                }
                if ($xpath->query('./w:rtl', $pRpr)->length === 0) {
                    $pRpr->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:rtl'));
                }
            }
            foreach ($xpath->query('.//w:r/w:rPr', $cell) as $rPr) {
                if ($rPr instanceof DOMElement && $xpath->query('./w:rtl', $rPr)->length === 0) {
                    $rPr->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:rtl'));
                }
            }
        }
    }

    /** Fill visible text in a Word table row without changing its formatting. */    private function fillRow(DOMXPath $xpath, DOMElement $row, array $values): void
    {
        $cells = $xpath->query('./w:tc', $row);
        foreach ($values as $index => $value) {
            $cell = $cells?->item($index);
            if ($cell instanceof DOMElement) {
                $this->setElementText($xpath, $cell, $value);
            }
        }
    }

    /** Locate the paragraph that starts with a label and replace its text. */
    private function replaceParagraph(DOMXPath $xpath, string $startsWith, string $replacement): void
    {
        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            if ($paragraph instanceof DOMElement && str_starts_with($paragraph->textContent, $startsWith)) {
                $this->setElementText($xpath, $paragraph, $replacement);

                return;
            }
        }
    }

    /** Set the first text run and clear any additional runs, preserving styles. */
    private function setElementText(DOMXPath $xpath, DOMElement $element, string $value): void
    {
        $texts = $xpath->query('.//w:t', $element);
        if (! $texts || $texts->length === 0) {
            // Some template note cells are intentionally blank and therefore
            // have no text run to replace. Add one while retaining the cell's
            // existing paragraph and table formatting.
            $paragraph = $xpath->query('.//w:p', $element)?->item(0);
            if (! $paragraph instanceof DOMElement) {
                return;
            }

            $run = $element->ownerDocument?->createElementNS(self::WORD_NAMESPACE, 'w:r');
            $text = $element->ownerDocument?->createElementNS(self::WORD_NAMESPACE, 'w:t');
            if (! $run instanceof DOMElement || ! $text instanceof DOMElement) {
                return;
            }

            $text->nodeValue = $value;
            $run->appendChild($text);
            $paragraph->appendChild($run);

            return;
        }
        $texts->item(0)->nodeValue = $value;
        for ($i = 1; $i < $texts->length; $i++) {
            $texts->item($i)->nodeValue = '';
        }
    }
}
