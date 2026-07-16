import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useFilters(initialFilters = {}, options = {}) {
    const filters = ref({ ...initialFilters });
    const debounceMs = options.debounceMs ?? 300;

    let debounceTimer = null;

    function applyFilters(extra = {}) {
        const params = { ...filters.value, ...extra };
        Object.keys(params).forEach((k) => {
            if (params[k] === '' || params[k] === null || params[k] === undefined) {
                delete params[k];
            }
        });
        router.get(window.location.pathname, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            ...options.routerOptions,
        });
    }

    watch(
        filters,
        () => {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => applyFilters(), debounceMs);
        },
        { deep: true },
    );

    function clear() {
        Object.keys(filters.value).forEach((k) => {
            filters.value[k] = '';
        });
        applyFilters();
    }

    return { filters, applyFilters, clear };
}
