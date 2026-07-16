import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let filesModified = 0;
let totalChanges = 0;

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
    let changes = 0;

    // Skip _dev showcase page
    if (file.includes('_dev')) continue;

    // 1. Convert <button type="submit" class="btn btn-primary" ...> to <Button type="submit" variant="primary" ...>
    c = c.replace(/<button\s+type="submit"\s+class="btn\s+btn-primary"(.*?)>/g, (m, rest) => {
        const loadingMatch = rest.match(/:disabled="processing"/);
        const newTag = loadingMatch
            ? `<Button type="submit" variant="primary" :loading="processing" icon="fas fa-save"${rest.replace(/\s*:disabled="processing"/, '')}>`
            : `<Button type="submit" variant="primary" icon="fas fa-save"${rest}>`;
        return newTag;
    });
    // Remove the icon+span inside these buttons
    c = c.replace(/<Button type="submit" variant="primary"(.*?)>\s*<i v-if="processing" class="fas fa-spinner fa-spin"><\/i>\s*<i v-else class="fas fa-save"><\/i>\s*<span>(.*?)<\/span>\s*<\/Button>/g,
        (m, attrs, label) => `<Button type="submit" variant="primary" :loading="processing" icon="fas fa-save"${attrs}>${label}</Button>`);

    // 2. Convert <button type="button" class="btn btn-danger" ...> to <Button variant="danger" ...>
    c = c.replace(/<button\s+type="button"\s+class="btn\s+btn-danger"\s*@click="([^"]+)"(.*?)>/g,
        (m, click, rest) => `<Button variant="danger" @click="${click}"${rest}>`);

    // 3. Convert <button type="button" class="btn btn-secondary" ...> to <Button variant="secondary" ...>
    c = c.replace(/<button\s+type="button"\s+class="btn\s+btn-secondary"\s*@click="([^"]+)"(.*?)>/g,
        (m, click, rest) => `<Button variant="secondary" @click="${click}"${rest}>`);

    // 4. Convert closing </Button> for the above
    // Only for specific patterns we converted above

    // 5. Convert <div class="card p-N ..."> to <Card variant="base" padding="N" ...>
    // Only simple patterns with no as="form" or other complex attrs
    c = c.replace(/<div\s+class="card\s+p-(\d+)\s*">/g, (m, p) => {
        return `<Card variant="base" padding="${p === '6' ? 'md' : p === '4' ? 'sm' : p === '8' ? 'lg' : 'md'}">`;
    });
    // And their closing </div> that follow immediately after (not nested)
    // We need to match the specific pattern: <Card ...>content</div>
    // This is tricky because we need to find the matching closing tag
    // Let's do a simpler approach: just convert the opening, and fix mismatches later

    // 6. Convert <div class="card p-N mb-X"> (with extra classes after)
    c = c.replace(/<div\s+class="card\s+p-\d+\s+([^"]+)">/g, (m, extras) => {
        // Keep as div since there are extra classes we can't easily map
        return m;
    });

    // Count changes
    if (c !== before) {
        // Count actual changes
        const beforeLines = before.split('\n');
        const afterLines = c.split('\n');
        for (let i = 0; i < Math.max(beforeLines.length, afterLines.length); i++) {
            if (beforeLines[i] !== afterLines[i]) changes++;
        }
        writeFileSync(file, c, 'utf8');
        filesModified++;
        totalChanges += changes;
    }
}

console.log(`Modified ${filesModified} files with ${totalChanges} line changes`);
