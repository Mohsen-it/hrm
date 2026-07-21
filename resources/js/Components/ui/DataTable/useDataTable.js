import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const STORAGE_KEY_DENSITY = 'hrm-dt-density';
const STORAGE_KEY_COLUMNS = 'hrm-dt-columns';
const STORAGE_KEY_SAVED_FILTERS = 'hrm-dt-saved-filters';

function loadStorage(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

function saveStorage(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {}
}

export function useDataTable({
    columns = [],
    storageKey = 'default',
    defaultPerPage = 20,
    defaultDensity = 'default',
} = {}) {
    const density = ref(loadStorage(STORAGE_KEY_DENSITY, defaultDensity));

    const columnVisibility = ref(() => {
        const saved = loadStorage(`${STORAGE_KEY_COLUMNS}-${storageKey}`, {});
        const state = {};
        for (const col of columns) {
            if (col.hidden) {
                state[col.key] = false;
            } else if (saved[col.key] !== undefined) {
                state[col.key] = saved[col.key];
            } else {
                state[col.key] = true;
            }
        }
        return state;
    });

    const sortColumn = ref(null);
    const sortDirection = ref('asc');
    const selectedIds = ref([]);
    const globalSearch = ref('');
    const filters = ref({});
    const expandedFilters = ref(false);
    const currentPage = ref(1);
    const perPage = ref(defaultPerPage);
    const focusedRowIndex = ref(-1);
    const savedFilters = ref(loadStorage(`${STORAGE_KEY_SAVED_FILTERS}-${storageKey}`, []));

    const isNavigating = ref(false);
    const isPageChanging = ref(false);
    const isPerPageChanging = ref(false);
    const isSearchChanging = ref(false);
    const isFilterChanging = ref(false);

    let navTimer = null;
    const handleInertiaStart = (event) => {
        if (navTimer) clearTimeout(navTimer);
        navTimer = setTimeout(() => {
            isNavigating.value = true;
            const url = event?.detail?.visit?.url;
            if (url) {
                try {
                    const params = new URL(url, window.location.origin).searchParams;
                    if (params.has('page')) isPageChanging.value = true;
                    if (params.has('per_page')) isPerPageChanging.value = true;
                    if (params.has('search')) isSearchChanging.value = true;
                    const hasOtherFilter = Array.from(params.keys()).some(
                        (k) => !['page', 'per_page', 'search'].includes(k),
                    );
                    if (hasOtherFilter) isFilterChanging.value = true;
                } catch {}
            }
        }, 60);
    };

    const handleInertiaFinish = () => {
        if (navTimer) clearTimeout(navTimer);
        navTimer = setTimeout(() => {
            isNavigating.value = false;
            isPageChanging.value = false;
            isPerPageChanging.value = false;
            isSearchChanging.value = false;
            isFilterChanging.value = false;
        }, 80);
    };

    onMounted(() => {
        if (typeof window !== 'undefined') {
            window.addEventListener('inertia:start', handleInertiaStart);
            window.addEventListener('inertia:finish', handleInertiaFinish);
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('inertia:start', handleInertiaStart);
            window.removeEventListener('inertia:finish', handleInertiaFinish);
        }
        if (navTimer) clearTimeout(navTimer);
    });

    function setDensity(d) {
        density.value = d;
        saveStorage(STORAGE_KEY_DENSITY, d);
    }

    function toggleColumn(key) {
        columnVisibility.value[key] = !columnVisibility.value[key];
        saveStorage(`${STORAGE_KEY_COLUMNS}-${storageKey}`, columnVisibility.value);
    }

    function toggleSort(key) {
        if (sortColumn.value === key) {
            if (sortDirection.value === 'asc') {
                sortDirection.value = 'desc';
            } else if (sortDirection.value === 'desc') {
                sortColumn.value = null;
                sortDirection.value = 'asc';
            }
        } else {
            sortColumn.value = key;
            sortDirection.value = 'asc';
        }
    }

    function selectRow(id) {
        const idx = selectedIds.value.indexOf(id);
        if (idx === -1) {
            selectedIds.value.push(id);
        } else {
            selectedIds.value.splice(idx, 1);
        }
    }

    function selectAll(rowIds) {
        if (selectedIds.value.length === rowIds.length) {
            selectedIds.value = [];
        } else {
            selectedIds.value = [...rowIds];
        }
    }

    function clearSelection() {
        selectedIds.value = [];
    }

    function setFilter(key, value) {
        if (value === '' || value === null || value === undefined) {
            const next = { ...filters.value };
            delete next[key];
            filters.value = next;
        } else {
            filters.value = { ...filters.value, [key]: value };
        }
        currentPage.value = 1;
    }

    function setFilters(newFilters) {
        filters.value = { ...newFilters };
        currentPage.value = 1;
    }

    function clearFilters() {
        filters.value = {};
        currentPage.value = 1;
    }

    function saveCurrentFilter(name) {
        const filter = { name, filters: { ...filters.value }, search: globalSearch.value, timestamp: Date.now() };
        const existing = savedFilters.value.findIndex((f) => f.name === name);
        if (existing >= 0) {
            savedFilters.value[existing] = filter;
        } else {
            savedFilters.value.push(filter);
        }
        saveStorage(`${STORAGE_KEY_SAVED_FILTERS}-${storageKey}`, savedFilters.value);
    }

    function loadFilter(name) {
        const found = savedFilters.value.find((f) => f.name === name);
        if (found) {
            filters.value = { ...found.filters };
            globalSearch.value = found.search || '';
            currentPage.value = 1;
        }
    }

    function deleteFilter(name) {
        savedFilters.value = savedFilters.value.filter((f) => f.name !== name);
        saveStorage(`${STORAGE_KEY_SAVED_FILTERS}-${storageKey}`, savedFilters.value);
    }

    function setPage(page) {
        currentPage.value = page;
    }

    function setPerPage(size) {
        perPage.value = size;
        currentPage.value = 1;
    }

    const visibleColumns = computed(() => {
        return columns.filter((col) => columnVisibility.value[col.key] !== false);
    });

    const densityClass = computed(() => {
        return {
            compact: 'dt-compact',
            default: 'dt-default',
            comfortable: 'dt-comfortable',
        }[density.value] || 'dt-default';
    });

    const hasActiveFilters = computed(() => {
        return Object.keys(filters.value).length > 0 || globalSearch.value.length > 0;
    });

    const activeFilterCount = computed(() => {
        return Object.keys(filters.value).length + (globalSearch.value ? 1 : 0);
    });

    function handleKeydown(e, rows) {
        if (!rows || rows.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            focusedRowIndex.value = Math.min(focusedRowIndex.value + 1, rows.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusedRowIndex.value = Math.max(focusedRowIndex.value - 1, 0);
        } else if (e.key === ' ' && e.target.closest('tr[data-row]')) {
            e.preventDefault();
            const row = rows[focusedRowIndex.value];
            if (row) selectRow(row.id);
        }
    }

    function resetState() {
        sortColumn.value = null;
        sortDirection.value = 'asc';
        selectedIds.value = [];
        globalSearch.value = '';
        filters.value = {};
        currentPage.value = 1;
        focusedRowIndex.value = -1;
    }

    return {
        density,
        columnVisibility,
        sortColumn,
        sortDirection,
        selectedIds,
        globalSearch,
        filters,
        expandedFilters,
        currentPage,
        perPage,
        focusedRowIndex,
        savedFilters,
        visibleColumns,
        densityClass,
        hasActiveFilters,
        activeFilterCount,
        isNavigating,
        isPageChanging,
        isPerPageChanging,
        isSearchChanging,
        isFilterChanging,
        setDensity,
        toggleColumn,
        toggleSort,
        selectRow,
        selectAll,
        clearSelection,
        setFilter,
        setFilters,
        clearFilters,
        saveCurrentFilter,
        loadFilter,
        deleteFilter,
        setPage,
        setPerPage,
        handleKeydown,
        resetState,
    };
}
