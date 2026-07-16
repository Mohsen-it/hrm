import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let reverted = 0;
const paddingMap = { sm: 4, md: 6, lg: 8, xl: 12 };

function walk(d, f = []) {
    for (const e of readdirSync(d)) {
        const p = join(d, e);
        if (statSync(p).isDirectory()) walk(p, f);
        else if (e.endsWith('.vue')) f.push(p);
    }
    return f;
}

for (const file of walk(basePath)) {
    let c = readFileSync(file, 'utf8');
    const before = c;

    // Revert <Card variant="base" padding="X"> back to <div class="card p-N">
    c = c.replace(/<Card\s+variant="base"\s+padding="(\w+)"\s*>/g, (m, p) => {
        const pVal = paddingMap[p] || 6;
        return `<div class="card p-${pVal}">`;
    });

    if (c !== before) {
        writeFileSync(file, c, 'utf8');
        reverted++;
    }
}

console.log('Reverted', reverted, 'files');
