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

            const lines = readFileSync(file, 'utf8').split('\n');
            lines.forEach((line, i) => {
                if (check.pattern.test(line)) {
                    violations.push({ file, line: i + 1, text: line.trim().slice(0, 120) });
                }
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
