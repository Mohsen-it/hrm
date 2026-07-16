#!/usr/bin/env node
// Recovery v3: extract original characters by removing the import pattern
import { readFileSync, writeFileSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
const modules = [
    'Companies', 'Branches', 'Departments', 'Positions', 'Grades',
    'Shifts', 'Users', 'Holidays', 'Vacations\\Requests', 'Zones',
    'FingerprintDevices', 'Attendance\\Sessions',
];
const pageTypes = ['Create.vue', 'Edit.vue', 'Show.vue'];

const corruption = "import Card from '@/Components/ui/Card.vue';\n";

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

        const importCount = (content.match(/import Card from '@\/Components\/ui\/Card\.vue';/g) || []).length;
        if (importCount < 10) continue;

        // Step 1: Remove all corruption lines
        let cleaned = content.split(corruption).join('');

        // Step 2: Reconstruct newlines
        // Add newline after each ";" (statement end)
        cleaned = cleaned.replace(/;(?=[^\n])/g, ';\n');
        // Add newline before "const ", "let ", "function ", "return " in script
        cleaned = cleaned.replace(/(\s)(const )/g, '\n$2');
        cleaned = cleaned.replace(/(\s)(let )/g, '\n$2');
        cleaned = cleaned.replace(/(\s)(function )/g, '\n$2');
        cleaned = cleaned.replace(/(\s)(return )/g, '\n$2');
        // Add newline around <script setup> and </script>
        cleaned = cleaned.replace(/(<script setup>)/, '$1\n');
        cleaned = cleaned.replace(/(<\/script>)/, '\n$1\n');
        // Add newline around <template> and </template>
        cleaned = cleaned.replace(/(<template>)/, '\n$1\n');
        cleaned = cleaned.replace(/(<\/template>)/, '\n$1\n');
        // Add newline after each closing tag
        cleaned = cleaned.replace(/(<\/[A-Za-z][A-Za-z0-9-]*>)/g, '$1\n');
        // Collapse 3+ consecutive newlines to 2
        cleaned = cleaned.replace(/\n{3,}/g, '\n\n');
        // Trim and ensure ending newline
        cleaned = cleaned.trim() + '\n';

        try {
            writeFileSync(file, cleaned, 'utf8');
            console.log(`Recovered: ${mod}/${type} (had ${importCount} imports, original ~${cleaned.length} chars)`);
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
