import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export function usePagination({
    routeName = null,
    filters = () => ({}),
    only = [],
    pageParam = 'page',
    perPageParam = 'per_page',
    preserveScroll = true,
    preserveState = true,
    replace = true,
    debounce = 0,
} = {}) {
    const page = usePage();
    const isNavigating = ref(false);
    const isPageChanging = ref(false);
    const isPerPageChanging = ref(false);

    let navigationTimer = null;
    let debounceTimer = null;
    let pendingNavigation = null;

    const startNavigation = (type = 'page') => {
        if (navigationTimer) clearTimeout(navigationTimer);
        navigationTimer = setTimeout(() => {
            isNavigating.value = true;
            if (type === 'page') isPageChanging.value = true;
            if (type === 'perPage') isPerPageChanging.value = true;
        }, 80);
    };

    const stopNavigation = () => {
        if (navigationTimer) {
            clearTimeout(navigationTimer);
            navigationTimer = null;
        }
        isNavigating.value = false;
        isPageChanging.value = false;
        isPerPageChanging.value = false;
    };

    const onStart = () => {
        startNavigation();
    };

    const onFinish = () => {
        stopNavigation();
        pendingNavigation = null;
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
    };

    const unsubscribe = router.on('start', onStart);
    let finishUnsub = null;

    const performNavigation = (params) => {
        if (debounce > 0) {
            if (debounceTimer) clearTimeout(debounceTimer);
            pendingNavigation = params;
            debounceTimer = setTimeout(() => {
                if (pendingNavigation) {
                    doNavigate(pendingNavigation);
                    pendingNavigation = null;
                }
            }, debounce);
        } else {
            doNavigate(params);
        }
    };

    const doNavigate = (params) => {
        if (routeName) {
            router.get(
                route(routeName),
                params,
                {
                    preserveState,
                    preserveScroll,
                    replace,
                    only: only.length > 0 ? only : undefined,
                    onFinish,
                },
            );
        } else {
            const url = new URL(window.location.href);
            for (const [k, v] of Object.entries(params)) {
                if (v === null || v === undefined || v === '') {
                    url.searchParams.delete(k);
                } else {
                    url.searchParams.set(k, v);
                }
            }
            router.get(
                url.pathname + url.search,
                {},
                {
                    preserveState,
                    preserveScroll,
                    replace,
                    only: only.length > 0 ? only : undefined,
                    onFinish,
                },
            );
        }
    };

    const goToPage = (targetPage, extraParams = {}) => {
        if (!targetPage || targetPage < 1) return;
        performNavigation({
            ...filters(),
            ...extraParams,
            [pageParam]: targetPage,
        });
    };

    const setPerPage = (perPage, resetPage = true) => {
        if (!perPage) return;
        performNavigation({
            ...filters(),
            [perPageParam]: perPage,
            ...(resetPage ? { [pageParam]: 1 } : {}),
        });
    };

    onUnmounted(() => {
        if (unsubscribe) unsubscribe();
        if (finishUnsub) finishUnsub();
        if (navigationTimer) clearTimeout(navigationTimer);
        if (debounceTimer) clearTimeout(debounceTimer);
    });

    return {
        isNavigating,
        isPageChanging,
        isPerPageChanging,
        goToPage,
        setPerPage,
    };
}

export function useTableNavigation() {
    const isNavigating = ref(false);
    const isPageChanging = ref(false);
    const isPerPageChanging = ref(false);
    const isSearchChanging = ref(false);
    const isFilterChanging = ref(false);

    let navigationTimer = null;

    const detectChange = (event) => {
        const params = event.detail?.visit?.url
            ? new URL(event.detail.visit.url, window.location.origin).searchParams
            : null;

        if (!params) return;

        if (params.has('page')) isPageChanging.value = true;
        if (params.has('per_page')) isPerPageChanging.value = true;
        if (params.has('search')) isSearchChanging.value = true;
        const hasFilter = Array.from(params.keys()).some((k) => !['page', 'per_page', 'search'].includes(k));
        if (hasFilter) isFilterChanging.value = true;

        isNavigating.value = true;
    };

    const resetState = () => {
        isNavigating.value = false;
        isPageChanging.value = false;
        isPerPageChanging.value = false;
        isSearchChanging.value = false;
        isFilterChanging.value = false;
        if (navigationTimer) clearTimeout(navigationTimer);
    };

    const handleStart = () => {
        if (navigationTimer) clearTimeout(navigationTimer);
        isNavigating.value = true;
    };

    const handleFinish = () => {
        if (navigationTimer) clearTimeout(navigationTimer);
        navigationTimer = setTimeout(resetState, 100);
    };

    onMounted(() => {
        if (typeof window !== 'undefined') {
            window.addEventListener('inertia:start', handleStart);
            window.addEventListener('inertia:finish', handleFinish);
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('inertia:start', handleStart);
            window.removeEventListener('inertia:finish', handleFinish);
        }
        if (navigationTimer) clearTimeout(navigationTimer);
    });

    return {
        isNavigating,
        isPageChanging,
        isPerPageChanging,
        isSearchChanging,
        isFilterChanging,
    };
}
