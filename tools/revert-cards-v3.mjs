import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let reverted = 0;

function walk(d, f = []) {
    for (const e of readdirSync(d)) {
        const p = join(d, e);
        if (statSync(p).isDirectory()) walk(p, f);
        else if (e.endsWith('.vue')) f.push(p);
    }
    return f;
}

const paddingMap = { sm: 4, md: 6, lg: 8, xl: 12, none: 0 };

for (const file of walk(basePath)) {
    let c = readFileSync(file, 'utf8');
    const before = c;

    // Match <Card variant="base" padding="X" extra> and convert back to <div class="card p-N extra>
    // The extras are bare tokens like "lg:col-span-2" or class="..."
    const re = /<Card\s+variant="base"\s+padding="(\w+)"([^>]*)>/g;
    c = c.replace(re, (m, p, extras) => {
        // If it has as="form" or @click etc, keep as Card
        if (m.includes('as="form"') || m.includes('@')) return m;
        const pValue = paddingMap[p] || 6;
        const extrasTrim = extras.trim();
        let classAttr = `card p-${pValue}`;
        if (extrasTrim) {
            // Parse extras - they may be like 'lg:col-span-2' or 'class="mb-4"'
            // If it's already class="..." merge; otherwise append
            const classMatch = extrasTrim.match(/^class="([^"]*)"$/);
            if (classMatch) {
                classAttr += ' ' + classMatch[1];
            } else {
                classAttr += ' ' + extrasTrim;
            }
        }
        return `<div class="${classAttr}">`;
    });

    if (c !== before) {
        writeFileSync(file, c, 'utf8');
        reverted++;
    }
}
console.log('Reverted', reverted, 'files');
