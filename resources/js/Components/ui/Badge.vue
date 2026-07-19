<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: [String, Number], required: true },
    variant: { type: String, default: 'active' },
    dot: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
    dir: { type: String, default: 'rtl' },
});

const variantClasses = computed(() => {
    const map = {
        orange: 'bg-mistral-primary/10 text-mistral-primary',
        cream: 'bg-mistral-cream-deeper text-mistral-ink',
        dark: 'bg-mistral-ink text-white',
        active: 'bg-mistral-success/10 text-mistral-success',
        inactive: 'bg-mistral-surface text-mistral-stone',
        pending: 'bg-mistral-warning/10 text-mistral-warning',
        danger: 'bg-mistral-danger/10 text-mistral-danger',
        absent: 'bg-mistral-danger/10 text-mistral-danger',
        info: 'bg-mistral-info/10 text-mistral-info',
        overtime: 'bg-mistral-info/10 text-mistral-info',
        vacation: 'bg-mistral-info/10 text-mistral-info',
    };
    return map[props.variant] || map.info;
});

const dotClasses = computed(() => {
    const map = {
        orange: 'bg-mistral-primary',
        cream: 'bg-mistral-ink',
        dark: 'bg-white',
        active: 'bg-mistral-success',
        inactive: 'bg-mistral-stone',
        pending: 'bg-mistral-warning',
        danger: 'bg-mistral-danger',
        absent: 'bg-mistral-danger',
        info: 'bg-mistral-info',
        overtime: 'bg-mistral-info',
        vacation: 'bg-mistral-info',
    };
    return map[props.variant] || map.info;
});

const sizeClasses = computed(() => {
    return {
        sm: 'h-5 text-[10px] px-1.5',
        md: 'h-5.5 text-[11px] px-2',
        lg: 'h-6 text-[12px] px-2.5',
    }[props.size] || 'h-5.5 text-[11px] px-2';
});
</script>

<template>
    <span
        :class="['inline-flex items-center gap-1 rounded-full font-medium leading-none whitespace-nowrap', variantClasses, sizeClasses]"
        :dir="dir"
    >
        <span v-if="dot" :class="['w-1.5 h-1.5 rounded-full shrink-0', dotClasses]"></span>
        {{ text }}
    </span>
</template>
