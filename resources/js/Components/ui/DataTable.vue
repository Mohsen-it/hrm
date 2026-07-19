<script setup>
import { computed, ref, watch, onMounted, onUnmounted, nextTick, TransitionGroup } from 'vue';
import Card from './Card.vue';
import DataTableToolbar from './DataTable/DataTableToolbar.vue';
import DataTableSkeleton from './DataTable/DataTableSkeleton.vue';
import LoadingSpinner from './LoadingSpinner.vue';
import EmptyState from './EmptyState.vue';
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
    title: { type: String, default: '' },
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
const meta = computed(() => ({
    current_page: props.data?.current_page || 1,
    last_page: props.data?.last_page || 1,
    per_page: props.data?.per_page || props.perPage,
    total: props.data?.total || 0,
    from: props.data?.from || 0,
    to: props.data?.to || 0,
}));

const selectableItems = computed(() => {
    if (!props.selectableFilter) return items.value;
    return items.value.filter(props.selectableFilter);
});

const allRowIds = computed(() => selectableItems.value.map((r) => r.id));
const allSelected = computed(() => allRowIds.value.length > 0 && allRowIds.value.every((id) => table.selectedIds.value.includes(id)));
const someSelected = computed(() => table.selectedIds.value.length > 0 && !allSelected.value);

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

function goToPage(page) {
    table.setPage(page);
    emit('page-change', page);
}

function goPrev() {
    if (meta.value.current_page > 1) goToPage(meta.value.current_page - 1);
}

function goNext() {
    if (meta.value.current_page < meta.value.last_page) goToPage(meta.value.current_page + 1);
}

const paginationPages = computed(() => {
    const last = meta.value.last_page;
    const current = meta.value.current_page;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages = [];
    const delta = 2;
    for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
        pages.push(i);
    }
    if (pages[0] > 1) pages.unshift(1);
    if (pages[pages.length - 1] < last) pages.push(last);
    return pages;
});

const perPageOptions = [10, 20, 50, 100];

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

            <div class="relative overflow-x-auto" ref="tableWrapperRef" @scroll="handleTableScroll">
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
                        <tr v-if="loading">
                            <td :colspan="table.visibleColumns.value.length + (selectable ? 1 : 0)" class="p-0">
                                <DataTableSkeleton
                                    :rows="5"
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
                                :key="row.id || rowIndex"
                                :data-row="row.id"
                                :tabindex="rowClickable || selectable ? 0 : -1"
                                :class="[
                                    'border-b border-mistral-hairline-soft/60 last:border-0 transition-all duration-200',
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

            <div
                v-if="enablePagination && meta.total > 0 && !loading"
                class="flex items-center justify-between px-4 py-3 border-t border-mistral-hairline-soft bg-mistral-surface/30 text-[12px] text-mistral-stone flex-wrap gap-3"
            >
                <div class="flex items-center gap-3">
                    <span class="whitespace-nowrap">
                        {{ isRtl ? 'عرض' : 'Showing' }} {{ meta.from }} - {{ meta.to }} {{ isRtl ? 'من' : 'of' }} {{ meta.total.toLocaleString() }}
                    </span>
                    <select
                        :value="meta.per_page"
                        class="h-7 px-2 text-[12px] text-mistral-ink bg-white border border-mistral-hairline-strong rounded-md cursor-pointer focus:outline-none focus:ring-1 focus:ring-mistral-primary/20"
                        @change="$emit('per-page-change', Number($event.target.value))"
                    >
                        <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                </div>

                <nav v-if="meta.last_page > 1" :aria-label="isRtl ? 'ترقيم الصفحات' : 'Pagination'">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            :disabled="meta.current_page === 1"
                            :class="[
                                'h-8 min-w-[32px] px-2 text-[13px] font-medium rounded-lg transition-all duration-150',
                                meta.current_page === 1
                                    ? 'text-mistral-muted cursor-not-allowed'
                                    : 'text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface cursor-pointer',
                            ]"
                            @click="goPrev"
                        >
                            <i :class="[dir === 'rtl' ? 'fas fa-chevron-right' : 'fas fa-chevron-left', 'rtl-flip text-[10px]']" aria-hidden="true"></i>
                        </button>

                        <template v-for="page in paginationPages" :key="page">
                            <span
                                v-if="page === '...'"
                                class="h-8 min-w-[32px] flex items-center justify-center text-[13px] text-mistral-muted"
                            >
                                ...
                            </span>
                            <button
                                v-else
                                type="button"
                                :aria-current="page === meta.current_page ? 'page' : undefined"
                                :class="[
                                    'h-8 min-w-[32px] px-2 text-[13px] font-medium rounded-lg transition-all duration-150',
                                    page === meta.current_page
                                        ? 'bg-mistral-primary text-white shadow-sm'
                                        : 'text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface cursor-pointer',
                                ]"
                                @click="goToPage(page)"
                            >
                                {{ page }}
                            </button>
                        </template>

                        <button
                            type="button"
                            :disabled="meta.current_page === meta.last_page"
                            :class="[
                                'h-8 min-w-[32px] px-2 text-[13px] font-medium rounded-lg transition-all duration-150',
                                meta.current_page === meta.last_page
                                    ? 'text-mistral-muted cursor-not-allowed'
                                    : 'text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface cursor-pointer',
                            ]"
                            @click="goNext"
                        >
                            <i :class="[dir === 'rtl' ? 'fas fa-chevron-left' : 'fas fa-chevron-right', 'rtl-flip text-[10px]']" aria-hidden="true"></i>
                        </button>
                    </div>
                </nav>
            </div>
        </Card>
    </div>
</template>
