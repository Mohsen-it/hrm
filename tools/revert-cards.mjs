#!/usr/bin/env node
// Revert: convert v3's <Card variant="base" padding="X" (extra classes)> back to <div class="card p-N (extra)">
import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let reverted = 0;

function getAllVueFiles(dir) {
    const files = [];
    for (const entry of readdirSync(dir)) {
        const fullPath = join(dir, entry);
        const stat = statSync(fullPath);
        if (stat.isDirectory()) files.push(...getAllVueFiles(fullPath));
        else if (entry.endsWith('.vue')) files.push(fullPath);
    }
    return files;
}

const paddingMap = { sm: 4, md: 6, lg: 8, xl: 12, none: 0 };

for (const file of getAllVueFiles(basePath)) {
    let content;
    try { content = readFileSync(file, 'utf8'); } catch (e) { continue; }

    // Match <Card variant="base" padding="X" extraClasses> at template level (no header/footer slots)
    // These were originally <div class="card p-N extraClasses">
    const cardRe = /<Card\s+variant="base"\s+padding="(\w+)"((?:\s+[\w-]+(?:="[^"]*")?)*?)\s*>/g;
    const replacements = [];
    let m;
    while ((m = cardRe.exec(content)) !== null) {
        const padding = m[1];
        const extras = m[2].trim();
        const pValue = paddingMap[padding] || 6;

        // Convert extras to class strings
        // e.g. "mb-6" stays as is, "grid grid-cols-3" stays
        // e.g. "lg:col-span-2" stays
        // Build the class attribute
        let classAttr = `card p-${pValue}`;
        if (extras) {
            // Parse extras - they should be valid Tailwind classes
            classAttr += ' ' + extras;
        }

        replacements.push({
            from: m.index,
            to: m.index + m[0].length,
            replacement: `<div class="${classAttr}">`,
        });
    }

    // Apply in reverse
    replacements.reverse();
    for (const r of replacements) {
        content = content.slice(0, r.from) + r.replacement + content.slice(r.to);
        reverted++;
    }

    // Also revert orphan </Card> that were added (only if not matched to a <Card> as="form" or with header/footer)
    // The pattern is: find </Card> that doesn't have a matching opening
    // Since this is complex, let me just match simple patterns:
    // If we have <Card variant="base" padding="X" ...>...</Card> on the same logical scope, the closing is fine
    // The fix script may have over-converted some </div> to </Card>. Let me undo those that don't have a matching <Card>

    // Actually, simpler: count Card opens vs closes. If balanced, OK. If not, fix the unbalanced ones.
    // But this is what we did before and it failed.

    // Let me just count and report
    const opens = (content.match(/<Card\s+/g) || []).length;
    const closes = (content.match(/<\/Card>/g) || []).length;
    if (opens !== closes) {
        console.log(`${file.replace('D:\\hrm_alepair\\resources\\js\\Pages\\', '')}: <Card> opens=${opens} closes=${closes}`);
    }

    if (replacements.length > 0) {
        try { writeFileSync(file, content, 'utf8'); } catch (e) {}
    }
}

console.log(`\nReverted ${reverted} Card -> div conversions`);
