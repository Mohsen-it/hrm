<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import SidebarGroup from './SidebarGroup.vue';
import { useSidebarMenu } from '@/composables/useSidebarMenu';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: true,
    },
    isCollapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'toggle-collapse']);

const page = usePage();
const { t, isRtl } = useTranslations();
const { menuGroups } = useSidebarMenu();

const user = computed(() => page.props.auth?.user || null);

const activeRoute = computed(() => {
    const url = page.url || '/';
    const cleaned = url.replace(/^\//, '').split('?')[0];
    return cleaned;
});

const navClass = computed(() => {
    if (props.isCollapsed) return 'w-16';
    return 'w-[260px]';
});

const visibilityClass = computed(() => {
    if (props.isOpen) return 'translate-x-0';
    return isRtl.value ? 'translate-x-full' : '-translate-x-full';
});

function onItemNavigate() {
    if (window.innerWidth < 768) {
        emit('close');
    }
}
</script>

<template>
    <aside
        :class="[
            'sidebar fixed top-0 bottom-0 flex flex-col z-40 transition-all duration-200',
            navClass,
            isRtl ? 'right-0' : 'left-0',
            visibilityClass,
        ]"
        :aria-label="t('common.main') || 'Sidebar'"
        role="navigation"
    >
        <!-- Logo + brand -->
        <div class="px-4 py-4 flex items-center gap-3 border-b border-[var(--color-hairline)] h-14 shrink-0">
            <div
                class="w-8 h-8 rounded-md bg-[var(--color-primary)] flex items-center justify-center text-white font-bold text-[16px] shrink-0"
            >
                H
            </div>
            <span v-if="!isCollapsed" class="text-[18px] font-bold text-[var(--color-primary)]">
                HRM
            </span>
        </div>

        <!-- Menu groups -->
        <nav class="flex-1 overflow-y-auto py-2">
            <template v-for="group in menuGroups" :key="group.key">
                <SidebarGroup
                    :group="group"
                    :is-collapsed="isCollapsed"
                    :active-route="activeRoute"
                    @click="onItemNavigate"
                />
            </template>
        </nav>

        <!-- Bottom: language + user -->
        <div class="border-t border-[var(--color-hairline)] p-2 space-y-2 shrink-0">
            <div v-if="!isCollapsed" class="flex justify-center">
                <LanguageSwitcher :dir="isRtl ? 'rtl' : 'ltr'" />
            </div>
            <div v-if="user" class="sidebar-item">
                <div
                    class="w-7 h-7 rounded-full bg-[var(--color-primary-light)] text-[var(--color-primary)] flex items-center justify-center text-[12px] font-bold shrink-0"
                >
                    {{ (user.name || user.email || '?').charAt(0).toUpperCase() }}
                </div>
                <div v-if="!isCollapsed" class="flex flex-col text-right overflow-hidden">
                    <span class="text-[13px] font-semibold text-[var(--color-ink)] truncate">
                        {{ user.name || user.email }}
                    </span>
                    <span class="text-[11px] text-[var(--color-ink-faint)] truncate">
                        {{ user.email }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Desktop collapse toggle -->
        <button
            type="button"
            class="hidden md:flex absolute top-1/2 -translate-y-1/2 w-6 h-6 bg-white border border-[var(--color-hairline)] rounded-full items-center justify-center text-[var(--color-ink-faint)] hover:text-[var(--color-primary)] hover:border-[var(--color-primary)] transition-colors z-10"
            :class="isRtl ? '-left-3' : '-right-3'"
            :title="isCollapsed ? t('common.view') : t('common.cancel')"
            :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            @click="emit('toggle-collapse')"
        >
            <i
                :class="[
                    isCollapsed ? 'fas fa-chevron-left' : 'fas fa-chevron-right',
                    'text-[10px] rtl-flip',
                ]"
                aria-hidden="true"
            ></i>
        </button>
    </aside>
</template>
