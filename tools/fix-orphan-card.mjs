import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let fixed = 0;

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
    const opens = (c.match(/<Card\s+/g) || []).length;
    const closes = (c.match(/<\/Card>/g) || []).length;
    if (closes > opens) {
        c = c.replace(/<\/Card>/g, '</div>');
        writeFileSync(file, c, 'utf8');
        fixed++;
    }
}
console.log('Fixed', fixed, 'files with orphan </Card>');
