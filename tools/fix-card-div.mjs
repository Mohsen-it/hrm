#!/usr/bin/env node
// Proper state machine: track Card and div stacks separately to fix orphan </Card>
import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let fixedFiles = 0;

function getAllVueFiles(dir) {
    const files = [];
    for (const entry of readdirSync(dir)) {
        const fullPath = join(dir, entry);
        const stat = statSync(fullPath);
        if (stat.isDirectory()) files.push(...getAllVueFiles(fullPath));
        else if (entry.endsWith('.vue')) files.push(fullPath);
    }
    return files;
}

function fixVueFile(content) {
    // Tokenize: find all tags in template
    // Process template section only (skip <script>)
    const scriptStart = content.indexOf('<script');
    const scriptEnd = content.indexOf('</script>');
    const before = scriptStart >= 0 ? content.slice(0, scriptStart) : content;
    const script = scriptStart >= 0 ? content.slice(scriptStart, scriptEnd + 9) : '';
    const after = scriptEnd >= 0 ? content.slice(scriptEnd + 9) : '';

    // State machine on `before + after` (template + post-template)
    const target = before + after;

    // Find all tags in order
    const tagRegex = /<\/?([A-Z][A-Za-z0-9-]*|div|span|form|button|table|tr|td|th|tbody|thead|select|textarea|input|label|a|p|h[1-6]|ul|ol|li|nav|header|footer|main|section|article)\b[^>]*?(\/?)>/g;
    const tags = [];
    let m;
    while ((m = tagRegex.exec(target)) !== null) {
        tags.push({ full: m[0], name: m[1].toLowerCase(), isClose: m[0].startsWith('</'), isSelfClose: m[2] === '/', pos: m.index });
    }

    // Stack-based: when we see </Card> at top level of template, convert to </div>
    // The issue: previous scripts converted inner </div> to </Card> when there was a Card open
    // Fix: for each </Card>, check if there's an unclosed <div> or <Card> at the same level

    // Simpler approach: rebuild the file, tracking tag depth
    let result = '';
    let i = 0;
    const cardStack = []; // stack of {name, indent}
    const divStack = [];

    // Process character by character, identifying tags
    while (i < target.length) {
        const cardOpen = target.indexOf('<Card', i);
        const cardClose = target.indexOf('</Card>', i);
        const divOpen = target.indexOf('<div', i);
        const divClose = target.indexOf('</div>', i);
        const nextOther = -1; // skip for now

        const candidates = [
            { pos: cardOpen, type: 'cardOpen' },
            { pos: cardClose, type: 'cardClose' },
            { pos: divOpen, type: 'divOpen' },
            { pos: divClose, type: 'divClose' },
        ].filter(c => c.pos >= 0).sort((a, b) => a.pos - b.pos);

        if (candidates.length === 0) {
            result += target.slice(i);
            break;
        }

        const next = candidates[0];
        result += target.slice(i, next.pos);

        if (next.type === 'cardOpen') {
            // Find end of tag
            const endOfTag = target.indexOf('>', next.pos);
            if (endOfTag < 0) break;
            result += target.slice(next.pos, endOfTag + 1);
            cardStack.push('Card');
            i = endOfTag + 1;
        } else if (next.type === 'cardClose') {
            // Convert to </div> if there's an unclosed div at this point
            if (divStack.length > 0) {
                result += '</div>';
                divStack.pop();
            } else {
                // No unclosed div - this </Card> is correct
                result += '</Card>';
                if (cardStack.length > 0) cardStack.pop();
            }
            i = next.pos + '</Card>'.length;
        } else if (next.type === 'divOpen') {
            const endOfTag = target.indexOf('>', next.pos);
            if (endOfTag < 0) break;
            result += target.slice(next.pos, endOfTag + 1);
            // Only push if not self-closing
            if (!target.slice(next.pos, endOfTag).endsWith('/>')) {
                divStack.push('div');
            }
            i = endOfTag + 1;
        } else if (next.type === 'divClose') {
            // Convert to </Card> if there's an unclosed Card at this point
            if (cardStack.length > 0) {
                result += '</Card>';
                cardStack.pop();
            } else {
                result += '</div>';
                if (divStack.length > 0) divStack.pop();
            }
            i = next.pos + '</div>'.length;
        }
    }

    return before ? result.replace(target, before + result) : (result + (scriptEnd >= 0 ? script : ''));
}
