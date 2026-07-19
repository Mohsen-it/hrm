<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: { type: String, default: 'md' },
    color: { type: String, default: 'primary' },
    dir: { type: String, default: 'rtl' },
});

const sizeClass = computed(() => {
    return {
        xs: 'w-3 h-3 border-[1.5px]',
        sm: 'w-4 h-4 border-2',
        md: 'w-5 h-5 border-2',
        lg: 'w-8 h-8 border-[3px]',
    }[props.size] || 'w-5 h-5 border-2';
});

const colorClass = computed(() => {
    return {
        primary: 'border-mistral-primary/30 border-t-mistral-primary',
        current: 'border-current/30 border-t-current',
        ink: 'border-mistral-ink/30 border-t-mistral-ink',
        muted: 'border-mistral-muted/30 border-t-mistral-muted',
        white: 'border-white/30 border-t-white',
        success: 'border-mistral-success/30 border-t-mistral-success',
        warning: 'border-mistral-warning/30 border-t-mistral-warning',
        danger: 'border-mistral-danger/30 border-t-mistral-danger',
        info: 'border-mistral-info/30 border-t-mistral-info',
    }[props.color] || 'border-mistral-primary/30 border-t-mistral-primary';
});
</script>

<template>
    <span
        :class="['inline-block rounded-full animate-spin', sizeClass, colorClass]"
        role="status"
        :aria-label="dir === 'rtl' ? 'جار التحميل' : 'Loading'"
        :dir="dir"
    ></span>
</template>
