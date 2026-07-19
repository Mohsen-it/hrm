<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    title: { type: String, default: '' },
    icon: { type: String, default: null },
    iconColor: { type: String, default: 'primary' },
    loading: { type: Boolean, default: false },
    padded: { type: Boolean, default: true },
    collapsible: { type: Boolean, default: false },
    collapsed: { type: Boolean, default: false },
    actionRoute: { type: String, default: null },
    actionLabel: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['toggle-collapse']);

const iconColorClass = computed(() => {
    const map = {
        primary: 'bg-mistral-primary/10 text-mistral-primary',
        success: 'bg-mistral-success/10 text-mistral-success',
        danger: 'bg-mistral-danger/10 text-mistral-danger',
        warning: 'bg-mistral-warning/10 text-mistral-warning',
        info: 'bg-mistral-info/10 text-mistral-info',
        vacation: 'bg-cyan-50 text-cyan-600',
        purple: 'bg-purple-50 text-purple-600',
    };
    return map[props.iconColor] || map.primary;
});
</script>

<template>
    <div
        class="bg-white border border-mistral-hairline-soft rounded-xl overflow-hidden transition-shadow duration-200 hover:shadow-level-1"
        :dir="dir"
    >
        <!-- Header -->
        <div
            v-if="title || $slots.header"
            class="flex items-center justify-between px-5 py-4 border-b border-mistral-hairline-soft"
        >
            <div class="flex items-center gap-3">
                <div
                    v-if="icon"
                    :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', iconColorClass]"
                >
                    <i :class="[icon, 'text-[14px]']" aria-hidden="true"></i>
                </div>
                <div>
                    <h3 class="text-[14px] font-semibold text-mistral-ink">{{ title }}</h3>
                    <slot name="subtitle" />
                </div>
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" />
                <Link
                    v-if="actionRoute"
                    :href="actionRoute"
                    class="text-[12px] text-mistral-primary hover:text-mistral-primary-deep font-medium transition-colors"
                >
                    {{ actionLabel || t('common.view_all') }}
                    <i class="fas fa-arrow-left rtl-flip ms-1 text-[10px]" aria-hidden="true"></i>
                </Link>
                <button
                    v-if="collapsible"
                    type="button"
                    class="w-7 h-7 rounded-lg flex items-center justify-center text-mistral-stone hover:text-mistral-ink hover:bg-mistral-surface transition-colors"
                    @click="emit('toggle-collapse')"
                >
                    <i :class="[collapsed ? 'fas fa-chevron-down' : 'fas fa-chevron-up', 'text-[11px]']" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <!-- Body -->
        <div :class="[padded ? 'p-5' : '']">
            <!-- Loading state -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="flex items-center gap-3 text-mistral-stone">
                    <div class="w-5 h-5 border-2 border-mistral-primary/30 border-t-mistral-primary rounded-full animate-spin"></div>
                    <span class="text-[13px]">{{ t('common.loading') }}</span>
                </div>
            </div>
            <slot v-else />
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="px-5 py-3 border-t border-mistral-hairline-soft bg-mistral-surface/30">
            <slot name="footer" />
        </div>
    </div>
</template>
