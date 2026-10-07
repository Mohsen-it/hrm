#!/usr/bin/env node
/**
 * check-design-tokens.mjs — بوابة حوكمة نظام التصميم (specs/012 — المرحلة 5)
 *
 * تفشل عند وجود:
 *   1. ألوان خام من لوحة Tailwind (bg-red-500, text-blue-700, ...) داخل
 *      resources/js/Components و resources/js/Pages — يجب استخدام توكنز mistral-*.
 *   2. aria-label يحتوي نصاً عربياً صلباً في ui/ + layout/ + navigation/ —
 *      يجب استخدام t('key') عبر useTranslations.
 *   3. radius خارج النظام (rounded-2xl / rounded-3xl) في ui/ — القاعدة:
 *      أزرار/حقول rounded-md، بطاقات/مودالات rounded-lg، شارات rounded-full.
 *   4. hex مبعثر في Components/Pages (P0-UX) — ألوان الرسوم يجب أن تعيش
 *      في خريطة مركزية واحدة (Dashboard.vue: CHART_COLORS) مطابقة لتوكنز app.css.
 *   5. جداول <table> يدوية في Pages (P1-UX) — يجب استخدام DataTable/ReportTable.
 *   6. خصائص فيزيائية (mr-/ml-/text-left/...) بدل المنطقية (me-/ms-/text-start).
 *
 * التشغيل: npm run lint:tokens
 */

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, sep } from 'node:path';

const ROOT = process.cwd();

const CHECKS = [
    {
        id: 'raw-colors',
        description: 'Raw Tailwind palette colors (must use mistral-* tokens)',
        dirs: ['resources/js/Components', 'resources/js/Pages'],
        extensions: ['.vue', '.js', '.ts'],
        pattern: /\b(?:bg|text|border|ring|fill|stroke|from|via|to|decoration|divide|outline|accent|caret|shadow)-(?:red|green|amber|blue|emerald|cyan|purple|gray|slate|zinc|neutral|stone|yellow|orange|sky|rose|teal|indigo|violet|fuchsia|pink|lime)-\d{2,3}\b/,
    },
    {
        id: 'hardcoded-aria-labels',
        description: 'Hardcoded Arabic text in aria-label (must use t(\'key\'))',
        dirs: ['resources/js/Components/ui', 'resources/js/Components/layout', 'resources/js/Components/navigation'],
        extensions: ['.vue'],
        pattern: /aria-label="[^"]*[\u0600-\u06FF][^"]*"/,
    },
    {
        id: 'off-system-radius',
        description: 'Off-system radius in ui/ (rounded-2xl / rounded-3xl)',
        dirs: ['resources/js/Components/ui'],
        extensions: ['.vue'],
        pattern: /\brounded-(?:2xl|3xl)\b/,
    },
    {
        id: 'scattered-hex',
        description: 'Hardcoded hex colors outside the central chart palette (use mistral-* tokens / CHART_COLORS)',
        dirs: ['resources/js/Components', 'resources/js/Pages'],
        extensions: ['.vue', '.js', '.ts'],
        pattern: /#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/,
        // Central palette lives in resources/js/utils/chartPalette.js (1:1
        // with app.css mistral-* tokens). Canvas/print cannot use classes.
        allowlistFile: ['resources/js/utils/chartPalette.js'],
    },
    {
        id: 'manual-tables',
        description: 'Manual <table> in Pages (must use DataTable / ReportTable)',
        dirs: ['resources/js/Pages'],
        extensions: ['.vue'],
        pattern: /<table[\s>]/,
        // Legitimate matrix visualizations (NOT data tables — DataTable would
        // be the wrong component): timeline/calendar/schedule grids with
        // sticky headers, the rotation month calendar, and the editable
        // balances matrix (inline cell editing). They must keep scope="col"
        // + logical alignment + translated aria-labels.
        allowlistFile: [
            'resources/js/Pages/Shifts/Rotations/Timeline.vue',
            'resources/js/Pages/Shifts/Calendar/TeamCalendar.vue',
            'resources/js/Pages/Shifts/ShiftCategories/SchedulePreview.vue',
            'resources/js/Pages/Shifts/Absence/EmployeeMonthlyAttendance.vue',
            'resources/js/Pages/Shifts/Rotations/Show.vue',
            'resources/js/Pages/Vacations/Balances/Index.vue',
        ],
    },
    {
        id: 'physical-rtl',
        description: 'Physical RTL utilities (use logical me-/ms-/ps-/pe-/start-/end-/text-start)',
        dirs: ['resources/js/Components', 'resources/js/Pages'],
        extensions: ['.vue'],
        pattern: /\b(?:mr-|ml-|pl-|pr-|text-left|text-right|left-0|right-0)\b/,
        // LTR islands (e.g. JSON <pre dir="ltr">) legitimately keep text-left.
        allowlistLine: [/dir="ltr"/],
    },
];

function* walk(dir) {
    let entries;
    try {
        entries = readdirSync(dir);
    } catch {
        return;
    }
    for (const entry of entries) {
        const full = join(dir, entry);
        let stats;
        try {
            stats = statSync(full);
        } catch {
            continue;
        }
        if (stats.isDirectory()) yield* walk(full);
        else yield full;
    }
}

let totalViolations = 0;

for (const check of CHECKS) {
    const violations = [];

    for (const dir of check.dirs) {
        for (const file of walk(join(ROOT, dir))) {
            if (!check.extensions.some((ext) => file.endsWith(ext))) continue;
            if (file.includes(`${sep}node_modules${sep}`)) continue;

            const normalizedFile = file.split(sep).join('/');
            if (check.allowlistFile?.some((frag) => normalizedFile.endsWith(frag))) continue;
            const lines = readFileSync(file, 'utf8').split('\n');
            lines.forEach((line, i) => {
                if (!check.pattern.test(line)) return;
                if (check.allowlistLine?.some((re) => re.test(line))) return;
                violations.push({ file, line: i + 1, text: line.trim().slice(0, 120) });
            });
        }
    }

    if (violations.length > 0) {
        totalViolations += violations.length;
        console.error(`\n✖ ${check.id}: ${check.description}`);
        for (const v of violations) {
            console.error(`   ${v.file}:${v.line}`);
            console.error(`     ${v.text}`);
        }
    } else {
        console.log(`✔ ${check.id}: OK`);
    }
}

if (totalViolations > 0) {
    console.error(`\n✖ ${totalViolations} design-token violation(s) found. Fix them or update scripts/check-design-tokens.mjs if a rule needs refining.`);
    process.exit(1);
}

console.log('\n✔ Design tokens gate passed.');
