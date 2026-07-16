#!/usr/bin/env node
// Migration v2: catch remaining btn-* patterns
import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';

let stats = {
    buttonsReplaced: 0,
    filesModified: 0,
};

function getAllVueFiles(dir) {
    const files = [];
    for (const entry of readdirSync(dir)) {
        const fullPath = join(dir, entry);
        const stat = statSync(fullPath);
        if (stat.isDirectory()) {
            files.push(...getAllVueFiles(fullPath));
        } else if (entry.endsWith('.vue')) {
            files.push(fullPath);
        }
    }
    return files;
}

const files = getAllVueFiles(basePath);

for (const file of files) {
    let content;
    try { content = readFileSync(file, 'utf8'); } catch (e) { continue; }

    let modified = false;

    // === Ensure Button import is present ===
    if (/class="btn /.test(content) || /class="card /.test(content)) {
        if (!/import Button from '@\/Components\/ui\/Button\.vue'/.test(content)) {
            content = content.replace(
                /(import (?:PageHeader|FormInput|FormTextarea|FormSelect) from '@\/Components\/ui\/[^']+';\n)/,
                '$1import Button from \'@/Components/ui/Button.vue\';\n'
            );
            modified = true;
        }
    }

    // === Pattern: <Link ... class="btn btn-secondary">X</Link> where X can be anything ===
    // Specifically: back buttons without icons
    const backBtnNoIcon = /<Link\s+:href="route\('([\w.]+)\.index'\)"\s+class="btn btn-secondary">\s*([\s\S]*?)\s*<\/Link>/g;
    if (backBtnNoIcon.test(content)) {
        content = content.replace(backBtnNoIcon, (match, routeName, inner) => {
            // Try to extract icon and label
            const iconMatch = inner.match(/<i\s+class="(fas?\s+[\w-]+)"\s*><\/i>/);
            const labelMatch = inner.match(/\{\{\s*t\('([^']+)'\)\s*\}\}/);

            if (iconMatch && labelMatch) {
                return `<Button variant="secondary" icon="${iconMatch[1]}" :href="route('${routeName}.index')">{{ t('${labelMatch[1]}') }}</Button>`;
            } else if (iconMatch) {
                return `<Button variant="secondary" icon="${iconMatch[1]}" :href="route('${routeName}.index')">${inner.trim()}</Button>`;
            } else if (labelMatch) {
                return `<Button variant="secondary" :href="route('${routeName}.index')">{{ t('${labelMatch[1]}') }}</Button>`;
            } else {
                return `<Button variant="secondary" :href="route('${routeName}.index')">${inner.trim()}</Button>`;
            }
        });
        stats.buttonsReplaced++;
        modified = true;
    }

    // === Pattern: <Link ... class="btn btn-primary">X</Link> (Edit button) ===
    const editBtnNoIcon = /<Link\s+:href="route\('([\w.]+)\.edit',\s*([^)]+)\)"\s+class="btn btn-primary">\s*([\s\S]*?)\s*<\/Link>/g;
    if (editBtnNoIcon.test(content)) {
        content = content.replace(editBtnNoIcon, (match, routeName, param, inner) => {
            const iconMatch = inner.match(/<i\s+class="(fas?\s+[\w-]+)"\s*><\/i>/);
            const labelMatch = inner.match(/\{\{\s*t\('([^']+)'\)\s*\}\}/);

            if (iconMatch && labelMatch) {
                return `<Button variant="primary" icon="${iconMatch[1]}" :href="route('${routeName}.edit', ${param})">{{ t('${labelMatch[1]}') }}</Button>`;
            } else if (labelMatch) {
                return `<Button variant="primary" :href="route('${routeName}.edit', ${param})">{{ t('${labelMatch[1]}') }}</Button>`;
            } else {
                return `<Button variant="primary" :href="route('${routeName}.edit', ${param})">${inner.trim()}</Button>`;
            }
        });
        stats.buttonsReplaced++;
        modified = true;
    }

    // === Pattern: <button class="btn btn-primary w-full"> for login ===
    const loginBtn = /<button\s+type="submit"\s+class="btn btn-primary w-full"\s+:disabled="processing">([\s\S]*?)<\/button>/g;
    if (loginBtn.test(content)) {
        content = content.replace(loginBtn, (match, inner) => {
            const iconMatch = inner.match(/<i\s+class="(fas?\s+[\w-]+)"\s*><\/i>/);
            const labelMatch = inner.match(/\{\{\s*t\('([^']+)'\)\s*\}\}/);
            const iconAttr = iconMatch ? `icon="${iconMatch[1]}"` : '';
            const label = labelMatch ? `{{ t('${labelMatch[1]}') }}` : inner.trim();
            return `<Button type="submit" variant="primary" block :loading="processing" ${iconAttr}>${label}</Button>`;
        });
        stats.buttonsReplaced++;
        modified = true;
    }

    // === Pattern: <button class="btn btn-secondary" ...>X</Button> ===
    const secondaryBtn = /<button\s+type="button"\s+class="btn btn-secondary"\s+@click="(\w+)\(\)">([\s\S]*?)<\/button>/g;
    if (secondaryBtn.test(content)) {
        content = content.replace(secondaryBtn, (match, handler, inner) => {
            const iconMatch = inner.match(/<i\s+class="(fas?\s+[\w-]+)"\s*><\/i>/);
            const labelMatch = inner.match(/\{\{\s*t\('([^']+)'\)\s*\}\}/);
            const iconAttr = iconMatch ? `icon="${iconMatch[1]}"` : '';
            const label = labelMatch ? `{{ t('${labelMatch[1]}') }}` : inner.trim();
            return `<Button variant="secondary" @click="$1" ${iconAttr}>${label}</Button>`;
        });
        stats.buttonsReplaced++;
        modified = true;
    }

    if (modified) {
        try {
            writeFileSync(file, content, 'utf8');
            stats.filesModified++;
        } catch (e) {
            console.error(`Error in ${file}: ${e.message}`);
        }
    }
}

console.log('=== Migration v2 Stats ===');
console.log(`Files modified: ${stats.filesModified}`);
console.log(`Buttons replaced: ${stats.buttonsReplaced}`);
