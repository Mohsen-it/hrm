<script setup>
import { computed, ref, watch, onMounted, onUnmounted, nextTick, TransitionGroup } from 'vue';
import Card from './Card.vue';
import DataTableToolbar from './DataTable/DataTableToolbar.vue';
import DataTableSkeleton from './DataTable/DataTableSkeleton.vue';
import LoadingSpinner from './LoadingSpinner.vue';
import EmptyState from './EmptyState.vue';
import Pagination from './Pagination.vue';
import { useDataTable } from './DataTable/useDataTable.js';
import { useTranslations } from '@/composables/useTranslations';

const { t, isRtl } = useTranslations();

const props = defineProps({
    columns: { type: Array, required: true },
    data: { type: Object, default: () => ({ data: [], links: [] }) },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    emptyTitle: { type: String, default: '' },
    emptyDescription: { type: String, default: '' },
    rowClickable: { type: Boolean, default: false },
    selectable: { type: Boolean, default: true },
    enableSearch: { type: Boolean, default: true },
    enableFilters: { type: Boolean, default: true },
    enableDensity: { type: Boolean, default: true },
    enableColumnVisibility: { type: Boolean, default: true },
    enableExport: { type: Boolean, default: true },
    enablePagination: { type: Boolean, default: true },
    selectableFilter: { type: Function, default: null },
    dir: { type: String, default: 'rtl' },
    storageKey: { type: String, default: 'default' },
    perPage: { type: Number, default: 20 },
    perPageOptions: { type: Array, default: () => [10, 20, 50, 100] },
    title: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
    routeName: { type: String, default: '' },
    only: { type: Array, default: () => [] },
    maxVisiblePages: { type: Number, default: 5 },
    showPageJump: { type: Boolean, default: true },
    showFirstLast: { type: Boolean, default: true },
    showPageInfo: { type: Boolean, default: true },
});

const emit = defineEmits([
    'row-click',
    'selection-change',
    'sort-change',
    'page-change',
    'per-page-change',
    'search',
    'filter-change',
    'export',
]);

const table = useDataTable({
    columns: props.columns,
    storageKey: props.storageKey,
    defaultPerPage: props.perPage,
});

const items = computed(() => props.data?.data || []);
const meta = computed(() => {
    const d = props.data || {};
    const m = d.meta || {};
    return {
        current_page: d.current_page || m.current_page || 1,
        last_page: d.last_page || m.last_page || 1,
        per_page: d.per_page || m.per_page || props.perPage,
        total: d.total || m.total || 0,
        from: d.from || m.from || 0,
        to: d.to || m.to || 0,
    };
});

const paginationData = computed(() => {
    const d = props.data || {};
    const m = d.meta || {};
    const itemsArr = Array.isArray(d.data) ? d.data : [];
    const currentPage = d.current_page || m.current_page || 1;
    const perPage = d.per_page || m.per_page || props.perPage;
    const total = d.total || m.total || itemsArr.length;
    const lastPage = d.last_page || m.last_page || Math.max(1, Math.ceil(total / perPage));
    const computedFrom = itemsArr.length > 0 ? (currentPage - 1) * perPage + 1 : 0;
    return {
        current_page: currentPage,
        last_page: Math.max(lastPage, 1),
        per_page: perPage,
        total: total,
        from: d.from || m.from || computedFrom,
        to: d.to || m.to || (computedFrom > 0 ? computedFrom + itemsArr.length - 1 : 0),
    };
});

const selectableItems = computed(() => {
    if (!props.selectableFilter) return items.value;
    return items.value.filter(props.selectableFilter);
});

const allRowIds = computed(() => selectableItems.value.map((r) => r.id));
const allSelected = computed(() => allRowIds.value.length > 0 && allRowIds.value.every((id) => table.selectedIds.value.includes(id)));
const someSelected = computed(() => table.selectedIds.value.length > 0 && !allSelected.value);

const showSkeleton = computed(() => props.loading || (table.isNavigating.value && items.value.length > 0));
const showPageTransition = computed(() => table.isPageChanging.value || table.isPerPageChanging.value);

