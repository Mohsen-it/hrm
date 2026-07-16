#!/usr/bin/env node
// Fix orphan </form> tags where the opening was already replaced with <Card ... as="form">
import { readFileSync, writeFileSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
const modules = [
    'Companies', 'Branches', 'Departments', 'Positions', 'Grades',
    'Shifts', 'Users', 'Holidays', 'Vacations\\Requests', 'Zones',
    'FingerprintDevices', 'Attendance\\Sessions',
];
const pageTypes = ['Create.vue', 'Edit.vue', 'Show.vue'];

let fixed = 0;
const errors = [];

for (const mod of modules) {
    for (const type of pageTypes) {
        const file = join(basePath, mod, type);
        let content;
        try { content = readFileSync(file, 'utf8'); }
        catch (e) { continue; }

        // Only fix if the file has <Card ... as="form">
        if (!/<Card[^>]*as="form"/.test(content)) continue;

        // Count opens and closes
        const opens = (content.match(/<Card[^>]*as="form"/g) || []).length;
        const formCloses = (content.match(/<\/form>/g) || []).length;
        const cardCloses = (content.match(/<\/Card>/g) || []).length;

        if (opens > formCloses) continue; // nothing to fix

        // Replace the first `</form>` (after a Card as="form" open) with `</Card>`
        // Use a simple approach: find the open, then the matching close
        const openRegex = /<Card[^>]*as="form"[^>]*>/g;
        let m;
        let replacements = [];
        while ((m = openRegex.exec(content)) !== null) {
            // Find next </form> after this open
            const afterOpen = content.indexOf('</form>', m.index);
            if (afterOpen > -1) {
                replacements.push({ from: afterOpen, to: afterOpen + 7 });
            }
        }

        // Apply replacements in reverse to preserve indices
        replacements.reverse();
        for (const r of replacements) {
            content = content.slice(0, r.from) + '</Card>' + content.slice(r.to);
        }

        if (replacements.length > 0) {
            try {
                writeFileSync(file, content, 'utf8');
                fixed += replacements.length;
                console.log(`Fixed ${replacements.length} </form> in ${mod}/${type}`);
            } catch (e) {
                errors.push(file);
            }
        }
    }
}

console.log(`\nFixed ${fixed} </form> tags`);
if (errors.length > 0) {
    console.log('Errors:');
    errors.forEach(e => console.log(`  ${e}`));
}
