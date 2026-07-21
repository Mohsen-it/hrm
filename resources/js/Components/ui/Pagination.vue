<script setup>
import { computed, ref, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    data: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    routeName: { type: String, default: '' },
    only: { type: Array, default: () => [] },
    perPageOptions: { type: Array, default: () => [10, 20, 50, 100] },
    showPageSize: { type: Boolean, default: true },
    showPageJump: { type: Boolean, default: true },
    showInfo: { type: Boolean, default: true },
    showFirstLast: { type: Boolean, default: true },
    maxVisiblePages: { type: Number, default: 5 },
    pageParam: { type: String, default: 'page' },
    perPageParam: { type: String, default: 'per_page' },
    paramName: { type: String, default: null },
    dir: { type: String, default: 'rtl' },
    preserveScroll: { type: Boolean, default: true },
    preserveState: { type: Boolean, default: true },
    replace: { type: Boolean, default: true },
    autoNavigate: { type: Boolean, default: false },
});

const emit = defineEmits(['page-change', 'per-page-change', 'navigating', 'navigated']);

const { t, isRtl } = useTranslations();

const doNavigate = (params) => {
    const only = props.only.length > 0 ? props.only : undefined;
    emit('navigating', params);
    if (props.routeName) {
        router.get(route(props.routeName), params, {
            preserveState: props.preserveState,
            preserveScroll: props.preserveScroll,
            replace: props.replace,
            only,
            onFinish: () => emit('navigated', params),
        });
        return;
    }
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
            preserveState: props.preserveState,
            preserveScroll: props.preserveScroll,
            replace: props.replace,
            only,
            onFinish: () => emit('navigated', params),
        },
    );
};

const paramKey = computed(() => props.paramName || props.pageParam);

const meta = computed(() => {
    const d = props.data || {};
    const m = d.meta || {};
    const itemsArr = Array.isArray(d.data) ? d.data : [];
    const total = d.total || m.total || itemsArr.length;
    const perPage = d.per_page || m.per_page || 20;
    const current = d.current_page || m.current_page || 1;
    const last = d.last_page || m.last_page || Math.max(1, Math.ceil(total / perPage));
    return {
        current: current,
        last: Math.max(last, 1),
        from: d.from || m.from || 0,
        to: d.to || m.to || 0,
        total: total,
        perPage: perPage,
    };
});

const hasData = computed(() => meta.value.total > 0);
const isMultiPage = computed(() => meta.value.last > 1);
const shouldShow = computed(() => hasData.value || isMultiPage.value);
const showPageNav = computed(() => isMultiPage.value);
const showPagination = computed(() => hasData.value);

const pageRange = computed(() => {
    const current = meta.value.current;
    const last = meta.value.last;
    const max = props.maxVisiblePages;
    if (last <= max + 2) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }
    const range = [];
    const side = Math.floor((max - 1) / 2);
    let start = Math.max(2, current - side);
    let end = Math.min(last - 1, current + side);

    if (current - 1 <= side) {
        end = Math.min(last - 1, max);
    }
    if (last - current <= side) {
        start = Math.max(2, last - max + 1);
    }
    range.push(1);
    if (start > 2) range.push('...');
    for (let i = start; i <= end; i++) range.push(i);
    if (end < last - 1) range.push('...');
    if (last > 1) range.push(last);
    return range;
});

const isLoading = ref(false);
const pendingPage = ref(null);
const pendingPerPage = ref(null);
let loadingTimer = null;

const startLoading = (key) => {
    if (loadingTimer) clearTimeout(loadingTimer);
    loadingTimer = setTimeout(() => {
        isLoading.value = true;
        if (key === 'page') pendingPage.value = true;
        if (key === 'perPage') pendingPerPage.value = true;
    }, 120);
};

const stopLoading = () => {
    if (loadingTimer) {
        clearTimeout(loadingTimer);
        loadingTimer = null;
    }
    isLoading.value = false;
    pendingPage.value = null;
    pendingPerPage.value = null;
};

watch(() => props.data, () => {
    stopLoading();
}, { deep: true });

const goToPage = (page) => {
    if (page < 1 || page > meta.value.last || page === meta.value.current) return;
    startLoading('page');
    emit('page-change', page);
    if (props.autoNavigate) {
        doNavigate({ ...props.filters, [paramKey.value]: page, [props.perPageParam]: meta.value.perPage });
    }
};

const goToPerPage = (size) => {
    const newSize = Number(size);
    if (!newSize || newSize === meta.value.perPage) return;
    startLoading('perPage');
    emit('per-page-change', newSize);
    if (props.autoNavigate) {
        doNavigate({ ...props.filters, [paramKey.value]: 1, [props.perPageParam]: newSize });
    }
};

const showJumpInput = ref(false);
const jumpValue = ref('');

