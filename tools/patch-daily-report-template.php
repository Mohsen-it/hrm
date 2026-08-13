<?php

/**
 * Patch the daily report Word template so its export tables stay in sync
 * with DailyReportDocxExport:
 *
 *   1. Add a "الدورية" (rotation) column to the lateness table (table 1).
 *   2. Relocate the missing-checkout table (previously the last table) right
 *      after the lateness table, rebuild it with the same six columns, and
 *      retitle its heading around the rotation exit window.
 *
 * The export writes the rotation value into the "الدورية" column, so this
 * patch must be re-applied if the template is re-exported from Word or
 * re-created from scratch.
 *
 * Usage: php tools/patch-daily-report-template.php
 */
const PATCH_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

$src = __DIR__.'/../resources/تقرير الغياب اليومي 5-8-2026.docx';
$tmp = tempnam(sys_get_temp_dir(), 'tpl-');
if (! copy($src, $tmp)) {
    fwrite(STDERR, "copy failed\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($tmp) !== true) {
    fwrite(STDERR, "open failed\n");
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
if ($xml === false) {
    fwrite(STDERR, "document.xml missing\n");
    exit(1);
}

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->preserveWhiteSpace = true;
if (! $doc->loadXML($xml)) {
    fwrite(STDERR, "loadXML failed\n");
    exit(1);
}

$xpath = new DOMXPath($doc);
$xpath->registerNamespace('w', PATCH_NS);

$tables = $xpath->query('//w:tbl');
$table = $tables->item(1); // lateness table
if (! $table instanceof DOMElement) {
    fwrite(STDERR, "lateness table not found\n");
    exit(1);
}

// 1) Widen the table grid so Word reserves space for the new column. This is
//    skipped when the column already exists so the patch can be re-run safely.
$headerTexts = [];
foreach ($xpath->query('./w:tr[1]/w:tc', $table) as $cell) {
    $headerTexts[] = trim($cell->textContent);
}
$inserted = 0;
if (in_array('الدورية', $headerTexts, true)) {
    echo "الدورية column already present, skipping column insert\n";
} else {
    $grid = $xpath->query('./w:tblGrid', $table)->item(0);
    if (! $grid instanceof DOMElement) {
        fwrite(STDERR, "tblGrid not found\n");
        exit(1);
    }
    $gridCol = $doc->createElementNS(PATCH_NS, 'w:gridCol');
    $gridCol->setAttributeNS(PATCH_NS, 'w:w', '1505');
    $grid->appendChild($gridCol);

    // 2) Insert a cloned "وقت الحضور" cell before itself in every row and clear it.
    $rows = $xpath->query('./w:tr', $table);
    foreach ($rows as $rowIndex => $row) {
        if (! $row instanceof DOMElement) {
            continue;
        }
        $cells = $xpath->query('./w:tc', $row);
        $anchor = $cells->item(3); // وقت الحضور cell (0-based)
        if (! $anchor instanceof DOMElement) {
            continue;
        }
        $clone = $anchor->cloneNode(true);
        foreach ($xpath->query('.//w:t', $clone) as $text) {
            $text->nodeValue = '';
        }
        if ($rowIndex === 0) {
            // Header row: label the new column.
            foreach ($xpath->query('.//w:t', $clone) as $text) {
                $text->nodeValue = 'الدورية';
            }
        }
        $row->insertBefore($clone, $anchor);
        $inserted++;
    }

    if ($inserted === 0) {
        fwrite(STDERR, "no rows patched\n");
        exit(1);
    }
}

// 3) Relocate the missing-checkout table right after the lateness table. The
//    table is located by its original heading so an already-relocated template
//    (where the heading was retitled) is skipped instead of misidentified.
$incomplete = null;
foreach ($tables as $candidate) {
    if (! $candidate instanceof DOMElement) {
        continue;
    }
    $heading = $candidate->previousSibling;
    while ($heading && ! ($heading instanceof DOMElement && $heading->localName === 'p')) {
        $heading = $heading->previousSibling;
    }
    if ($heading instanceof DOMElement && str_contains($heading->textContent, 'عدم الالتزام تسجيل الدخول/الخروج')) {
        $incomplete = $candidate;
        break;
    }
}
if (! $incomplete instanceof DOMElement) {
    echo "missing-checkout table already relocated, skipping\n";
} else {
    $body = $xpath->query('//w:body')->item(0);
    if (! $body instanceof DOMElement) {
        fwrite(STDERR, "body not found\n");
        exit(1);
    }

    // The heading is the paragraph immediately before the table.
    $heading = $incomplete->previousSibling;
    while ($heading && ! ($heading instanceof DOMElement && $heading->localName === 'p')) {
        $heading = $heading->previousSibling;
    }
    if (! $heading instanceof DOMElement) {
        fwrite(STDERR, "missing-checkout heading not found\n");
        exit(1);
    }

    // Retitle the heading to reflect the time-table criterion: the exit
    // deadline comes from each rotation's time table (جدول الوقت), not from
    // the rotation's absolute punch window.
    $headingTexts = $xpath->query('.//w:t', $heading);
    if ($headingTexts->length === 0) {
        fwrite(STDERR, "heading has no text\n");
        exit(1);
    }
    $headingTexts->item(0)->nodeValue = 'تقرير عدم تسجيل بصمة الخروج حسب جداول الوقت';
    for ($i = 1; $i < $headingTexts->length; $i++) {
        $headingTexts->item($i)->nodeValue = '';
    }

    // Rebuild the table with the same columns as the lateness table.
    $newTable = $table->cloneNode(true);

    // Anchor: the blank paragraph right after the lateness table.
    $anchor = $table->nextSibling;
    while ($anchor && ! ($anchor instanceof DOMElement && $anchor->localName === 'p')) {
        $anchor = $anchor->nextSibling;
    }
    if (! $anchor instanceof DOMElement) {
        fwrite(STDERR, "anchor paragraph not found\n");
        exit(1);
    }

    $body->insertBefore($heading, $anchor->nextSibling);
    $body->insertBefore($newTable, $heading->nextSibling);
    $body->removeChild($incomplete);
}

// 4) Give the missing-checkout table its expected-exit column so the report
//    reads against جداول الوقت: الدورية / وقت الدخول المتوقع / وقت الخروج
//    المتوقع / ملاحظات. The table is located by its current heading, so an
//    already-patched template is skipped and the patch stays idempotent.
$incomplete = null;
foreach ($xpath->query('//w:tbl') as $candidate) {
    if (! $candidate instanceof DOMElement) {
        continue;
    }
    $heading = $candidate->previousSibling;
    while ($heading && ! ($heading instanceof DOMElement && $heading->localName === 'p')) {
        $heading = $heading->previousSibling;
    }
    if ($heading instanceof DOMElement && str_contains($heading->textContent, 'تقرير عدم تسجيل بصمة الخروج')) {
        $incomplete = $candidate;
        break;
    }
}
if (! $incomplete instanceof DOMElement) {
    fwrite(STDERR, "missing-checkout table not found\n");
    exit(1);
}

$incompleteHeaders = [];
foreach ($xpath->query('./w:tr[1]/w:tc', $incomplete) as $cell) {
    $incompleteHeaders[] = trim($cell->textContent);
}
if (in_array('وقت الخروج المتوقع', $incompleteHeaders, true)) {
    echo "وقت الخروج المتوقع column already present, skipping\n";
} else {
    // Insert a grid column before the notes column.
    $grid = $xpath->query('./w:tblGrid', $incomplete)->item(0);
    if (! $grid instanceof DOMElement) {
        fwrite(STDERR, "missing-checkout grid not found\n");
        exit(1);
    }
    $gridCols = $xpath->query('./w:tblGrid/w:gridCol', $incomplete);
    $notesCol = $gridCols->item($gridCols->length - 1);
    if (! $notesCol instanceof DOMElement) {
        fwrite(STDERR, "missing-checkout grid columns not found\n");
        exit(1);
    }
    $gridCol = $doc->createElementNS(PATCH_NS, 'w:gridCol');
    $gridCol->setAttributeNS(PATCH_NS, 'w:w', '950');
    $grid->insertBefore($gridCol, $notesCol);

    // Clone the "وقت الحضور" cell as the new column in every row; label the
    // header cell and relabel the entry column for the time-table basis.
    $inserted = 0;
    foreach ($xpath->query('./w:tr', $incomplete) as $rowIndex => $row) {
        if (! $row instanceof DOMElement) {
            continue;
        }
        $cells = $xpath->query('./w:tc', $row);
        $checkInCell = $cells->item($cells->length - 2); // وقت الحضور cell
        $notesCell = $cells->item($cells->length - 1); // ملاحظات cell
        if (! $checkInCell instanceof DOMElement || ! $notesCell instanceof DOMElement) {
            continue;
        }
        $clone = $checkInCell->cloneNode(true);
        foreach ($xpath->query('.//w:t', $clone) as $text) {
            $text->nodeValue = '';
        }
        if ($rowIndex === 0) {
            $cloneTexts = $xpath->query('.//w:t', $clone);
            if ($cloneTexts->length > 0) {
                $cloneTexts->item(0)->nodeValue = 'وقت الخروج المتوقع';
                for ($i = 1; $i < $cloneTexts->length; $i++) {
                    $cloneTexts->item($i)->nodeValue = '';
                }
            }
            $checkInTexts = $xpath->query('.//w:t', $checkInCell);
            if ($checkInTexts->length > 0) {
                $checkInTexts->item(0)->nodeValue = 'وقت الدخول المتوقع';
                for ($i = 1; $i < $checkInTexts->length; $i++) {
                    $checkInTexts->item($i)->nodeValue = '';
                }
            }
        }
        $row->insertBefore($clone, $notesCell);
        $inserted++;
    }
    if ($inserted === 0) {
        fwrite(STDERR, "no rows patched for expected-exit column\n");
        exit(1);
    }
    echo "inserted expected-exit column into {$inserted} rows\n";
}

// 5) Normalize every data table so the exported rows render cleanly in Word:
//    cell content is centered vertically, stale sample rows are dropped, and
//    the check-in tables (six-column lateness + seven-column missing-checkout)
//    get column widths that match the grid. The bottom-aligned cells were the
//    cause of the "pushed up" rows the user reported, so every table is
//    aligned the same way to avoid a repeat complaint on the other tables.
$tables = $xpath->query('//w:tbl');
$sixWidths = [700, 3100, 2500, 2300, 1000, 1756]; // lateness grid, sum 11356
$sevenWidths = [600, 2700, 2200, 2000, 950, 950, 1956]; // missing-checkout grid, sum 11356
$normalized = 0;
foreach ($tables as $target) {
    if (! $target instanceof DOMElement) {
        continue;
    }
    if ($xpath->query('./w:tr', $target)->length < 2) {
        continue;
    }

    // The lateness table and the missing-checkout table get their grid rebuilt
    // to the canonical widths; other tables keep their proven layout.
    $cellCount = $xpath->query('./w:tr[1]/w:tc', $target)->length;
    $headerText = [];
    foreach ($xpath->query('./w:tr[1]/w:tc', $target) as $cell) {
        $headerText[] = trim($cell->textContent);
    }
    $rebuild = false;
    $widths = [];
    if ($cellCount === count($sixWidths)
        && in_array('الدورية', $headerText, true)
        && in_array('وقت الحضور', $headerText, true)) {
        $rebuild = true;
        $widths = $sixWidths;
    } elseif ($cellCount === count($sevenWidths) && in_array('وقت الخروج المتوقع', $headerText, true)) {
        $rebuild = true;
        $widths = $sevenWidths;
    }
    if ($rebuild) {
        $grid = $xpath->query('./w:tblGrid', $target)->item(0);
        if ($grid instanceof DOMElement) {
            while ($grid->firstChild) {
                $grid->removeChild($grid->firstChild);
            }
            foreach ($widths as $width) {
                $col = $doc->createElementNS(PATCH_NS, 'w:gridCol');
                $col->setAttributeNS(PATCH_NS, 'w:w', (string) $width);
                $grid->appendChild($col);
            }
        }
    }

    $rowsToRemove = [];
    foreach ($xpath->query('./w:tr', $target) as $rowIndex => $row) {
        if (! $row instanceof DOMElement) {
            continue;
        }
        if ($rowIndex > 1) {
            $rowsToRemove[] = $row;

            continue;
        }
        foreach ($xpath->query('./w:tc', $row) as $cellIndex => $cell) {
            if (! $cell instanceof DOMElement) {
                continue;
            }
            $tcPr = $xpath->query('./w:tcPr', $cell)->item(0);
            if (! $tcPr instanceof DOMElement) {
                $tcPr = $doc->createElementNS(PATCH_NS, 'w:tcPr');
                $cell->insertBefore($tcPr, $cell->firstChild);
            }
            if ($rebuild && $cellIndex < count($widths)) {
                $tcW = $xpath->query('./w:tcW', $tcPr)->item(0);
                if (! $tcW instanceof DOMElement) {
                    $tcW = $doc->createElementNS(PATCH_NS, 'w:tcW');
                    $tcPr->insertBefore($tcW, $tcPr->firstChild);
                }
                $tcW->setAttributeNS(PATCH_NS, 'w:type', 'dxa');
                $tcW->setAttributeNS(PATCH_NS, 'w:w', (string) $widths[$cellIndex]);
            }
            $vAlign = $xpath->query('./w:vAlign', $tcPr)->item(0);
            if (! $vAlign instanceof DOMElement) {
                $vAlign = $doc->createElementNS(PATCH_NS, 'w:vAlign');
                $tcPr->appendChild($vAlign);
            }
            $vAlign->setAttributeNS(PATCH_NS, 'w:val', 'center');
        }
    }
    foreach ($rowsToRemove as $row) {
        $target->removeChild($row);
    }

    // Clear the prototype row so the template ships without sample data.
    foreach ($xpath->query('./w:tr[2]/w:tc', $target) as $cell) {
        foreach ($xpath->query('.//w:t', $cell) as $text) {
            $text->nodeValue = '';
        }
    }

    $normalized++;
}
if ($normalized > 0) {
    echo "normalized tables: {$normalized}\n";
}

$saved = $doc->saveXML();
if ($saved === false) {
    fwrite(STDERR, "saveXML failed\n");
    exit(1);
}

$zip->addFromString('word/document.xml', $saved);
$zip->close();

copy($tmp, $src);
@unlink($tmp);

echo "done; rows patched: {$inserted}; missing-checkout table relocated\n";
