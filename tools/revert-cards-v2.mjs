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

    // Convert <Card variant="base" padding="X" extraClasses> back to <div class="card p-N extraClasses">
    // Only when there's NO as="form" (form-as patterns are correct)
    c = c.replace(/<Card\s+variant="base"\s+padding="(\w+)"((?:\s+[\w-]+(?:="[^"]*")?)*?)\s*>/g, (m, p, extras) => {
        if (m.includes('as="form"') || m.includes('header=') || m.includes('@')) {
            return m; // skip - these are correct
        }
        const pValue = paddingMap[p] || 6;
        const extrasClean = extras.trim();
        const classAttr = extrasClean ? `card p-${pValue} ${extrasClean}` : `card p-${pValue}`;
        return `<div class="${classAttr}">`;
    });

    if (c !== before) {
        writeFileSync(file, c, 'utf8');
        reverted++;
    }
}
console.log('Reverted', reverted, 'files');
