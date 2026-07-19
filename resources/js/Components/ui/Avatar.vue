<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    src: { type: String, default: null },
    size: { type: String, default: 'md' },
    dir: { type: String, default: 'rtl' },
});

const sizeClass = computed(() => {
    return {
        xs: 'w-6 h-6 text-[9px]',
        sm: 'w-8 h-8 text-[11px]',
        md: 'w-9 h-9 text-[12px]',
        lg: 'w-11 h-11 text-[14px]',
        xl: 'w-14 h-14 text-[18px]',
    }[props.size] || 'w-9 h-9 text-[12px]';
});

const initials = computed(() => {
    if (!props.name) return '?';
    const parts = props.name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});
</script>

<template>
    <div
        :class="[
            'inline-flex items-center justify-center rounded-full bg-mistral-cream text-mistral-ink font-semibold overflow-hidden shrink-0 ring-2 ring-white',
            sizeClass,
        ]"
        :dir="dir"
    >
        <img v-if="src" :src="src" :alt="name" class="w-full h-full object-cover" loading="lazy" />
        <span v-else>{{ initials }}</span>
    </div>
</template>
