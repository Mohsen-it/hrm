import { readFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';

function walk(d, f = []) {
    for (const e of readdirSync(d)) {
        const p = join(d, e);
        if (statSync(p).isDirectory()) walk(p, f);
        else if (e.endsWith('.vue')) f.push(p);
    }
    return f;
}

const issues = [];

for (const file of walk(basePath)) {
    const content = readFileSync(file, 'utf8');
    const lines = content.split('\n');
    
    // Find all <form> tags and check if they have submit buttons
    let inForm = false;
    let formDepth = 0;
    let formStartLine = 0;
    let hasSubmit = false;
    let hasFormTag = false;
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        
        // Check for <form
        if (line.includes('<form') && !line.includes('</form')) {
            inForm = true;
            formDepth = 1;
            formStartLine = i + 1;
            hasSubmit = false;
            hasFormTag = true;
        }
        
        if (inForm) {
            // Check for submit button
            if (line.includes('type="submit"') || line.includes("@submit.prevent")) {
                hasSubmit = true;
            }
            
            // Check for form closing
            if (line.includes('</form>')) {
                inForm = false;
                if (!hasSubmit && hasFormTag) {
                    const relPath = file.replace('D:\\hrm_alepair\\resources\\js\\Pages\\', '');
                    issues.push(`${relPath}:${formStartLine} - <form> without type="submit" button`);
                }
                hasFormTag = false;
            }
        }
    }
}

if (issues.length === 0) {
    console.log('All <form> tags have submit buttons!');
} else {
    console.log('Forms without submit buttons:');
    issues.forEach(i => console.log('  ' + i));
}
