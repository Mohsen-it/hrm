#!/usr/bin/env node
/**
 * Migrate Inertia pages from the wrapper-layout pattern to persistent layouts.
 *
 * For every page under resources/js/Pages that imports AppLayout:
 *   1. Removes the AppLayout import from <script setup>.
 *   2. Inserts a plain <script> block before <script setup> with
 *      `export default { layout: AppLayout }`.
 *   3. Removes the <AppLayout ...> wrapper from the template.
 *   4. If the wrapper carried a title, moves it into a usePageTitle(...) call
 *      inside <script setup> (persistent layouts can't receive a title prop).
 *
 * Usage:
 *   node scripts/migrate-persistent-layout.js            # apply
 *   node scripts/migrate-persistent-layout.js --dry-run  # preview only
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const DRY_RUN = process.argv.includes('--dry-run');
const PAGES_DIR = path.resolve(__dirname, '..', 'resources', 'js', 'Pages');

const IMPORT_RE = /^[ \t]*import\s+AppLayout\s+from\s+['"]@\/[Ll]ayouts\/AppLayout\.vue[''];?[ \t]*\r?\n?/gm;

function walk(dir) {
    const out = [];
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) out.push(...walk(full));
        else if (entry.name.endsWith('.vue')) out.push(full);
    }
    return out;
}

function extractTitle(attrs) {
    // Bound expression first, then plain literal; supports double or single
    // quoted attribute values. Returns a JS expression (or null).
    const bound =
        attrs.match(/\s:title="([^"]*)"/) || attrs.match(/\s:title='([^']*)'/);
    if (bound) return bound[1];
    const literal =
        attrs.match(/\stitle="([^"]*)"/) || attrs.match(/\stitle='([^']*)'/);
    if (literal) {
        return "'" + literal[1].replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
    }
    return null;
}

const files = walk(PAGES_DIR);
let migrated = 0;
let skipped = 0;
const problems = [];

for (const file of files) {
    const rel = path.relative(path.resolve(__dirname, '..'), file);
    const original = fs.readFileSync(file, 'utf8');

    if (!IMPORT_RE.test(original)) {
        skipped++;
        continue;
    }

    let content = original;
    const eol = original.includes('\r\n') ? '\r\n' : '\n';

    // --- 1. Template: strip the <AppLayout> wrapper, capture its title ------
    const openMatch = content.match(/<AppLayout\b[^>]*>/);
    if (!openMatch) {
        problems.push(`${rel}: opening <AppLayout> tag not found`);
        continue;
    }
    const titleExpr = extractTitle(openMatch[0].slice('<AppLayout'.length, -1));

    content = content.replace(/<AppLayout\b[^>]*>/, '');
    if (!/<\/AppLayout>\s*/.test(content)) {
        problems.push(`${rel}: closing </AppLayout> not found`);
        continue;
    }
    content = content.replace(/<\/AppLayout>\s*/, '');

    // --- 2. Drop the AppLayout import from <script setup> -------------------
    content = content.replace(IMPORT_RE, '');

    // --- 3. Insert the plain <script> layout block --------------------------
    const setupIdx = content.indexOf('<script setup>');
    if (setupIdx === -1) {
        problems.push(`${rel}: <script setup> not found`);
        continue;
    }

    const layoutBlock =
        '<script>' + eol +
        "import AppLayout from '@/Layouts/AppLayout.vue';" + eol +
        eol +
        'export default {' + eol +
        '    layout: AppLayout,' + eol +
        '};' + eol +
        '</script>' + eol +
        eol;

    content = content.slice(0, setupIdx) + layoutBlock + content.slice(setupIdx);

    // --- 4. Add usePageTitle import + call into <script setup> --------------
    if (titleExpr !== null) {
        const marker = '<script setup>';
        const setupStart = setupIdx + layoutBlock.length;
        const closeIdx = content.indexOf('</script>', setupStart);
        if (closeIdx === -1) {
            problems.push(`${rel}: <script setup> closing tag not found`);
            continue;
        }
        // `inner` is the setup body WITHOUT the `<script setup>` marker.
        const inner = content
            .slice(setupStart + marker.length, closeIdx)
            .replace(/^\r?\n/, '');
        const importLine = "import { usePageTitle } from '@/composables/usePageTitle';";
        const withImport = inner.includes(importLine)
            ? inner
            : importLine + eol + eol + inner;
        const withCall =
            marker + eol + withImport + eol + eol + 'usePageTitle(' + titleExpr + ');' + eol;
        content = content.slice(0, setupStart) + withCall + content.slice(closeIdx);
    }

    // --- Sanity checks ------------------------------------------------------
    if (/<AppLayout/.test(content)) {
        problems.push(`${rel}: <AppLayout> still present after migration`);
        continue;
    }
    if (!content.includes('layout: AppLayout')) {
        problems.push(`${rel}: layout: AppLayout missing after migration`);
        continue;
    }
    if (titleExpr !== null) {
        const checkStart = content.indexOf('<script setup>');
        const checkEnd = content.indexOf('</script>', checkStart);
        const setupBlock = content.slice(checkStart, checkEnd);
        if (!setupBlock.includes('usePageTitle(')) {
            problems.push(`${rel}: usePageTitle call not inside <script setup>`);
            continue;
        }
    }

    if (!DRY_RUN) {
        fs.writeFileSync(file, content);
    }
    migrated++;
}

console.log(
    `${DRY_RUN ? '[DRY RUN] ' : ''}Files processed: ${migrated} migrated, ` +
    `${skipped} skipped (no AppLayout import).`
);
if (problems.length) {
    console.log(`Problems (${problems.length}):`);
    for (const p of problems) console.log('  - ' + p);
    process.exitCode = 1;
}
