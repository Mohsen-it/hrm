#!/usr/bin/env node
// Recovery v5: correct approach - strip corruption from end of each line
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
        try {
            content = readFileSync(file, 'utf8');
        } catch (e) { continue; }

        // Count lines that END WITH the corruption (these are corrupted)
        const lines = content.split('\n');
        const corruptedCount = lines.filter(l => l.endsWith(corruptionLine)).length;
        if (corruptedCount < 10) continue;

        // Strip the corruption from end of each line
        const stripped = lines.map(l =>
            l.endsWith(corruptionLine)
                ? l.slice(0, -corruptionLine.length)
                : l
        );

        // Join back
        let result = stripped.join('\n');

        // Reconstruct newlines based on patterns
        result = result.replace(/;(?=\S)/g, ';\n');
        result = result.replace(/(\s)(const )/g, '\n$2');
        result = result.replace(/(\s)(let )/g, '\n$2');
        result = result.replace(/(\s)(function )/g, '\n$2');
        result = result.replace(/(\s)(return )/g, '\n$2');
        result = result.replace(/(<script setup>)/, '$1\n');
        result = result.replace(/(<\/script>)/, '\n$1\n');
        result = result.replace(/(<template>)/, '\n$1\n');
        result = result.replace(/(<\/template>)/, '\n$1\n');
        result = result.replace(/(<\/[A-Za-z][A-Za-z0-9-]*>)/g, '$1\n');
        result = result.replace(/\n{3,}/g, '\n\n');

        result = result.trim() + '\n';

        writeFileSync(file, result, 'utf8');
        console.log(`Recovered: ${mod}/${type} (was ${content.length}, now ${result.length})`);
        recovered++;
    }
}

console.log(`\n=== Recovered ${recovered} files ===`);
