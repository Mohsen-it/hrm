import { ref } from 'vue';

/**
 * Shared page-title state for persistent layouts.
 *
 * With the persistent-layout pattern, AppLayout no longer receives a `title`
 * prop from each page (the layout outlives the page component), so pages set
 * the title here and AppLayout reads it for the Navbar.
 *
 * Pages call `usePageTitle(t('some.title'))` inside `<script setup>`.
 * AppLayout calls `usePageTitle()` (no argument) to read the reactive value,
 * and `resetPageTitle()` on navigation so pages without a title don't inherit
 * the previous page's one.
 */
const title = ref('');

export function usePageTitle(value) {
    if (value !== undefined) {
        title.value = value;
    }
    return title;
}

export function resetPageTitle() {
    title.value = '';
}
