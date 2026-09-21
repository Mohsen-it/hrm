/**
 * chartPalette.js — Central chart color palette (P0-UX).
 *
 * Single source of truth for every literal color used inside Chart.js
 * configs and print CSS fallbacks. Each value mirrors a design token in
 * `resources/css/app.css` 1:1 (see --color-mistral-*). Canvas/print contexts
 * cannot consume Tailwind classes, so literals live HERE ONLY —
 * `npm run lint:tokens` (scattered-hex) forbids hex anywhere else in
 * Components/Pages. To add a color: add the token in app.css first,
 * then mirror it here with the token name in a comment.
 *
 * @see specs/013-ux-learnability (ux plan, created in P0)
 */

// --color-mistral-primary #054239
export const CHART_PRIMARY = '#054239';
// --color-mistral-success #007a3d
export const CHART_SUCCESS = '#007a3d';
// --color-mistral-danger #ce1126
export const CHART_DANGER = '#ce1126';
// --color-mistral-warning #d97706
export const CHART_WARNING = '#d97706';
// --color-mistral-info #2563eb
export const CHART_INFO = '#2563eb';
// --color-mistral-status-overtime #7c3aed
export const CHART_OVERTIME = '#7c3aed';
// --color-mistral-status-vacation #0891b2
export const CHART_VACATION = '#0891b2';
// --color-mistral-ink #171717
export const CHART_INK = '#171717';
// --color-mistral-steel #525252
export const CHART_STEEL = '#525252';
// --color-mistral-stone #737373
export const CHART_STONE = '#737373';
// --color-mistral-hairline-soft #ededed
export const CHART_GRID = '#ededed';
// --color-mistral-yellow-saturated #c9a227
export const CHART_GOLD = '#c9a227';

export const CHART_SUCCESS_SOFT = 'rgba(0,122,61,0.1)';
export const CHART_DANGER_SOFT = 'rgba(206,17,38,0.05)';

/** Ordered categorical palette for N-series charts / group dots. */
export const CHART_CATEGORICAL = [
    CHART_PRIMARY,
    CHART_SUCCESS,
    CHART_INFO,
    CHART_WARNING,
    CHART_OVERTIME,
    CHART_VACATION,
    CHART_DANGER,
    CHART_STONE,
];

/** Legacy object shape used by Dashboard.vue. */
export const CHART_COLORS = {
    primary: CHART_PRIMARY,
    success: CHART_SUCCESS,
    danger: CHART_DANGER,
    warning: CHART_WARNING,
    info: CHART_INFO,
    grid: CHART_GRID,
    successSoft: CHART_SUCCESS_SOFT,
    dangerSoft: CHART_DANGER_SOFT,
};
