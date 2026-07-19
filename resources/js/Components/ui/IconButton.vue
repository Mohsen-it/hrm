<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps({
    icon: { type: String, required: true },
    ariaLabel: { type: String, required: true },
    variant: {
        type: String,
        default: 'ghost',
        validator: (v) => ['primary', 'secondary', 'ghost', 'info', 'success', 'warning', 'danger'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    pressed: { type: Boolean, default: false },
    href: { type: String, default: null },
    dir: { type: String, default: 'rtl' },
});

defineEmits(['click']);

const sizeClasses = computed(() => {
    return {
        sm: 'w-8 h-8',
        md: 'w-9 h-9',
        lg: 'w-10 h-10',
    }[props.size] || 'w-9 h-9';
});

const iconSize = computed(() => {
    return { sm: 'text-xs', md: 'text-sm', lg: 'text-[15px]' }[props.size] || 'text-sm';
});

const variantClasses = computed(() => {
    const map = {
        primary: [
            'text-mistral-primary bg-mistral-primary/8',
            'hover:bg-mistral-primary/15 hover:text-mistral-primary-deep',
            'active:bg-mistral-primary/20',
            'focus-visible:outline-mistral-primary',
            'dark:text-mistral-primary dark:hover:bg-mistral-primary/20',
        ].join(' '),
        secondary: [
            'text-mistral-steel bg-mistral-surface',
            'hover:bg-mistral-hairline-soft hover:text-mistral-ink',
            'active:bg-mistral-hairline',
            'focus-visible:outline-mistral-primary',
            'dark:text-mistral-on-dark-muted dark:hover:bg-white/10 dark:hover:text-white',
        ].join(' '),
        ghost: [
            'text-mistral-steel bg-mistral-surface/60',
            'hover:bg-mistral-hairline-soft hover:text-mistral-ink',
            'active:bg-mistral-hairline',
            'focus-visible:outline-mistral-primary',
            'dark:text-mistral-on-dark-muted dark:hover:bg-white/10 dark:hover:text-white',
        ].join(' '),
        info: [
            'text-mistral-info bg-mistral-info/8',
            'hover:bg-mistral-info/15 hover:text-blue-700',
            'active:bg-mistral-info/20',
            'focus-visible:outline-mistral-info',
            'dark:text-mistral-info dark:hover:bg-mistral-info/20',
        ].join(' '),
        success: [
            'text-mistral-success bg-mistral-success/8',
            'hover:bg-mistral-success/15 hover:text-green-700',
            'active:bg-mistral-success/20',
            'focus-visible:outline-mistral-success',
            'dark:text-mistral-success dark:hover:bg-mistral-success/20',
        ].join(' '),
        warning: [
            'text-mistral-warning bg-mistral-warning/8',
            'hover:bg-mistral-warning/15 hover:text-amber-700',
            'active:bg-mistral-warning/20',
            'focus-visible:outline-mistral-warning',
            'dark:text-mistral-warning dark:hover:bg-mistral-warning/20',
        ].join(' '),
        danger: [
            'text-mistral-danger bg-mistral-danger/8',
            'hover:bg-mistral-danger/15 hover:text-red-700',
            'active:bg-mistral-danger/20',
            'focus-visible:outline-mistral-danger',
            'dark:text-mistral-danger dark:hover:bg-mistral-danger/20',
        ].join(' '),
    };
    return map[props.variant] || map.ghost;
});

const pressedClass = computed(() => {
    if (!props.pressed) return '';
    return 'ring-2 ring-mistral-primary/40';
});

const isDisabled = computed(() => props.disabled || props.loading);

const loadingSpinnerColor = computed(() => {
    return 'current';
});

const buttonClass = computed(() => [
    'inline-flex items-center justify-center rounded-lg cursor-pointer transition-all duration-150',
    'focus-visible:outline-2 focus-visible:outline-offset-2',
    'disabled:opacity-40 disabled:cursor-not-allowed disabled:pointer-events-none',
    sizeClasses.value,
    iconSize.value,
    variantClasses.value,
    pressedClass.value,
]);
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="buttonClass"
        :aria-label="ariaLabel"
        :aria-disabled="isDisabled"
        :aria-busy="loading"
        :aria-pressed="pressed || undefined"
        :dir="dir"
        :preserve-state="false"
    >
        <LoadingSpinner v-if="loading" size="sm" :color="loadingSpinnerColor" />
        <i v-else :class="icon" aria-hidden="true"></i>
    </Link>
    <button
        v-else
        type="button"
        :class="buttonClass"
        :disabled="isDisabled"
        :aria-label="ariaLabel"
        :aria-busy="loading"
        :aria-pressed="pressed || undefined"
        :dir="dir"
        @click="$emit('click', $event)"
    >
        <LoadingSpinner v-if="loading" size="sm" :color="loadingSpinnerColor" />
        <i v-else :class="icon" aria-hidden="true"></i>
    </button>
</template>
