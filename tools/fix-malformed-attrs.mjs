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
    const before = c;

    // Fix: class="X class="Y"  → class="X Y"
    // This pattern is from v2 script's `class="${iconAttr}">` ending in extra space
    c = c.replace(/class="([^"]*)" icon="/g, (m, classes) => `class="${classes.trim()}" icon="`);

    // Fix: class="X" icon="Y"> where there's an extra space
    c = c.replace(/" icon="/g, '" icon="');

    // Fix malformed: class="X class="Y">
    c = c.replace(/class="([^"]+)\s+class="([^"]+)"/g, 'class="$1 $2"');

    if (c !== before) {
        writeFileSync(file, c, 'utf8');
        fixed++;
    }
}
console.log('Fixed', fixed, 'files with malformed attributes');
