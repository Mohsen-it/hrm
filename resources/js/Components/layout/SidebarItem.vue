<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    isCollapsed: {
        type: Boolean,
        default: false,
    },
    isActive: {
        type: Boolean,
        default: false,
    },
    level: {
        type: Number,
        default: 0,
    },
});

const { t } = useTranslations();

const href = computed(() => {
    if (typeof window === 'undefined' || typeof window.route !== 'function') {
        return '#';
    }
    try {
        return window.route(props.item.route);
    } catch (_e) {
        return '#';
    }
});

const paddingClass = computed(() => {
    if (props.isCollapsed) return 'justify-center';
    return props.level > 0 ? 'pr-10' : 'px-3';
});
</script>

<template>
    <Link
        :href="href"
        :class="[
            'sidebar-item',
            { 'sidebar-item-active': isActive },
            paddingClass,
        ]"
        :title="isCollapsed ? t(item.label) : ''"
        :aria-current="isActive ? 'page' : null"
    >
        <i
            v-if="item.icon"
            :class="[item.icon, 'w-5 text-center shrink-0 text-[14px]']"
            aria-hidden="true"
        ></i>
        <span v-if="!isCollapsed" class="truncate flex-1">{{ t(item.label) }}</span>
        <span
            v-if="!isCollapsed && item.badge"
            class="sidebar-item-badge"
            aria-label="notifications"
        >{{ item.badge }}</span>
    </Link>
</template>
