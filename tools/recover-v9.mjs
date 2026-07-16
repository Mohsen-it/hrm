#!/usr/bin/env node
// Recovery v9: handle \r\n and \r line endings
import { readFileSync, writeFileSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
const modules = [
    'Companies', 'Branches', 'Departments', 'Positions', 'Grades',
    'Shifts', 'Users', 'Holidays', 'Vacations\\Requests', 'Zones',
    'FingerprintDevices', 'Attendance\\Sessions',
];
const pageTypes = ['Create.vue', 'Edit.vue', 'Show.vue'];

let recovered = 0;
let skipped = 0;

for (const mod of modules) {
    for (const type of pageTypes) {
        const file = join(basePath, mod, type);
        let content;
        try { content = readFileSync(file, 'utf8'); } catch (e) { continue; }

        // Normalize line endings: \r\n -> \n, then split on \r or \n
        const normalized = content.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const lines = normalized.split('\n');

        const singleCharCount = lines.filter(l => l.length === 1).length;
        const emptyCount = lines.filter(l => l === '').length;
        const corruptionCount = lines.filter(l => l.endsWith("import Card from '@/Components/ui/Card.vue';")).length;

        if (corruptionCount === 0 && singleCharCount < 100) {
            skipped++;
            continue;
        }

        // Strip corruption
        const stripped = lines.map(l =>
            l.endsWith("import Card from '@/Components/ui/Card.vue';") ? l.slice(0, -45) : l
        );

        // Merge consecutive single-char lines
        const output = [];
        let buffer = '';
        for (const line of stripped) {
            if (line === '') {
                if (buffer) { output.push(buffer); buffer = ''; }
                output.push('');
            } else if (line.length === 1) {
                buffer += line;
            } else {
                if (buffer) { output.push(buffer); buffer = ''; }
                output.push(line);
            }
        }
        if (buffer) output.push(buffer);

        let result = output.join('\n');

        // Reconstruct newlines
        result = result.replace(/;(?=\S)/g, ';\n');
        result = result.replace(/(\s)(const )/g, '\n$2');
        result = result.replace(/(\s)(let )/g, '\n$2');
        result = result.replace(/(\s)(function )/g, '\n$2');
        result = result.replace(/(\s)(return )/g, '\n$2');
        result = result.replace(/(<script setup>)/g, '\n$1\n');
        result = result.replace(/(<\/script>)/g, '\n$1\n');
        result = result.replace(/(<template>)/g, '\n$1\n');
        result = result.replace(/(<\/template>)/g, '\n$1\n');
        result = result.replace(/(<\/[A-Za-z][A-Za-z0-9-]*>)/g, '$1\n');
        result = result.replace(/\n{3,}/g, '\n\n');

        result = result.trim() + '\n';

        writeFileSync(file, result, 'utf8');
        console.log(`Recovered: ${mod}/${type} (corruption=${corruptionCount}, singleChar=${singleCharCount}, now ${result.length} chars)`);
        recovered++;
    }
}

console.log(`\n=== Recovered ${recovered} files, skipped ${skipped} ===`);
