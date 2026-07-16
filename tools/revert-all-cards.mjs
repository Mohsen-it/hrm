#!/usr/bin/env node
// Comprehensive revert: undo all v3 card changes for files with broken Card balance
import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let revertedFiles = 0;

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

    const opens = (content.match(/<Card\s+/g) || []).length;
    const closes = (content.match(/<\/Card>/g) || []).length;

    if (opens === closes && opens === 0) continue; // nothing to do

    // If unbalanced, revert all Card -> div in this file
    if (opens !== closes || opens > 0) {
        // Convert <Card variant="base" padding="X" extras> back to <div class="card p-N extras">
        content = content.replace(/<Card\s+variant="base"\s+padding="(\w+)"((?:\s+[\w-]+(?:="[^"]*")?)*?)\s*>/g, (m, p, extras) => {
            const pValue = paddingMap[p] || 6;
            const extrasClean = extras.trim();
            const classAttr = extrasClean ? `card p-${pValue} ${extrasClean}` : `card p-${pValue}`;
            return `<div class="${classAttr}">`;
        });

        // Convert all </Card> back to </div>
        // But only if we're reverting the whole file (i.e., balance was off)
        const newOpens = (content.match(/<Card\s+/g) || []).length;
        const newCloses = (content.match(/<\/Card>/g) || []).length;
        if (newCloses > newOpens) {
            content = content.replace(/<\/Card>/g, '</div>');
        }

        try {
            writeFileSync(file, content, 'utf8');
            revertedFiles++;
        } catch (e) {}
    }
}

console.log(`Reverted ${revertedFiles} files`);
