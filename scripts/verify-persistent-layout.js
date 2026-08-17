#!/usr/bin/env node
/**
 * Post-migration verification for the persistent-layout refactor.
 *
 * Checks every page under resources/js/Pages:
 *   1. No <AppLayout ...> wrapper remains in templates.
 *   2. Every page that imports AppLayout defines `layout: AppLayout`.
 *   3. Exactly one plain <script> block and one <script setup> block,
 *      with the layout export inside the plain block.
 *   4. Every usePageTitle(...) call sits inside <script setup>, and every
 *      identifier it references is defined in that block.
 *   5. <template> / </template> tags are balanced.
 *
 * Usage: node scripts/verify-persistent-layout.js
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PAGES_DIR = path.resolve(__dirname, '..', 'resources', 'js', 'Pages');

const KEYWORDS = new Set([
    'true', 'false', 'null', 'undefined', 'of', 'in', 'new', 'typeof',
    'instanceof', 'return', 'if', 'else', 'this', 'or', 'and', 'not',
    'as', 'from', 'import', 'export', 'async', 'await',
]);

function walk(dir) {
    const out = [];
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const f = path.join(dir, e.name);
        if (e.isDirectory()) out.push(...walk(f));
        else if (e.name.endsWith('.vue')) out.push(f);
    }
    return out;
}

const issues = [];
let wrapperPages = 0;
let titleCalls = 0;

for (const file of walk(PAGES_DIR)) {
    const rel = path.relative(path.resolve(__dirname, '..'), file);
    const src = fs.readFileSync(file, 'utf8');

    // 1. No wrapper tags left anywhere.
    if (/<AppLayout|<\/AppLayout>/.test(src)) {
        issues.push(`${rel}: <AppLayout> wrapper still present`);
    }

    const importsLayout = /import\s+AppLayout\s+from\s+['"]@\/[Ll]ayouts\/AppLayout\.vue['"]/.test(src);
    const hasLayoutExport = /export\s+default\s*\{[\s\S]*layout\s*:\s*AppLayout/.test(src);
    if (importsLayout) {
        wrapperPages++;
        // 2. Every importer must define the persistent layout.
        if (!hasLayoutExport) {
            issues.push(`${rel}: imports AppLayout but no layout: AppLayout export`);
        }
    } else if (hasLayoutExport) {
        issues.push(`${rel}: layout export present without AppLayout import`);
    }

    // 3. Block structure: exactly one plain <script> and one <script setup>
    //    (only enforced for pages that were migrated to the persistent layout).
    const plainScripts = (src.match(/<script(?![^>]*setup)[^>]*>/g) || []).length;
    const setupScripts = (src.match(/<script setup>/g) || []).length;
    if (importsLayout) {
        if (plainScripts !== 1) issues.push(`${rel}: expected 1 plain <script>, found ${plainScripts}`);
        if (setupScripts !== 1) issues.push(`${rel}: expected 1 <script setup>, found ${setupScripts}`);
    }

    if (hasLayoutExport) {
        const plainStart = src.indexOf('<script>');
        const plainEnd = src.indexOf('</script>', plainStart);
        const plainBlock = src.slice(plainStart, plainEnd);
        if (!/layout\s*:\s*AppLayout/.test(plainBlock)) {
            issues.push(`${rel}: layout export not inside the plain <script> block`);
        }
    }

    // 4. usePageTitle calls inside <script setup> with valid identifiers.
    const setupStart = src.indexOf('<script setup>');
    const setupEnd = src.indexOf('</script>', setupStart);
    const setup = setupStart !== -1 && setupEnd !== -1 ? src.slice(setupStart, setupEnd) : '';

    // Find every `usePageTitle(<expr>);` call, scanning to the matching
    // closing paren so template literals containing `)` don't truncate.
    const STRIP_STRINGS =
        /'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*"|`(?:[^`\\]|\\.)*`/g;
    const searchFrom = (start) => {
        let i = src.indexOf('usePageTitle(', start);
        if (i === -1) return null;
        let depth = 0;
        let j = i + 'usePageTitle('.length;
        for (; j < src.length; j++) {
            if (src[j] === '(') depth++;
            else if (src[j] === ')') {
                if (depth === 0) break;
                depth--;
            }
        }
        return { start: i, end: j, expr: src.slice(i + 'usePageTitle('.length, j) };
    };
    let from = 0;
    while (true) {
        const call = searchFrom(from);
        if (!call) break;
        from = call.end + 1;
        titleCalls++;
        if (call.start < setupStart || call.start > setupEnd) {
            issues.push(`${rel}: usePageTitle call is outside <script setup>`);
            continue;
        }
        const cleaned = call.expr.replace(STRIP_STRINGS, ' ');
        const idents = new Set();
        cleaned.replace(/[A-Za-z_$][\w$]*/g, (id) => idents.add(id));
        for (const id of idents) {
            if (KEYWORDS.has(id) || id === 't') continue;
            if (!new RegExp('\\b' + id.replace(/[$]/g, '\\$&') + '\\b').test(setup)) {
                issues.push(`${rel}: usePageTitle references '${id}' not found in <script setup>`);
            }
        }
    }

    // 5. Template tag balance (named slots like <template #x> count too).
    const openTags = (src.match(/<template\b/g) || []).length;
    const closeTags = (src.match(/<\/template>/g) || []).length;
    if (openTags !== closeTags) {
        issues.push(`${rel}: template tags unbalanced (${openTags} open / ${closeTags} close)`);
    }
}

console.log(`Pages using AppLayout: ${wrapperPages}`);
console.log(`usePageTitle calls: ${titleCalls}`);
console.log(issues.length ? `ISSUES (${issues.length}):\n  ` + issues.join('\n  ') : 'No issues found. ✅');
process.exitCode = issues.length ? 1 : 0;
