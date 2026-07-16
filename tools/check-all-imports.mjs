import { readFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
const components = ['Button', 'Card', 'FormInput', 'FormSelect', 'FormTextarea', 'FormCheckbox', 'FormRadio', 'FormSwitch', 'FormDatepicker', 'FormGroup', 'FormModal', 'DataTable', 'SearchInput', 'PageHeader', 'Badge', 'Alert', 'ConfirmDialog', 'EmptyState', 'StatCard', 'LoadingSpinner', 'IconButton', 'Avatar', 'Pagination', 'Tabs', 'Breadcrumb'];

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
    const c = readFileSync(file, 'utf8');
    const rel = file.replace('D:\\hrm_alepair\\resources\\js\\Pages\\', '');
    
    for (const comp of components) {
        const usesComp = new RegExp(`<${comp}[\\s>]`).test(c);
        const importsComp = new RegExp(`import ${comp} from`).test(c);
        
        if (usesComp && !importsComp) {
            issues.push(`${rel}: missing import for <${comp}>`);
        }
    }
}

if (issues.length === 0) {
    console.log('All component imports are present!');
} else {
    console.log('Missing imports:');
    issues.forEach(i => console.log('  ' + i));
}
