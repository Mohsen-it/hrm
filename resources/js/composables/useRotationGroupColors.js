/**
 * Shared professional palette for rotation-group badges.
 *
 * Soft tinted backgrounds with matching colored text and a solid dot —
 * aligned with the Mistral design tokens (no raw palette colors) so group
 * badges look consistent on the users table, manage-assignments and timeline
 * pages. Colors are picked by group position (group_index), never by name,
 * so Arabic names (الأولى، ...) get distinct colors just like A/B/C/D.
 */
const GROUP_PALETTE = Object.freeze([
    { bg: 'bg-mistral-success-bg', border: 'border-mistral-success/20', text: 'text-mistral-success', dot: 'bg-mistral-success' },
    { bg: 'bg-mistral-info-bg', border: 'border-mistral-info/20', text: 'text-mistral-info', dot: 'bg-mistral-info' },
    { bg: 'bg-mistral-warning-bg', border: 'border-mistral-warning/20', text: 'text-mistral-warning', dot: 'bg-mistral-warning' },
    { bg: 'bg-mistral-danger-bg', border: 'border-mistral-danger/20', text: 'text-mistral-danger', dot: 'bg-mistral-danger' },
    { bg: 'bg-mistral-status-overtime/10', border: 'border-mistral-status-overtime/20', text: 'text-mistral-status-overtime', dot: 'bg-mistral-status-overtime' },
    { bg: 'bg-mistral-status-vacation/10', border: 'border-mistral-status-vacation/20', text: 'text-mistral-status-vacation', dot: 'bg-mistral-status-vacation' },
    { bg: 'bg-mistral-primary/10', border: 'border-mistral-primary/20', text: 'text-mistral-primary', dot: 'bg-mistral-primary' },
    { bg: 'bg-mistral-cream-deeper', border: 'border-mistral-beige-deep', text: 'text-mistral-primary-deep', dot: 'bg-mistral-sunshine-700' },
]);

export const groupColorFallback = Object.freeze({
    bg: 'bg-mistral-surface',
    border: 'border-mistral-hairline',
    text: 'text-mistral-steel',
    dot: 'bg-mistral-steel',
});

/**
 * Resolve the badge colors for a group position (group_index).
 * Falls back to neutral gray for missing/non-numeric input.
 */
export function groupColorByIndex(index) {
    const i = Number(index);

    if (!Number.isFinite(i)) {
        return groupColorFallback;
    }

    const n = GROUP_PALETTE.length;

    return GROUP_PALETTE[((i % n) + n) % n];
}

export function useRotationGroupColors() {
    return {
        palette: GROUP_PALETTE,
        fallback: groupColorFallback,
        groupColorByIndex,
    };
}