const openJumpInput = () => {
    if (!isMultiPage.value) return;
    showJumpInput.value = true;
    jumpValue.value = String(meta.value.current);
    nextTick(() => {
        document.querySelector('[data-pagination-jump-input]')?.focus();
        document.querySelector('[data-pagination-jump-input]')?.select();
    });
};

const closeJumpInput = () => {
    showJumpInput.value = false;
    jumpValue.value = '';
};

const submitJump = () => {
    const target = parseInt(jumpValue.value, 10);
    if (Number.isFinite(target) && target >= 1 && target <= meta.value.last) {
        goToPage(target);
    } else {
        closeJumpInput();
    }
};

const goFirst = () => goToPage(1);
const goPrev = () => goToPage(meta.value.current - 1);
const goNext = () => goToPage(meta.value.current + 1);
const goLast = () => goToPage(meta.value.last);

const canPrev = computed(() => meta.value.current > 1);
const canNext = computed(() => meta.value.current < meta.value.last);

const chevronLeft = computed(() => props.dir === 'rtl' ? 'fa-chevron-right' : 'fa-chevron-left');
const chevronRight = computed(() => props.dir === 'rtl' ? 'fa-chevron-left' : 'fa-chevron-right');

const rangeText = computed(() => {
    if (!hasData.value) return '';
    const from = meta.value.from.toLocaleString();
    const to = meta.value.to.toLocaleString();
    const total = meta.value.total.toLocaleString();
    if (isRtl.value) {
        return `عرض ${from} - ${to} من إجمالي ${total}`;
    }
    return `Showing ${from}–${to} of ${total.toLocaleString()}`;
});
</script>

