<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    columns: { type: Array, required: true },
    visibleColumns: { type: Array, required: true },
    selectedIds: { type: Array, default: () => [] },
    totalRows: { type: Number, default: 0 },
    globalSearch: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
    density: { type: String, default: 'default' },
    expandedFilters: { type: Boolean, default: false },
    hasActiveFilters: { type: Boolean, default: false },
    activeFilterCount: { type: Number, default: 0 },
    savedFilters: { type: Array, default: () => [] },
    enableSearch: { type: Boolean, default: true },
    enableFilters: { type: Boolean, default: true },
    enableDensity: { type: Boolean, default: true },
    enableColumnVisibility: { type: Boolean, default: true },
    enableExport: { type: Boolean, default: true },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits([
    'update:globalSearch',
    'search',
    'filter-change',
    'density-change',
    'toggle-column',
    'toggle-expanded-filters',
    'export',
    'clear-filters',
    'save-filter',
    'load-filter',
    'delete-filter',
    'bulk-delete',
    'bulk-export',
]);

const searchValue = ref(props.globalSearch);
let searchTimer = null;

watch(() => props.globalSearch, (v) => { searchValue.value = v; });

watch(searchValue, (v) => {
    emit('update:globalSearch', v);
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => emit('search', v), 300);
});

const showColumnMenu = ref(false);
const showExportMenu = ref(false);
const showSavedFilterMenu = ref(false);
const saveFilterName = ref('');
const columnMenuRef = ref(null);
const exportMenuRef = ref(null);
const savedFilterMenuRef = ref(null);

