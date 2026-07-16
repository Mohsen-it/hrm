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
        sm: 'w-6 h-6 text-[10px]',
        md: 'w-8 h-8 text-[12px]',
        lg: 'w-10 h-10 text-[14px]',
        xl: 'w-14 h-14 text-[18px]',
    }[props.size] || 'w-8 h-8 text-[12px]';
});

const initials = computed(() => {
    if (!props.name) return '?';
    const parts = props.name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});
</script>

<template>
    <div :class="['inline-flex items-center justify-center rounded-full bg-mistral-cream text-mistral-ink font-bold overflow-hidden shrink-0', sizeClass]" :dir="dir">
        <img v-if="src" :src="src" :alt="name" class="w-full h-full object-cover" />
        <span v-else>{{ initials }}</span>
    </div>
</template>
