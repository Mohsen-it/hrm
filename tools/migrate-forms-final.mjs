#!/usr/bin/env node
// Migrate form pages (Create/Edit/Show) to use Button, Card, FormCheckbox components
// Also replace hardcoded var(--color-*) classes with mistral Tailwind utilities

import { readFileSync, writeFileSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
const modules = [
    'Companies', 'Branches', 'Departments', 'Positions', 'Grades',
    'Shifts', 'Users', 'Holidays', 'Vacations\\Requests', 'Zones',
    'FingerprintDevices', 'Attendance\\Sessions',
];
const pageTypes = ['Create.vue', 'Edit.vue', 'Show.vue'];

let stats = {
    filesProcessed: 0,
    filesModified: 0,
    buttonsReplaced: 0,
    cardsReplaced: 0,
    checkboxesReplaced: 0,
    colorTokensReplaced: 0,
    errors: [],
};

for (const mod of modules) {
    for (const type of pageTypes) {
        const file = join(basePath, mod, type);
        stats.filesProcessed++;

        let content;
        try { content = readFileSync(file, 'utf8'); }
        catch (e) { continue; }

        let modified = false;
        const before = content;

        // === Add imports if needed ===
        if (/class="btn /.test(content) || /class="card /.test(content) || /type="submit" class="btn/.test(content)) {
            // Add Button import if missing
            if (!/import Button from '@\/Components\/ui\/Button\.vue'/.test(content)) {
                content = content.replace(
                    /(import (?:PageHeader|FormInput|FormTextarea|FormSelect) from '@\/Components\/ui\/[^']+';\n)/,
                    '$1import Button from \'@/Components/ui/Button.vue\';\n'
                );
                modified = true;
            }
            // Add Card import if missing
            if (!/import Card from '@\/Components\/ui\/Card\.vue'/.test(content)) {
                content = content.replace(
                    /(import Button from '@\/Components\/ui\/Button\.vue';\n)/,
                    '$1import Card from \'@/Components/ui/Card.vue\';\n'
                );
                modified = true;
            }
        }

        // === Replace form wrapper ===
        // <form class="card p-6" @submit.prevent="X">  =>  <Card variant="base" padding="md" as="form" @submit.prevent="X">
        // <form class="card p-5 lg:col-span-3" @submit.prevent="X">  (Settings has different padding)
        // Handle any p-N padding
        const formCardPattern = /<form\s+class="card\s+p-\d+(?:\s+\w+[-\w:]+(?:\s+[\w-]+)*)*"\s+@submit\.prevent="(\w+)">/g;
        if (formCardPattern.test(content)) {
            content = content.replace(formCardPattern, '<Card variant="base" padding="md" as="form" @submit.prevent="$1">');
            stats.cardsReplaced++;
            modified = true;
        }
        // Also handle form without explicit @submit.prevent (less common)
        const formCardPattern2 = /<form\s+class="card\s+p-\d+(?:\s+\w+[-\w:]+(?:\s+[\w-]+)*)*"\s+@submit\.prevent="(\w+)\.(\w+)\.(\w+)">/g;
        if (formCardPattern2.test(content)) {
            content = content.replace(formCardPattern2, '<Card variant="base" padding="md" as="form" @submit.prevent="$1.$2.$3">');
            stats.cardsReplaced++;
            modified = true;
        }

        // === Replace back button in PageHeader ===
        // <Link :href="route('X.index')" class="btn btn-secondary">
        //     <i class="fas fa-arrow-right rtl-flip"></i>
        //     <span>{{ t('common.back') }}</span>
        // </Link>
        const backBtnPattern = /<Link\s+:href="route\('([\w.]+)\.index'\)"\s+class="btn btn-secondary">\s*<i\s+class="fas fa-arrow-right rtl-flip"><\/i>\s*<span>\s*\{\{\s*t\('common\.back'\)\s*\}\}\s*<\/span>\s*<\/Link>/g;
        if (backBtnPattern.test(content)) {
            content = content.replace(backBtnPattern, '<Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route(\'$1.index\')">{{ t(\'common.back\') }}</Button>');
            stats.buttonsReplaced++;
            modified = true;
        }

        // === Replace save/update button ===
        // <button type="submit" class="btn btn-primary" :disabled="processing">
        //     <i v-if="processing" class="fas fa-spinner fa-spin"></i>
        //     <i v-else class="fas fa-save"></i>
        //     <span>{{ t('common.save|update') }}</span>
        // </Button>
        const saveBtnPattern = /<button\s+type="submit"\s+class="btn btn-primary"\s+:disabled="processing">\s*<i\s+v-if="processing"\s+class="fas fa-spinner fa-spin"><\/i>\s*<i\s+v-else\s+class="fas fa-save"><\/i>\s*<span>\s*\{\{\s*t\('common\.(save|update)'\)\s*\}\}\s*<\/span>\s*<\/button>/g;
        if (saveBtnPattern.test(content)) {
            content = content.replace(saveBtnPattern, '<Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">{{ t(\'common.$1\') }}</Button>');
            stats.buttonsReplaced++;
            modified = true;
        }

        // === Replace cancel button (Link) ===
        // <Link :href="route('X.index')" class="btn btn-secondary">{{ t('common.cancel') }}</Link>
        const cancelBtnPattern = /<Link\s+:href="route\('([\w.]+)\.index'\)"\s+class="btn btn-secondary">\s*\{\{\s*t\('common\.cancel'\)\s*\}\}\s*<\/Link>/g;
        if (cancelBtnPattern.test(content)) {
            content = content.replace(cancelBtnPattern, '<Button variant="secondary" :href="route(\'$1.index\')">{{ t(\'common.cancel\') }}</Button>');
            stats.buttonsReplaced++;
            modified = true;
        }

        // === Replace Edit button in Show pages ===
        // <Link :href="route('X.edit', id)" class="btn btn-primary">
        //     <i class="fas fa-edit"></i>
        //     <span>{{ t('common.edit') }}</span>
        // </Link>
        const editBtnPattern = /<Link\s+:href="route\('([\w.]+)\.edit',\s*([^)]+)\)"\s+class="btn btn-primary">\s*<i\s+class="fas fa-edit"><\/i>\s*<span>\s*\{\{\s*t\('common\.edit'\)\s*\}\}\s*<\/span>\s*<\/Link>/g;
        if (editBtnPattern.test(content)) {
            content = content.replace(editBtnPattern, '<Button variant="primary" icon="fas fa-edit" :href="route(\'$1.edit\', $2)">{{ t(\'common.edit\') }}</Button>');
            stats.buttonsReplaced++;
            modified = true;
        }

        // === Replace raw checkbox in form pages ===
        // <input id="X" v-model="form.Y" type="checkbox" class="w-4 h-4" />
        // <label for="X" class="text-[14px] text-[var(--color-ink)]">
        //     <span>{{ t('Z') }}</span>
        // </label>
        const checkboxPattern = /<input\s+id="([^"]+)"\s+v-model="form\.([^"]+)"\s+type="checkbox"\s+class="w-4 h-4"\s*\/>(?:\s*<label\s+for="[^"]+"\s+class="[^"]*">)?\s*(?:<span>)?\s*\{\{\s*t\('([^']+)'\)\s*\}\}\s*(?:<\/span>)?\s*<\/label>/g;
        if (checkboxPattern.test(content)) {
            content = content.replace(checkboxPattern, '<FormCheckbox v-model="form.$2" :label="t(\'$3\')" />');
            stats.checkboxesReplaced++;
            modified = true;
        }
        // Simpler checkbox pattern (for those with id but different format)
        const checkboxPattern2 = /<input\s+id="([^"]+)"\s+v-model="form\.([^"]+)"\s+type="checkbox"\s+class="w-4 h-4"\s*\/>/g;
        if (checkboxPattern2.test(content)) {
            content = content.replace(checkboxPattern2, '<FormCheckbox v-model="form.$2" />');
            stats.checkboxesReplaced++;
            modified = true;
        }

        // === Replace text-[var(--color-ink-*)] with mistral utilities ===
        const colorReplacements = [
            [/text-\[var\(--color-ink\)\]/g, 'text-mistral-ink'],
            [/text-\[var\(--color-ink-muted\)\]/g, 'text-mistral-steel'],
            [/text-\[var\(--color-ink-mute\)\]/g, 'text-mistral-steel'],
            [/text-\[var\(--color-ink-faint\)\]/g, 'text-mistral-stone'],
            [/text-\[var\(--color-ink-subtle\)\]/g, 'text-mistral-stone'],
            [/text-\[var\(--color-ink-disabled\)\]/g, 'text-mistral-muted'],
            [/text-\[var\(--color-primary\)\]/g, 'text-mistral-primary'],
            [/text-\[var\(--color-info\)\]/g, 'text-mistral-info'],
            [/text-\[var\(--color-danger\)\]/g, 'text-mistral-danger'],
            [/text-\[var\(--color-warning\)\]/g, 'text-mistral-warning'],
            [/text-\[var\(--color-success\)\]/g, 'text-mistral-success'],
            [/text-\[11px\]\s+text-\[var\(--color-danger\)\]/g, 'text-[11px] text-mistral-danger'],
            [/text-\[11px\]\s+text-\[var\(--color-ink-subtle\)\]/g, 'text-[11px] text-mistral-stone'],
            [/bg-\[var\(--color-surface-1\)\]/g, 'bg-mistral-surface'],
            [/bg-\[var\(--color-surface-2\)\]/g, 'bg-mistral-surface'],
            [/bg-\[var\(--color-surface-3\)\]/g, 'bg-mistral-hairline-soft'],
            [/bg-\[var\(--color-canvas-soft\)\]/g, 'bg-mistral-surface'],
            [/bg-\[var\(--color-primary-light\)\]/g, 'bg-mistral-cream'],
            [/bg-\[var\(--color-teal-deep\)\]/g, 'bg-mistral-primary'],
            [/border-\[var\(--color-hairline\)\]/g, 'border-mistral-hairline-soft'],
            [/border-\[var\(--color-hairline-dark\)\]/g, 'border-mistral-ink'],
        ];
        for (const [pattern, replacement] of colorReplacements) {
            const matches = content.match(pattern);
            if (matches) {
                content = content.replace(pattern, replacement);
                stats.colorTokensReplaced += matches.length;
                modified = true;
            }
        }

        // === Save if modified ===
        if (modified) {
            try {
                writeFileSync(file, content, 'utf8');
                stats.filesModified++;
            } catch (e) {
                stats.errors.push(file);
            }
        }
    }
}

console.log('=== Migration Stats ===');
console.log(`Files processed: ${stats.filesProcessed}`);
console.log(`Files modified:  ${stats.filesModified}`);
console.log(`Buttons replaced: ${stats.buttonsReplaced}`);
console.log(`Cards replaced:   ${stats.cardsReplaced}`);
console.log(`Checkboxes replaced: ${stats.checkboxesReplaced}`);
console.log(`Color tokens replaced: ${stats.colorTokensReplaced}`);
if (stats.errors.length > 0) {
    console.log('Errors:');
    stats.errors.forEach(e => console.log(`  ${e}`));
}
