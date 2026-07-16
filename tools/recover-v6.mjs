#!/usr/bin/env node
// Recovery v6: smart reconstruction
import { readFileSync, writeFileSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
const modules = [
    'Companies', 'Branches', 'Departments', 'Positions', 'Grades',
    'Shifts', 'Users', 'Holidays', 'Vacations\\Requests', 'Zones',
    'FingerprintDevices', 'Attendance\\Sessions',
];
const pageTypes = ['Create.vue', 'Edit.vue', 'Show.vue'];

const corruptionLine = "import Card from '@/Components/ui/Card.vue';";

let recovered = 0;

for (const mod of modules) {
    for (const type of pageTypes) {
        const file = join(basePath, mod, type);
        let content;
        try { content = readFileSync(file, 'utf8'); } catch (e) { continue; }

        const lines = content.split('\n');
        const corruptedCount = lines.filter(l => l.endsWith(corruptionLine)).length;
        if (corruptedCount < 10) continue;

        // Strip corruption from each line
        const stripped = lines.map(l =>
            l.endsWith(corruptionLine) ? l.slice(0, -corruptionLine.length) : l
        );

        // Now: most lines are single chars. Real newlines from original are preserved as EMPTY lines.
        // (Because: original `A\nB` became `Aimport Card;\nBimport Card;\n` → after strip: `A\nB` → split by \n: `A`, ``, `B`)
        // Wait, that's not right either. Let me think again.
        //
        // Original: `var(--color)\nimport X;\n`
        // After corruption: each char gets `import Card;\n` appended
        //   = `vimport Card;\naimport Card;\nrimport Card;\n(import Card;\n-import Card;\n-import Card;\ncimport Card;\noimport Card;\nlimport Card;\noimport Card;\nrimport Card;\n)\nimport Card;\nimport Card;\nXimport Card;\n import Card;\n'import Card;\nYimport Card;\n'import Card;\n;import Card;\n\nimport Card;\n`
        // Split by \n: [`vimport Card;`, `aimport Card;`, ..., `)import Card;`, ``, `import Card;`, `import Card;`, `Ximport Card;`, ..., `;import Card;`, ``, `iimport Card;`, ...]
        // After strip: [`v`, `a`, `r`, `(`, ..., `)`, ``, `i`, `m`, `p`, `o`, `r`, `t`, ` `, `X`, ..., `;`, ``, `i`, ...]
        //
        // So the empty string lines represent the original \n characters.
        // Multi-char lines that don't end with corruption are legitimate.

        // Strategy: 
        // - For each stripped line:
        //   - If empty: emit a newline
        //   - If multi-char: emit as-is
        //   - If single-char: accumulate in buffer
        // - Flush buffer at the end OR when we hit a multi-char/empty line

        const output = [];
        let buffer = '';
        for (const line of stripped) {
            if (line === '') {
                if (buffer) { output.push(buffer); buffer = ''; }
                output.push(''); // empty line = real newline
            } else if (line.length === 1) {
                buffer += line;
            } else {
                // multi-char line
                if (buffer) { output.push(buffer); buffer = ''; }
                output.push(line);
            }
        }
        if (buffer) output.push(buffer);

        let result = output.join('\n');

        // Reconstruct: add newlines after `;` if missing
        result = result.replace(/;(?=\S)/g, ';\n');
        // Add newlines before key Vue/JS keywords
        result = result.replace(/(\s)(const )/g, '\n$2');
        result = result.replace(/(\s)(let )/g, '\n$2');
        result = result.replace(/(\s)(function )/g, '\n$2');
        result = result.replace(/(\s)(return )/g, '\n$2');
        // Around script tags
        result = result.replace(/(<script setup>)/g, '\n$1\n');
        result = result.replace(/(<\/script>)/g, '\n$1\n');
        // Around template tags
        result = result.replace(/(<template>)/g, '\n$1\n');
        result = result.replace(/(<\/template>)/g, '\n$1\n');
        // After each closing tag
        result = result.replace(/(<\/[A-Za-z][A-Za-z0-9-]*>)/g, '$1\n');
        // Collapse 3+ newlines
        result = result.replace(/\n{3,}/g, '\n\n');

        result = result.trim() + '\n';

        writeFileSync(file, result, 'utf8');
        console.log(`Recovered: ${mod}/${type} (was ${content.length}, now ${result.length})`);
        recovered++;
    }
}

console.log(`\n=== Recovered ${recovered} files ===`);
