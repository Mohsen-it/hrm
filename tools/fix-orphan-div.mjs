#!/usr/bin/env node
// Fix orphan </div> after <Card> opening tags
import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

const basePath = 'D:\\hrm_alepair\\resources\\js\\Pages';
let fixed = 0;

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

for (const file of getAllVueFiles(basePath)) {
    let content;
    try { content = readFileSync(file, 'utf8'); } catch (e) { continue; }

    // Count Card opens (excluding self-closing)
    const cardOpens = (content.match(/<Card\s+/g) || []).length;
    const cardCloses = (content.match(/<\/Card>/g) || []).length;
    const diff = cardOpens - cardCloses;

    if (diff <= 0) continue;

    // Find each <Card ... > opening and replace the next </div> with </Card>
    // Use a state machine: track open count
    let result = '';
    let i = 0;
    let cardStack = 0;
    let replacements = 0;

    while (i < content.length) {
        // Look for next tag
        const cardOpenMatch = content.slice(i).match(/<Card\s+[^>]*>/);
        const cardCloseMatch = content.slice(i).match(/<\/Card>/);
        const divCloseMatch = content.slice(i).match(/<\/div>/);

        if (!divCloseMatch) break;

        const divPos = i + divCloseMatch.index;
        const cardOpenPos = cardOpenMatch ? i + cardOpenMatch.index : Infinity;
        const cardClosePos = cardCloseMatch ? i + cardCloseMatch.index : Infinity;

        if (cardOpenPos < divPos && cardOpenPos < cardClosePos) {
            // Card opening before div close
            result += content.slice(i, cardOpenPos);
            result += cardOpenMatch[0];
            cardStack++;
            i = cardOpenPos + cardOpenMatch[0].length;
        } else if (cardClosePos < divPos) {
            // Card closing before div close
            result += content.slice(i, cardClosePos);
            result += '</Card>';
            cardStack = Math.max(0, cardStack - 1);
            i = cardClosePos + '</Card>'.length;
        } else {
            // div closing
            if (cardStack > 0) {
                // Replace this </div> with </Card>
                result += content.slice(i, divPos);
                result += '</Card>';
                cardStack--;
                replacements++;
                i = divPos + '</div>'.length;
            } else {
                result += content.slice(i, divPos + '</div>'.length);
                i = divPos + '</div>'.length;
            }
        }
    }
    result += content.slice(i);

    if (replacements > 0) {
        writeFileSync(file, result, 'utf8');
        fixed += replacements;
        console.log(`Fixed ${replacements} </div> in ${file.replace('D:\\hrm_alepair\\resources\\js\\Pages\\', '')}`);
    }
}

console.log(`\nFixed ${fixed} </div> tags`);
