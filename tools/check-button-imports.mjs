import { readFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';

function walk(d, f = []) {
    for (const e of readdirSync(d)) {
        const p = join(d, e);
        if (statSync(p).isDirectory()) walk(p, f);
        else if (e.endsWith('.vue')) f.push(p);
    }
    return f;
}

const missing = [];

for (const file of walk(basePath)) {
    const c = readFileSync(file, 'utf8');
    const usesButton = /<Button[\s>]/.test(c);
    const importsButton = /import Button from/.test(c);
    
    if (usesButton && !importsButton) {
        const rel = file.replace('D:\\hrm_alepair\\resources\\js\\Pages\\', '');
        missing.push(rel);
    }
}

if (missing.length === 0) {
    console.log('All files that use <Button> have the import.');
} else {
    console.log('Files missing Button import:');
    missing.forEach(f => console.log('  ' + f));
}
