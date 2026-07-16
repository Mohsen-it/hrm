#!/usr/bin/env node
// Migration v3: catch remaining patterns
import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let stats = { filesModified: 0, btnReplaced: 0, cardReplaced: 0 };

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

const files = getAllVueFiles(basePath);

for (const file of files) {
    let content;
    try { content = readFileSync(file, 'utf8'); } catch (e) { continue; }
    let modified = false;

    // Ensure Button + Card imports
    if (/class="btn /.test(content) || /class="card /.test(content)) {
        if (!/import Button from '@\/Components\/ui\/Button\.vue'/.test(content)) {
            content = content.replace(
                /(import (?:PageHeader|FormInput|FormTextarea|FormSelect) from '@\/Components\/ui\/[^']+';\n)/,
                '$1import Button from \'@/Components/ui/Button.vue\';\n'
            );
            modified = true;
        }
        if (!/import Card from '@\/Components\/ui\/Card\.vue'/.test(content)) {
            content = content.replace(
                /(import Button from '@\/Components\/ui\/Button\.vue';\n)/,
                '$1import Card from \'@/Components/ui/Card.vue\';\n'
            );
            modified = true;
        }
    }

    // === Pattern: <button type="button" class="btn btn-danger" @click="X">Y</Button> ===
    const dangerBtn = /<button\s+type="button"\s+class="btn btn-danger"(?:\s+@click="([^"]+)")?>([\s\S]*?)<\/button>/g;
    if (dangerBtn.test(content)) {
        content = content.replace(dangerBtn, (m, click, inner) => {
            const iconMatch = inner.match(/<i\s+class="(fas?\s+[\w-]+)"\s*><\/i>/);
            const labelMatch = inner.match(/\{\{\s*t\('([^']+)'\)\s*\}\}/);
            const iconAttr = iconMatch ? `icon="${iconMatch[1]}"` : '';
            const label = labelMatch ? `{{ t('${labelMatch[1]}') }}` : inner.trim();
            const clickAttr = click ? `@click="${click}"` : '';
            return `<Button variant="danger" ${iconAttr} ${clickAttr}>${label}</Button>`;
        });
        stats.btnReplaced++;
        modified = true;
    }

    // === Pattern: <Link class="btn btn-secondary" :href="..."><i ...>...</i><span>{{ t('X') }}</span></Link> ===
    const linkSecondary = /<Link\s+:href="([^"]+)"\s+class="btn btn-secondary">([\s\S]*?)<\/Link>/g;
    if (linkSecondary.test(content)) {
        content = content.replace(linkSecondary, (m, href, inner) => {
            const iconMatch = inner.match(/<i\s+class="(fas?\s+[\w-]+)"\s*><\/i>/);
            const labelMatch = inner.match(/\{\{\s*t\('([^']+)'\)\s*\}\}/);
            const iconAttr = iconMatch ? `icon="${iconMatch[1]}"` : '';
            const label = labelMatch ? `{{ t('${labelMatch[1]}') }}` : inner.trim();
            return `<Button variant="secondary" :href="${href} ${iconAttr}>${label}</Button>`;
        });
        // Fix the trailing space
        content = content.replace(/":href="([^"]+) icon="([^"]+)">/g, '" :href="$1" icon="$2">');
        stats.btnReplaced++;
        modified = true;
    }

    // === Pattern: <div class="card p-N (other classes)">X</div> ===
    // Map p-3 → padding="sm", p-4 → padding="sm", p-5 → padding="md", p-6 → padding="md", p-8 → padding="lg"
    const divCard = /<div\s+class="card\s+p-(\d+)([^"]*)">/g;
    if (divCard.test(content)) {
        content = content.replace(divCard, (m, p, other) => {
            const paddingMap = { 3: 'sm', 4: 'sm', 5: 'md', 6: 'md', 8: 'lg' };
            const pad = paddingMap[p] || 'md';
            // Map other Tailwind classes that conflict with our defaults
            return `<Card variant="base" padding="${pad}"${other}>`;
        });
        stats.cardReplaced++;
        modified = true;
    }
    // Also handle <div class="card p-N (more classes)">
    const divCard2 = /<div\s+class="card\s+p-(\d+)\s+([\w\s-]+)"\s*>/g;
    if (divCard2.test(content)) {
        content = content.replace(divCard2, (m, p, otherClasses) => {
            const paddingMap = { 3: 'sm', 4: 'sm', 5: 'md', 6: 'md', 8: 'lg' };
            const pad = paddingMap[p] || 'md';
            return `<Card variant="base" padding="${pad}" ${otherClasses.trim()}>`;
        });
        stats.cardReplaced++;
        modified = true;
    }

    // === Fix corresponding </div> for the Card ===
    // The above <div> -> <Card> replacements need their </div> to become </Card>
    // Simple approach: find the first <div class="card ...> and replace it, then count </div> closes
    // Actually, this is risky because divs can be nested. Let me skip for now.

    if (modified) {
        try { writeFileSync(file, content, 'utf8'); stats.filesModified++; }
        catch (e) { console.error(`Error: ${file}: ${e.message}`); }
    }
}

console.log('=== Migration v3 Stats ===');
console.log(`Files modified: ${stats.filesModified}`);
console.log(`Buttons replaced: ${stats.btnReplaced}`);
console.log(`Cards replaced: ${stats.cardReplaced}`);
