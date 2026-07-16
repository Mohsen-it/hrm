#!/usr/bin/env node
// Recovery v4: filter out corruption lines (line-by-line approach)
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
let failed = [];

for (const mod of modules) {
    for (const type of pageTypes) {
        const file = join(basePath, mod, type);
        let content;
        try {
            content = readFileSync(file, 'utf8');
        } catch (e) {
            continue;
        }

        const lines = content.split('\n');
        const corruptionCount = lines.filter(l => l === corruptionLine).length;

        if (corruptionCount < 10) continue;

        // Filter out corruption lines
        const filtered = lines.filter(l => l !== corruptionLine);

        // Each remaining line is either:
        // - A real line from the file (which we want to keep)
        // - Part of the original content (e.g., "<", "s", "c", etc.)
        // The corruption lines had newlines after them, so the original content was one char per line.
        // We need to join consecutive single-char lines back together.

        const joined = [];
        let buffer = '';
        for (const line of filtered) {
            if (line.length === 0) {
                // Empty line: flush buffer
                if (buffer) {
                    joined.push(buffer);
                    buffer = '';
                }
                joined.push('');
            } else if (line.length === 1 || /^[\s\(\)\{\}\[\];,]$/.test(line)) {
                // Single char or punctuation: part of original content
                buffer += line;
            } else {
                // Multi-char line: real content line
                if (buffer) {
                    joined.push(buffer);
                    buffer = '';
                }
                joined.push(line);
            }
        }
        if (buffer) joined.push(buffer);

        // Reconstruct newlines based on patterns
        let result = joined.join('\n');

        // Add newlines after ";"
        result = result.replace(/;(?=\S)/g, ';\n');
        // Add newlines before "const ", "let ", "function ", "return "
        result = result.replace(/(\s)(const )/g, '\n$2');
        result = result.replace(/(\s)(let )/g, '\n$2');
        result = result.replace(/(\s)(function )/g, '\n$2');
        result = result.replace(/(\s)(return )/g, '\n$2');
        // Add newlines around <script setup> and </script>
        result = result.replace(/(<script setup>)/, '$1\n');
        result = result.replace(/(<\/script>)/, '\n$1\n');
        // Add newlines around <template> and </template>
        result = result.replace(/(<template>)/, '\n$1\n');
        result = result.replace(/(<\/template>)/, '\n$1\n');
        // Add newline after each closing tag
        result = result.replace(/(<\/[A-Za-z][A-Za-z0-9-]*>)/g, '$1\n');
        // Collapse 3+ newlines
        result = result.replace(/\n{3,}/g, '\n\n');

        result = result.trim() + '\n';

        try {
            writeFileSync(file, result, 'utf8');
            console.log(`Recovered: ${mod}/${type} (was ${content.length} chars, now ${result.length} chars)`);
            recovered++;
        } catch (e) {
            failed.push(file);
        }
    }
}

console.log(`\n=== Recovered ${recovered} files ===`);
if (failed.length > 0) {
    console.log('Failed:');
    failed.forEach(f => console.log(`  ${f}`));
}
