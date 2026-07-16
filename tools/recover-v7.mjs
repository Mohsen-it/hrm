#!/usr/bin/env node
// Recovery v7: file is already stripped, just merge single-char lines
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

for (const mod of modules) {
    for (const type of pageTypes) {
        const file = join(basePath, mod, type);
        let content;
        try { content = readFileSync(file, 'utf8'); } catch (e) { continue; }

        const lines = content.split('\n');
        // If the file is mostly single-char lines, it's still in stripped form
        const singleCharCount = lines.filter(l => l.length === 1).length;
        if (singleCharCount < 100) continue;

        // Merge consecutive single-char lines into one line.
        // Treat empty lines as real newlines.
        const output = [];
        let buffer = '';
        for (const line of lines) {
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
