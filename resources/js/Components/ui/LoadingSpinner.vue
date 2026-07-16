<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: { type: String, default: 'md' },
    color: { type: String, default: 'primary' },
    dir: { type: String, default: 'rtl' },
});

const sizeClass = computed(() => {
    return { sm: 'w-4 h-4 border-2', md: 'w-6 h-6 border-2', lg: 'w-10 h-10 border-4' }[props.size] || 'w-6 h-6 border-2';
});

const colorClass = computed(() => {
    return { primary: 'border-mistral-primary', ink: 'border-mistral-ink', muted: 'border-mistral-muted' }[props.color] || 'border-mistral-primary';
});
</script>

<template>
    <span
        :class="['inline-block rounded-full border-t-transparent animate-spin', sizeClass, colorClass]"
        role="status"
        :aria-label="dir === 'rtl' ? 'جار التحميل' : 'Loading'"
        :dir="dir"
    ></span>
</template>
