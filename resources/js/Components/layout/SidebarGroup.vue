<script setup>
import { computed, ref, watch } from 'vue';
import SidebarItem from './SidebarItem.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    group: {
        type: Object,
        required: true,
    },
    isCollapsed: {
        type: Boolean,
        default: false,
    },
    activeRoute: {
        type: String,
        default: '',
    },
});

const { t } = useTranslations();

const hasMultipleItems = computed(() => props.group.items.length > 1);

const isGroupActive = computed(() =>
    props.group.items.some((it) => {
        if (!props.activeRoute) return false;
        return it.route === props.activeRoute || it.route === props.activeRoute.split('.')[0];
    }),
);

const isExpanded = ref(isGroupActive.value);

watch(
    () => props.activeRoute,
    () => {
        if (isGroupActive.value) {
            isExpanded.value = true;
        }
    },
);

function toggle() {
    isExpanded.value = !isExpanded.value;
}

function isItemActive(routeName) {
    if (!routeName || !props.activeRoute) return false;
    if (routeName === 'dashboard') {
        return props.activeRoute === '/' || props.activeRoute === 'dashboard';
    }
    return (
        routeName === props.activeRoute ||
        routeName === props.activeRoute.split('.')[0]
    );
}
</script>

<template>
    <div class="sidebar-group">
        <button
            v-if="!isCollapsed && hasMultipleItems"
            type="button"
            class="sidebar-section-title w-full flex items-center justify-between hover:text-[var(--color-ink-mute)] transition-colors"
            :aria-expanded="isExpanded"
            @click="toggle"
        >
            <span class="flex items-center gap-2">
                <i v-if="group.icon" :class="[group.icon, 'text-[12px]']" aria-hidden="true"></i>
                <span>{{ t(group.section) }}</span>
            </span>
            <i
                :class="[
                    isExpanded ? 'fas fa-chevron-down' : 'fas fa-chevron-up',
                    'text-[10px] transition-transform',
                ]"
                aria-hidden="true"
            ></i>
        </button>
        <div
            v-else-if="!isCollapsed"
            class="sidebar-section-title flex items-center gap-2"
        >
            <i v-if="group.icon" :class="[group.icon, 'text-[12px]']" aria-hidden="true"></i>
            <span>{{ t(group.section) }}</span>
        </div>
        <div v-else class="h-px bg-[var(--color-hairline)] my-2 mx-2"></div>

        <div
            v-show="!isCollapsed ? isExpanded || isGroupActive : true"
            class="space-y-1"
        >
            <SidebarItem
                v-for="item in group.items"
                :key="item.route"
                :item="item"
                :is-collapsed="isCollapsed"
                :is-active="isItemActive(item.route)"
                :level="0"
            />
        </div>
    </div>
</template>