function handleClickOutside(e) {
    if (columnMenuRef.value && !columnMenuRef.value.contains(e.target)) showColumnMenu.value = false;
    if (exportMenuRef.value && !exportMenuRef.value.contains(e.target)) showExportMenu.value = false;
    if (savedFilterMenuRef.value && !savedFilterMenuRef.value.contains(e.target)) showSavedFilterMenu.value = false;
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

const filterableColumns = computed(() => {
    return props.columns.filter((c) => c.filterable);
});

const hasSelection = computed(() => props.selectedIds.length > 0);

function onFilterChange(key, value) {
    const next = { ...props.filters };
    if (value === '' || value === null || value === undefined) {
        delete next[key];
    } else {
        next[key] = value;
    }
    emit('filter-change', next);
}

function handleSaveFilter() {
    if (!saveFilterName.value.trim()) return;
    emit('save-filter', saveFilterName.value.trim());
    saveFilterName.value = '';
    showSavedFilterMenu.value = false;
}

function exportCSV() {
    const headers = props.visibleColumns.filter((c) => c.key !== 'actions' && c.key !== 'select').map((c) => c.label);
    const rows = [];
    for (const col of props.visibleColumns) {
        if (col.key === 'actions' || col.key === 'select') continue;
    }
    emit('export', { format: 'csv', columns: props.visibleColumns });
    showExportMenu.value = false;
}
</script>

<template>
    <div class="border-b border-mistral-hairline-soft bg-white rounded-t-xl">
        <div class="px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2 flex-wrap">
                <div v-if="enableSearch" class="relative">
                    <i
                        class="fas fa-magnifying-glass absolute top-1/2 -translate-y-1/2 text-mistral-muted text-[13px]"
                        :class="dir === 'rtl' ? 'right-3' : 'left-3'"
                        aria-hidden="true"
                    ></i>
                    <input
                        v-model="searchValue"
                        type="search"
                        :placeholder="dir === 'rtl' ? 'بحث...' : 'Search...'"
                        :class="[
                            'h-9 w-full sm:w-64 text-[13px] text-mistral-ink bg-white border border-mistral-hairline-strong rounded-lg transition-all duration-150',
                            'placeholder:text-mistral-muted',
                            'focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary',
                            dir === 'rtl' ? 'pr-9 pl-8' : 'pl-9 pr-8',
                        ]"
                    />
                    <button
                        v-if="searchValue"
                        type="button"
                        class="absolute top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center rounded-full text-mistral-muted hover:text-mistral-ink hover:bg-mistral-surface transition-colors"
                        :class="dir === 'rtl' ? 'left-2' : 'right-2'"
                        @click="searchValue = ''"
                    >
                        <i class="fas fa-xmark text-[10px]" aria-hidden="true"></i>
                    </button>
                </div>

                <button
                    v-if="enableFilters && filterableColumns.length > 0"
                    type="button"
                    :class="[
                        'h-9 px-3 text-[13px] font-medium rounded-lg border transition-all duration-150 inline-flex items-center gap-2',
                        expandedFilters
                            ? 'bg-mistral-primary/10 text-mistral-primary border-mistral-primary/30'
                            : 'bg-white text-mistral-steel border-mistral-hairline-strong hover:bg-mistral-surface',
                    ]"
                    @click="emit('toggle-expanded-filters')"
                >
                    <i class="fas fa-sliders text-[11px]" aria-hidden="true"></i>
                    <span class="hidden sm:inline">{{ dir === 'rtl' ? 'فلاتر' : 'Filters' }}</span>
                    <span
                        v-if="activeFilterCount > 0"
                        class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-mistral-primary text-white"
                    >
                        {{ activeFilterCount }}
                    </span>
                </button>
            </div>

            <div class="flex items-center gap-2">
                <div v-if="hasSelection" class="flex items-center gap-2 ms-2 pe-2 border-e border-mistral-hairline-soft">
                    <span class="text-[13px] text-mistral-primary font-semibold whitespace-nowrap">
                        {{ selectedIds.length }} {{ dir === 'rtl' ? 'محدد' : 'selected' }}
                    </span>
                    <button
                        type="button"
                        class="h-8 px-3 text-[12px] font-medium rounded-lg bg-mistral-danger/10 text-mistral-danger hover:bg-mistral-danger/20 transition-colors"
                        @click="emit('bulk-delete')"
                    >
                        <i class="fas fa-trash ms-1" aria-hidden="true"></i>
                        {{ dir === 'rtl' ? 'حذف' : 'Delete' }}
                    </button>
                    <button
                        type="button"
                        class="h-8 px-3 text-[12px] font-medium rounded-lg bg-mistral-info/10 text-mistral-info hover:bg-mistral-info/20 transition-colors"
                        @click="emit('bulk-export')"
                    >
                        <i class="fas fa-download ms-1" aria-hidden="true"></i>
                        {{ dir === 'rtl' ? 'تصدير' : 'Export' }}
                    </button>
                </div>

                <div v-if="enableDensity" class="flex items-center bg-mistral-surface rounded-lg p-0.5">
                    <button
                        v-for="d in [{ key: 'compact', icon: 'fas fa-compress', label: 'Compact' }, { key: 'default', icon: 'fas fa-equals', label: 'Default' }, { key: 'comfortable', icon: 'fas fa-expand', label: 'Comfortable' }]"
                        :key="d.key"
                        type="button"
                        :class="[
                            'w-7 h-7 flex items-center justify-center rounded-md text-[11px] transition-all duration-150',
                            density === d.key
                                ? 'bg-white text-mistral-primary shadow-sm'
                                : 'text-mistral-stone hover:text-mistral-ink',
                        ]"
                        :title="d.label"
                        @click="emit('density-change', d.key)"
                    >
                        <i :class="d.icon" aria-hidden="true"></i>
                    </button>
                </div>

                <div v-if="enableColumnVisibility" class="relative" ref="columnMenuRef">
                    <button
                        type="button"
                        class="h-9 w-9 flex items-center justify-center rounded-lg border border-mistral-hairline-strong text-mistral-steel hover:bg-mistral-surface transition-colors"
                        title="Columns"
                        @click="showColumnMenu = !showColumnMenu"
                    >
                        <i class="fas fa-table-columns text-[12px]" aria-hidden="true"></i>
                    </button>
                    <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div
                            v-if="showColumnMenu"
                            class="absolute top-full mt-1 z-30 w-56 bg-white border border-mistral-hairline-soft rounded-xl shadow-level-3 py-1"
                            :class="dir === 'rtl' ? 'right-0' : 'left-0'"
                        >
                            <div class="px-3 py-2 border-b border-mistral-hairline-soft">
                                <span class="text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                    {{ dir === 'rtl' ? 'الأعمدة' : 'Columns' }}
                                </span>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <label
                                    v-for="col in columns"
                                    :key="col.key"
                                    class="flex items-center gap-2.5 px-3 py-2 hover:bg-mistral-surface cursor-pointer transition-colors"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="columnVisibility[col.key] !== false"
                                        class="w-3.5 h-3.5 rounded border-mistral-hairline-strong text-mistral-primary focus:ring-mistral-primary/20 cursor-pointer"
                                        @change="emit('toggle-column', col.key)"
                                    />
                                    <span class="text-[13px] text-mistral-ink">{{ col.label }}</span>
                                </label>
                            </div>
                        </div>
                    </Transition>
                </div>

                <div v-if="enableExport" class="relative" ref="exportMenuRef">
                    <button
                        type="button"
                        class="h-9 px-3 flex items-center justify-center gap-1.5 rounded-lg border border-mistral-hairline-strong text-mistral-steel text-[13px] font-medium hover:bg-mistral-surface transition-colors"
                        @click="showExportMenu = !showExportMenu"
                    >
                        <i class="fas fa-download text-[11px]" aria-hidden="true"></i>
                        <span class="hidden sm:inline">{{ dir === 'rtl' ? 'تصدير' : 'Export' }}</span>
                        <i class="fas fa-chevron-down text-[9px] text-mistral-muted" aria-hidden="true"></i>
                    </button>
                    <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div
                            v-if="showExportMenu"
                            class="absolute top-full mt-1 z-30 w-44 bg-white border border-mistral-hairline-soft rounded-xl shadow-level-3 py-1"
                            :class="dir === 'rtl' ? 'right-0' : 'left-0'"
                        >
                            <button
                                v-for="fmt in [{ key: 'csv', icon: 'fas fa-file-csv', label: 'CSV' }, { key: 'excel', icon: 'fas fa-file-excel', label: 'Excel' }]"
                                :key="fmt.key"
                                type="button"
                                class="w-full flex items-center gap-2 px-3 py-2 text-[13px] text-mistral-ink hover:bg-mistral-surface transition-colors"
                                @click="emit('export', { format: fmt.key }); showExportMenu = false"
                            >
                                <i :class="[fmt.icon, 'text-mistral-stone text-[12px]']" aria-hidden="true"></i>
                                {{ fmt.label }}
                            </button>
                        </div>
                    </Transition>
                </div>

                <div v-if="enableFilters && savedFilters.length > 0" class="relative" ref="savedFilterMenuRef">
                    <button
                        type="button"
                        class="h-9 px-3 flex items-center justify-center gap-1.5 rounded-lg border border-mistral-hairline-strong text-mistral-steel text-[13px] font-medium hover:bg-mistral-surface transition-colors"
                        @click="showSavedFilterMenu = !showSavedFilterMenu"
                    >
                        <i class="fas fa-bookmark text-[11px]" aria-hidden="true"></i>
                        <span class="hidden sm:inline">{{ dir === 'rtl' ? 'المحفوظة' : 'Saved' }}</span>
                    </button>
                    <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div
                            v-if="showSavedFilterMenu"
                            class="absolute top-full mt-1 z-30 w-56 bg-white border border-mistral-hairline-soft rounded-xl shadow-level-3 py-1"
                            :class="dir === 'rtl' ? 'right-0' : 'left-0'"
                        >
                            <div class="px-3 py-2 border-b border-mistral-hairline-soft">
                                <span class="text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                    {{ dir === 'rtl' ? 'الفلاتر المحفوظة' : 'Saved Filters' }}
                                </span>
                            </div>
                            <div v-if="savedFilters.length === 0" class="px-3 py-4 text-center text-[13px] text-mistral-muted">
                                {{ dir === 'rtl' ? 'لا توجد فلاتر محفوظة' : 'No saved filters' }}
                            </div>
                            <div v-else class="max-h-48 overflow-y-auto">
                                <div
                                    v-for="sf in savedFilters"
                                    :key="sf.name"
                                    class="flex items-center justify-between px-3 py-2 hover:bg-mistral-surface group"
                                >
                                    <button
                                        type="button"
                                        class="text-[13px] text-mistral-ink hover:text-mistral-primary flex-1 text-start truncate"
                                        @click="emit('load-filter', sf.name); showSavedFilterMenu = false"
                                    >
                                        {{ sf.name }}
                                    </button>
                                    <button
                                        type="button"
                                        class="w-5 h-5 flex items-center justify-center rounded text-mistral-muted hover:text-mistral-danger opacity-0 group-hover:opacity-100 transition-opacity"
                                        @click="emit('delete-filter', sf.name)"
                                    >
                                        <i class="fas fa-xmark text-[10px]" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>

        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="max-h-0 opacity-0"
            enter-to-class="max-h-[500px] opacity-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="max-h-[500px] opacity-100"
            leave-to-class="max-h-0 opacity-0"
        >
            <div v-if="expandedFilters && filterableColumns.length > 0" class="overflow-hidden border-t border-mistral-hairline-soft">
                <div class="px-4 py-3 flex items-center gap-3 flex-wrap">
                    <template v-for="col in filterableColumns" :key="col.key">
                        <div v-if="col.filterType === 'select'" class="min-w-[160px]">
                            <label class="block text-[11px] font-semibold text-mistral-steel uppercase tracking-wider mb-1">
                                {{ col.label }}
                            </label>
                            <select
                                :value="filters[col.key] ?? ''"
                                class="h-9 w-full px-3 text-[13px] text-mistral-ink bg-white border border-mistral-hairline-strong rounded-lg appearance-none cursor-pointer select-with-arrow focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary"
                                @change="onFilterChange(col.key, $event.target.value)"
                            >
                                <option value="">{{ dir === 'rtl' ? 'الكل' : 'All' }}</option>
                                <option
                                    v-for="opt in col.filterOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div v-else-if="col.filterType === 'date'" class="min-w-[160px]">
                            <label class="block text-[11px] font-semibold text-mistral-steel uppercase tracking-wider mb-1">
                                {{ col.label }}
                            </label>
                            <input
                                type="date"
                                :value="filters[col.key] ?? ''"
                                class="h-9 w-full px-3 text-[13px] text-mistral-ink bg-white border border-mistral-hairline-strong rounded-lg focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary"
                                @change="onFilterChange(col.key, $event.target.value)"
                            />
                        </div>
                        <div v-else class="min-w-[160px]">
                            <label class="block text-[11px] font-semibold text-mistral-steel uppercase tracking-wider mb-1">
                                {{ col.label }}
                            </label>
                            <input
                                type="text"
                                :value="filters[col.key] ?? ''"
                                :placeholder="dir === 'rtl' ? 'فلتر...' : 'Filter...'"
                                class="h-9 w-full px-3 text-[13px] text-mistral-ink bg-white border border-mistral-hairline-strong rounded-lg placeholder:text-mistral-muted focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary"
                                @input="onFilterChange(col.key, $event.target.value)"
                            />
                        </div>
                    </template>

                    <div class="flex items-end gap-2 ms-auto">
                        <button
                            type="button"
                            class="h-9 px-3 text-[13px] font-medium rounded-lg text-mistral-steel hover:bg-mistral-surface border border-mistral-hairline-strong transition-colors"
                            @click="emit('clear-filters')"
                        >
                            <i class="fas fa-rotate-left ms-1 text-[11px]" aria-hidden="true"></i>
                            {{ dir === 'rtl' ? 'مسح' : 'Clear' }}
                        </button>
                        <div class="relative">
                            <input
                                v-model="saveFilterName"
                                type="text"
                                :placeholder="dir === 'rtl' ? 'اسم الفلتر' : 'Filter name'"
                                class="h-9 px-3 text-[13px] text-mistral-ink bg-white border border-mistral-hairline-strong rounded-lg placeholder:text-mistral-muted focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary w-32"
                                @keydown.enter="handleSaveFilter"
                            />
                            <button
                                type="button"
                                class="absolute top-1/2 -translate-y-1/2 h-6 w-6 flex items-center justify-center rounded text-mistral-muted hover:text-mistral-primary transition-colors"
                                :class="dir === 'rtl' ? 'left-1' : 'right-1'"
                                :disabled="!saveFilterName.trim()"
                                @click="handleSaveFilter"
                            >
                                <i class="fas fa-bookmark text-[11px]" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
