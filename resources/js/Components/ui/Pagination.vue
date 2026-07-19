<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    data: { type: Object, required: true },
    paramName: { type: String, default: 'page' },
    simple: { type: Boolean, default: false },
    dir: { type: String, default: 'rtl' },
});

const meta = computed(() => ({
    current: props.data?.current_page || 1,
    last: props.data?.last_page || 1,
    from: props.data?.from || 0,
    to: props.data?.to || 0,
    total: props.data?.total || 0,
}));

const pages = computed(() => {
    if (meta.value.last <= 1) return [];
    const current = meta.value.current;
    const last = meta.value.last;
    const delta = 2;
    const range = [];
    for (let i = Math.max(1, current - delta); i <= Math.min(last, current + delta); i++) {
        range.push(i);
    }
    return range;
});

function go(page) {
    if (page < 1 || page > meta.value.last || page === meta.value.current) return;
    const url = new URL(window.location.href);
    url.searchParams.set(props.paramName, page);
    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <nav v-if="meta.last > 1" :aria-label="dir === 'rtl' ? 'ترقيم الصفحات' : 'Pagination'" :dir="dir">
        <div class="flex items-center justify-center gap-1 flex-wrap">
            <button
                type="button"
                :disabled="meta.current === 1"
                :class="[
                    'h-8 min-w-[32px] px-2 text-[13px] font-medium rounded-lg transition-all duration-150',
                    meta.current === 1
                        ? 'text-mistral-muted cursor-not-allowed'
                        : 'text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface cursor-pointer',
                ]"
                @click="go(meta.current - 1)"
            >
                <i :class="[dir === 'rtl' ? 'fas fa-chevron-right' : 'fas fa-chevron-left', 'rtl-flip text-[10px]']" aria-hidden="true"></i>
            </button>

            <template v-if="!simple">
                <button
                    v-for="page in pages"
                    :key="page"
                    type="button"
                    :aria-current="page === meta.current ? 'page' : undefined"
                    :class="[
                        'h-8 min-w-[32px] px-2 text-[13px] font-medium rounded-lg transition-all duration-150 hidden sm:inline-flex',
                        page === meta.current
                            ? 'bg-mistral-primary text-white shadow-sm'
                            : 'text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface cursor-pointer',
                    ]"
                    @click="go(page)"
                >
                    {{ page }}
                </button>
            </template>

            <span v-if="!simple" class="text-[12px] text-mistral-stone sm:hidden">
                {{ meta.current }} / {{ meta.last }}
            </span>

            <button
                type="button"
                :disabled="meta.current === meta.last"
                :class="[
                    'h-8 min-w-[32px] px-2 text-[13px] font-medium rounded-lg transition-all duration-150',
                    meta.current === meta.last
                        ? 'text-mistral-muted cursor-not-allowed'
                        : 'text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface cursor-pointer',
                ]"
                @click="go(meta.current + 1)"
            >
                <i :class="[dir === 'rtl' ? 'fas fa-chevron-left' : 'fas fa-chevron-right', 'rtl-flip text-[10px]']" aria-hidden="true"></i>
            </button>
        </div>
    </nav>
</template>