<template>
    <div
        v-if="showPagination"
        :dir="dir"
        :aria-busy="isLoading"
        class="dt-pagination flex items-center justify-between gap-3 flex-wrap px-4 py-3 border-t border-mistral-hairline-soft bg-mistral-surface/30 text-[12px] text-mistral-stone transition-opacity duration-150"
        :class="{ 'dt-pagination-loading opacity-70 pointer-events-none': isLoading }"
    >
        <div v-if="showInfo" class="flex items-center gap-2 min-w-0">
            <span class="whitespace-nowrap tabular-nums">{{ rangeText }}</span>
        </div>

        <div v-if="showPageSize" class="flex items-center gap-2">
            <span class="whitespace-nowrap hidden sm:inline">
                {{ isRtl ? 'لكل صفحة' : 'Rows per page' }}
            </span>
            <div class="relative">
                <select
                    :value="meta.perPage"
                    :disabled="isLoading"
                    class="h-8 ps-3 pe-7 text-[12px] font-medium text-mistral-ink bg-white border border-mistral-hairline-strong rounded-md appearance-none cursor-pointer select-with-arrow focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary transition-all duration-150 hover:border-mistral-primary/40 disabled:opacity-50"
                    @change="goToPerPage($event.target.value)"
                >
                    <option v-for="opt in perPageOptions" :key="opt" :value="opt">
                        {{ opt }}
                    </option>
                </select>
            </div>
        </div>

        <nav
            v-if="showPageNav"
            :aria-label="isRtl ? 'ترقيم الصفحات' : 'Pagination'"
            class="flex items-center gap-1"
        >
            <button
                v-if="showFirstLast"
                type="button"
                :disabled="!canPrev || isLoading"
                :aria-label="isRtl ? 'الصفحة الأولى' : 'First page'"
                :class="[
                    'dt-page-btn h-8 min-w-[32px] px-2 rounded-md text-mistral-steel transition-all duration-150',
                    !canPrev || isLoading
                        ? 'opacity-30 cursor-not-allowed'
                        : 'hover:text-mistral-ink hover:bg-white cursor-pointer',
                ]"
                @click="goFirst"
            >
                <i :class="['fas fa-angles-left rtl-flip text-[10px]', isRtl && 'rotate-180']" aria-hidden="true"></i>
            </button>

            <button
                type="button"
                :disabled="!canPrev || isLoading"
                :aria-label="isRtl ? 'السابق' : 'Previous'"
                :class="[
                    'dt-page-btn h-8 min-w-[32px] px-2 rounded-md text-mistral-steel transition-all duration-150',
                    !canPrev || isLoading
                        ? 'opacity-30 cursor-not-allowed'
                        : 'hover:text-mistral-ink hover:bg-white cursor-pointer',
                ]"
                @click="goPrev"
            >
                <i :class="[chevronLeft, 'rtl-flip text-[10px]']" aria-hidden="true"></i>
            </button>

            <template v-for="(p, idx) in pageRange" :key="`${p}-${idx}`">
                <span
                    v-if="p === '...'"
                    class="h-8 min-w-[28px] flex items-center justify-center text-mistral-muted select-none"
                >
                    <i class="fas fa-ellipsis text-[10px]" aria-hidden="true"></i>
                </span>
                <button
                    v-else
                    type="button"
                    :disabled="isLoading"
                    :aria-label="`${isRtl ? 'صفحة' : 'Page'} ${p}`"
                    :aria-current="p === meta.current ? 'page' : undefined"
                    :class="[
                        'dt-page-btn h-8 min-w-[32px] px-2.5 text-[12px] font-semibold rounded-md transition-all duration-150 tabular-nums',
                        p === meta.current
                            ? 'bg-mistral-primary text-white shadow-sm'
                            : 'text-mistral-steel hover:text-mistral-ink hover:bg-white cursor-pointer',
                        isLoading && p !== meta.current ? 'opacity-60' : '',
                    ]"
                    @click="goToPage(p)"
                >
                    <span v-if="isLoading && p === meta.current" class="inline-flex items-center gap-1.5">
                        <span class="dt-spinner-inline"></span>
                        <span>{{ p }}</span>
                    </span>
                    <span v-else>{{ p }}</span>
                </button>
            </template>

            <button
                type="button"
                :disabled="!canNext || isLoading"
                :aria-label="isRtl ? 'التالي' : 'Next'"
                :class="[
                    'dt-page-btn h-8 min-w-[32px] px-2 rounded-md text-mistral-steel transition-all duration-150',
                    !canNext || isLoading
                        ? 'opacity-30 cursor-not-allowed'
                        : 'hover:text-mistral-ink hover:bg-white cursor-pointer',
                ]"
                @click="goNext"
            >
                <i :class="[chevronRight, 'rtl-flip text-[10px]']" aria-hidden="true"></i>
            </button>

            <button
                v-if="showFirstLast"
                type="button"
                :disabled="!canNext || isLoading"
                :aria-label="isRtl ? 'الصفحة الأخيرة' : 'Last page'"
                :class="[
                    'dt-page-btn h-8 min-w-[32px] px-2 rounded-md text-mistral-steel transition-all duration-150',
                    !canNext || isLoading
                        ? 'opacity-30 cursor-not-allowed'
                        : 'hover:text-mistral-ink hover:bg-white cursor-pointer',
                ]"
                @click="goLast"
            >
                <i :class="['fas fa-angles-right rtl-flip text-[10px]', isRtl && 'rotate-180']" aria-hidden="true"></i>
            </button>

            <div v-if="showPageJump" class="hidden sm:flex items-center gap-1.5 ms-2 ps-2 border-s border-mistral-hairline-soft">
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-x-1 w-0"
                    enter-to-class="opacity-100 translate-x-0 w-auto"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-x-0 w-auto"
                    leave-to-class="opacity-0 -translate-x-1 w-0"
                    mode="out-in"
                >
                    <div v-if="showJumpInput" key="input" class="flex items-center gap-1 overflow-hidden">
                        <input
                            v-model="jumpValue"
                            data-pagination-jump-input
                            type="number"
                            min="1"
                            :max="meta.last"
                            :placeholder="`1-${meta.last}`"
                            class="dt-jump-input h-8 w-14 px-2 text-[12px] text-mistral-ink bg-white border border-mistral-primary rounded-md text-center tabular-nums focus:outline-none focus:ring-2 focus:ring-mistral-primary/30"
                            @keydown.enter="submitJump"
                            @keydown.esc="closeJumpInput"
                            @blur="submitJump"
                        />
                    </div>
                    <button
                        v-else
                        key="button"
                        type="button"
                        class="dt-page-btn h-8 px-2 text-[12px] text-mistral-steel rounded-md hover:text-mistral-ink hover:bg-white transition-all duration-150 cursor-pointer tabular-nums"
                        :title="isRtl ? 'الانتقال إلى صفحة' : 'Jump to page'"
                        @click="openJumpInput"
                    >
                        <span class="hidden md:inline">{{ isRtl ? 'الانتقال' : 'Jump' }}</span>
                        <i class="fas fa-arrow-right rtl-flip text-[10px] md:ms-1" :class="isRtl ? 'rotate-180' : ''" aria-hidden="true"></i>
                    </button>
                </Transition>
            </div>
        </nav>

        <div
            v-if="isLoading"
            class="flex items-center gap-1.5 text-mistral-primary text-[11px] font-medium tabular-nums"
            role="status"
            aria-live="polite"
        >
            <span class="dt-spinner-inline"></span>
            <span class="hidden sm:inline">{{ isRtl ? 'جاري التحميل...' : 'Loading...' }}</span>
        </div>
    </div>
</template>

<style scoped>
.dt-page-btn:not(:disabled):active {
    transform: scale(0.95);
}

.dt-page-btn[aria-current='page'] {
    transform: scale(1.05);
}

.dt-spinner-inline {
    display: inline-block;
    width: 10px;
    height: 10px;
    border: 1.5px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: dt-pag-spin 0.6s linear infinite;
}

@keyframes dt-pag-spin {
    to {
        transform: rotate(360deg);
    }
}

.dt-jump-input::-webkit-outer-spin-button,
.dt-jump-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.dt-jump-input[type='number'] {
    -moz-appearance: textfield;
}
</style>