watch(() => table.selectedIds.value, (ids) => {
    emit('selection-change', ids);
}, { deep: true });

watch(() => table.sortColumn.value, (col) => {
    emit('sort-change', { column: col, direction: table.sortDirection.value });
});

watch(() => table.filters.value, (f) => {
    emit('filter-change', f);
}, { deep: true });

function cellValue(row, col) {
    if (typeof col.accessor === 'function') return col.accessor(row);
    if (typeof col.accessor === 'string') return row[col.accessor];
    return row[col.key];
}

function onRowClick(row) {
    if (props.rowClickable) emit('row-click', row);
}

function onRowKeydown(e, row, index) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (props.rowClickable) emit('row-click', row);
        if (props.selectable) table.selectRow(row.id);
    }
}

const tableWrapperRef = ref(null);
const headerShadow = ref(false);

function handleTableScroll(e) {
    headerShadow.value = e.target.scrollTop > 0;
}

function getSortIcon(col) {
    if (table.sortColumn.value !== col.key) return 'fas fa-sort text-mistral-muted';
    if (table.sortDirection.value === 'asc') return 'fas fa-sort-up text-mistral-primary';
    return 'fas fa-sort-down text-mistral-primary';
}

function onSearch(value) {
    table.globalSearch.value = value;
    emit('search', value);
}

function onFilterChange(filters) {
    table.filters.value = filters;
}

function onDensityChange(d) {
    table.setDensity(d);
}

function onToggleColumn(key) {
    table.toggleColumn(key);
}

function onExport(payload) {
    emit('export', payload);
}

function onSaveFilter(name) {
    table.saveCurrentFilter(name);
}

function onLoadFilter(name) {
    table.loadFilter(name);
}

function onDeleteFilter(name) {
    table.deleteFilter(name);
}

const lastVisibleColIndex = computed(() => {
    const cols = table.visibleColumns.value;
    for (let i = cols.length - 1; i >= 0; i--) {
        if (cols[i].key !== 'actions' && cols[i].key !== 'select') return i;
    }
    return cols.length - 1;
});
</script>

