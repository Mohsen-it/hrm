<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: [String, Number], required: true },
    variant: { type: String, default: 'active' },
    dot: { type: Boolean, default: false },
    dir: { type: String, default: 'rtl' },
});

const variantClasses = computed(() => {
    const map = {
        orange: 'bg-mistral-primary text-mistral-on-primary',
        cream: 'bg-mistral-cream-deeper text-mistral-ink',
        dark: 'bg-mistral-ink text-mistral-on-dark',
        active: 'bg-mistral-success-bg text-mistral-success',
        inactive: 'bg-mistral-surface text-mistral-status-inactive',
        pending: 'bg-mistral-warning-bg text-mistral-warning',
        danger: 'bg-mistral-danger-bg text-mistral-danger',
        absent: 'bg-mistral-danger-bg text-mistral-status-absent',
        info: 'bg-mistral-info-bg text-mistral-info',
        overtime: 'bg-mistral-info-bg text-mistral-status-overtime',
        vacation: 'bg-mistral-info-bg text-mistral-status-vacation',
    };
    return map[props.variant] || map.info;
});

const dotClasses = computed(() => {
    const map = {
        orange: 'bg-mistral-on-primary',
        cream: 'bg-mistral-ink',
        dark: 'bg-mistral-on-dark',
        active: 'bg-mistral-success',
        inactive: 'bg-mistral-status-inactive',
        pending: 'bg-mistral-warning',
        danger: 'bg-mistral-danger',
        absent: 'bg-mistral-status-absent',
        info: 'bg-mistral-info',
        overtime: 'bg-mistral-status-overtime',
        vacation: 'bg-mistral-status-vacation',
    };
    return map[props.variant] || map.info;
});
</script>

<template>
    <span
        :class="['inline-flex items-center h-[22px] px-2.5 rounded-full text-[12px] font-semibold leading-tight', variantClasses]"
        :dir="dir"
    >
        <span v-if="dot" :class="['w-2 h-2 rounded-full mx-1', dotClasses]"></span>
        <span class="mx-1">{{ text }}</span>
    </span>
</template>