<template>
    <div :dir="dir">
        <Card variant="base" padding="none" class="overflow-hidden">
            <DataTableToolbar
                :columns="columns"
                :visible-columns="table.visibleColumns.value"
                :selected-ids="table.selectedIds.value"
                :total="meta.total"
                :global-search="table.globalSearch.value"
                :filters="table.filters.value"
                :density="table.density.value"
                :expanded-filters="table.expandedFilters.value"
                :has-active-filters="table.hasActiveFilters.value"
                :active-filter-count="table.activeFilterCount.value"
                :saved-filters="table.savedFilters.value"
                :enable-search="enableSearch"
                :enable-filters="enableFilters"
                :enable-density="enableDensity"
                :enable-column-visibility="enableColumnVisibility"
                :enable-export="enableExport"
                :dir="dir"
                @search="onSearch"
                @filter-change="onFilterChange"
                @density-change="onDensityChange"
                @toggle-column="onToggleColumn"
                @toggle-expanded-filters="table.expandedFilters.value = !table.expandedFilters.value"
                @export="onExport"
                @clear-filters="table.clearFilters()"
                @save-filter="onSaveFilter"
                @load-filter="onLoadFilter"
                @delete-filter="onDeleteFilter"
                @bulk-delete="$emit('export', { format: 'bulk-delete', ids: table.selectedIds.value })"
                @bulk-export="$emit('export', { format: 'bulk-export', ids: table.selectedIds.value })"
            />

            <div class="relative">
                <div
                    v-if="showPageTransition && !loading"
                    class="dt-table-progress"
                    role="presentation"
                >
                    <div class="dt-table-progress-bar"></div>
                </div>

                <div
                    class="relative overflow-x-auto transition-opacity duration-150"
                    :class="showPageTransition && !loading ? 'opacity-60' : 'opacity-100'"
                    ref="tableWrapperRef"
                    @scroll="handleTableScroll"
                >
                    <table class="w-full border-collapse text-start" :class="table.densityClass.value" role="grid">
                        <thead>
                            <tr
                                :class="[
                                    'bg-mistral-surface/60 border-b border-mistral-hairline-soft transition-shadow duration-200',
                                    headerShadow ? 'dt-header-shadow' : '',
                                ]"
                            >
                                <th
                                    v-if="selectable"
                                    class="sticky bg-mistral-surface/60 z-10 px-4 py-3 text-center w-[48px]"
                                    :class="dir === 'rtl' ? 'right-0' : 'left-0'"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="allSelected"
                                        :indeterminate.prop="someSelected"
                                        class="w-4 h-4 rounded border-mistral-hairline-strong text-mistral-primary focus:ring-mistral-primary/20 cursor-pointer"
                                        @change="table.selectAll(allRowIds)"
                                    />
                                </th>
                                <th
                                    v-for="(col, colIdx) in table.visibleColumns.value"
                                    :key="col.key"
                                    :class="[
                                        'px-4 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider',
                                        col.sortable ? 'cursor-pointer select-none hover:text-mistral-ink transition-colors' : '',
                                        table.sortColumn.value === col.key ? 'text-mistral-primary' : '',
                                        col.headerClass,
                                        col.key === 'actions' ? 'sticky bg-mistral-surface/60 z-10 text-center w-[120px]' : '',
                                        col.key === 'actions' ? (dir === 'rtl' ? 'right-0' : 'left-0') : '',
                                        colIdx === lastVisibleColIndex && col.key !== 'actions' ? (dir === 'rtl' ? 'ps-4' : 'pe-4') : '',
                                    ]"
                                    :style="col.width ? { width: col.width } : {}"
                                    @click="col.sortable ? table.toggleSort(col.key) : null"
                                >
                                    <div class="flex items-center gap-1.5" :class="col.key === 'actions' ? 'justify-center' : ''">
                                        <span>{{ col.label }}</span>
                                        <i
                                            v-if="col.sortable"
                                            :class="[getSortIcon(col), 'text-[10px]']"
                                            aria-hidden="true"
                                        ></i>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="showSkeleton">
                                <td :colspan="table.visibleColumns.value.length + (selectable ? 1 : 0)" class="p-0">
                                    <DataTableSkeleton
                                        :rows="Math.min(meta.per_page || 10, 8)"
                                        :columns="table.visibleColumns.value.length"
                                        :selectable="selectable"
                                        :density="table.density.value"
                                        :dir="dir"
                                    />
                                </td>
                            </tr>
                            <tr v-else-if="error">
                                <td :colspan="table.visibleColumns.value.length + (selectable ? 1 : 0)" class="text-center py-16">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-14 h-14 rounded-2xl bg-mistral-danger/10 flex items-center justify-center">
                                            <i class="fas fa-exclamation-triangle text-[24px] text-mistral-danger"></i>
                                        </div>
                                        <p class="text-[14px] text-mistral-ink font-semibold">حدث خطأ</p>
                                        <p class="text-[13px] text-mistral-stone">{{ error }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="items.length === 0">
                                <td :colspan="table.visibleColumns.value.length + (selectable ? 1 : 0)" class="p-0">
                                    <slot name="empty">
                                        <EmptyState :title="emptyTitle || t('common.no_data')" :description="emptyDescription" />
                                    </slot>
                                </td>
                            </tr>
                            <template v-else>
                                <tr
                                    v-for="(row, rowIndex) in items"
                                    :key="`${meta.current_page}-${row.id || rowIndex}`"
                                    :data-row="row.id"
                                    :tabindex="rowClickable || selectable ? 0 : -1"
                                    :class="[
                                        'dt-row border-b border-mistral-hairline-soft/60 last:border-0 transition-all duration-200',
                                        rowIndex % 2 === 1 ? 'bg-mistral-surface/30' : 'bg-white',
                                        'hover:bg-mistral-cream-light/40',
                                        rowClickable ? 'cursor-pointer' : '',
                                        table.selectedIds.value.includes(row.id) ? 'bg-mistral-primary/5' : '',
                                        table.focusedRowIndex.value === rowIndex ? 'ring-2 ring-inset ring-mistral-primary/30' : '',
                                    ]"
                                    @click="onRowClick(row)"
                                    @keydown="onRowKeydown($event, row, rowIndex)"
                                >
                                    <td
                                        v-if="selectable"
                                        :class="[
                                            'sticky z-10 px-4 py-3 text-center w-[48px]',
                                            dir === 'rtl' ? 'right-0' : 'left-0',
                                            rowIndex % 2 === 1 ? 'bg-mistral-surface/30' : 'bg-white',
                                            table.selectedIds.value.includes(row.id) ? 'bg-mistral-primary/5' : '',
                                        ]"
                                    >
                                        <div @click.stop>
                                            <input
                                                type="checkbox"
                                                :checked="table.selectedIds.value.includes(row.id)"
                                                class="w-4 h-4 rounded border-mistral-hairline-strong text-mistral-primary focus:ring-mistral-primary/20 cursor-pointer"
                                                @change="table.selectRow(row.id)"
                                            />
                                        </div>
                                    </td>
                                    <td
                                        v-for="(col, colIdx) in table.visibleColumns.value"
                                        :key="col.key"
                                        :class="[
                                            'px-4 py-3 text-[13px] text-mistral-ink',
                                            col.cellClass,
                                            col.key === 'actions' ? 'sticky z-10 text-center' : '',
                                            col.key === 'actions' ? (dir === 'rtl' ? 'right-0' : 'left-0') : '',
                                            col.key === 'actions' ? (rowIndex % 2 === 1 ? 'bg-mistral-surface/30' : 'bg-white') : '',
                                            col.key === 'actions' && table.selectedIds.value.includes(row.id) ? 'bg-mistral-primary/5' : '',
                                        ]"
                                    >
                                        <slot :name="`cell-${col.key}`" :row="row" :value="cellValue(row, col)">
                                            <span v-if="col.badge">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap',
                                                        col.badge.class || 'bg-mistral-surface text-mistral-stone',
                                                    ]"
                                                >
                                                    <span
                                                        v-if="col.badge.dot"
                                                        :class="['w-1.5 h-1.5 rounded-full shrink-0', col.badge.dotClass || 'bg-mistral-stone']"
                                                    ></span>
                                                    {{ cellValue(row, col) }}
                                                </span>
                                            </span>
                                            <span v-else-if="col.tooltip" class="relative group/tooltip">
                                                {{ cellValue(row, col) }}
                                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 text-[11px] text-white bg-mistral-ink rounded-lg opacity-0 group-hover/tooltip:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-20">
                                                    {{ col.tooltip(row) }}
                                                </span>
                                            </span>
                                            <span v-else>{{ cellValue(row, col) }}</span>
                                        </slot>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <Pagination
                v-if="enablePagination"
                :data="paginationData"
                :filters="filters"
                :route-name="routeName"
                :only="only"
                :per-page-options="perPageOptions"
                :max-visible-pages="maxVisiblePages"
                :show-page-jump="showPageJump"
                :show-first-last="showFirstLast"
                :show-info="showPageInfo"
                :dir="dir"
                :auto-navigate="!!routeName"
                @page-change="(p) => emit('page-change', p)"
                @per-page-change="(s) => emit('per-page-change', s)"
            />
        </Card>
    </div>
</template>

<style scoped>
.dt-table-progress {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    z-index: 20;
    background-color: transparent;
    overflow: hidden;
    border-top-left-radius: inherit;
    border-top-right-radius: inherit;
}

.dt-table-progress-bar {
    height: 100%;
    width: 30%;
    background: linear-gradient(
        90deg,
        transparent 0%,
        var(--color-mistral-primary) 50%,
        transparent 100%
    );
    animation: dt-progress-slide 1s ease-in-out infinite;
    border-radius: 9999px;
}

@keyframes dt-progress-slide {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(400%);
    }
}

.dt-row {
    animation: dt-row-fade-in 0.2s ease-out;
}

@keyframes dt-row-fade-in {
    from {
        opacity: 0;
        transform: translateY(2px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
