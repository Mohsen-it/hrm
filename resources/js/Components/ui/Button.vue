<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'cream', 'dark', 'on-cream', 'link', 'danger', 'success', 'warning', 'ghost', 'icon'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
    },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    pressed: { type: Boolean, default: false },
    href: { type: String, default: null },
    icon: { type: String, default: null },
    iconPosition: { type: String, default: 'start' },
    block: { type: Boolean, default: false },
    ariaLabel: { type: String, default: null },
    dir: { type: String, default: 'rtl' },
});

defineEmits(['click']);

const sizeClasses = computed(() => {
    if (props.variant === 'icon') {
        return { xs: 'w-7 h-7', sm: 'w-8 h-8', md: 'w-9 h-9', lg: 'w-10 h-10' }[props.size] || 'w-9 h-9';
    }
    return {
        xs: 'h-7 text-[11px] px-2.5 gap-1.5',
        sm: 'h-8 text-xs px-3 gap-1.5',
        md: 'h-9 text-[13px] px-4 gap-2',
        lg: 'h-10 text-sm px-5 gap-2',
    }[props.size] || 'h-9 text-[13px] px-4 gap-2';
});

const variantClasses = computed(() => {
    const map = {
        primary: [
            'bg-mistral-primary text-white',
            'hover:bg-mistral-primary-deep hover:shadow-md',
            'active:bg-mistral-primary-deep active:shadow-sm',
            'focus-visible:outline-mistral-primary',
            'dark:bg-mistral-primary dark:hover:bg-mistral-primary-deep',
        ].join(' '),
        secondary: [
            'bg-white text-mistral-ink border border-mistral-hairline-strong shadow-sm',
            'hover:bg-mistral-surface hover:border-mistral-stone hover:shadow-md',
            'active:bg-mistral-hairline-soft active:shadow-sm',
            'focus-visible:outline-mistral-primary',
            'dark:bg-mistral-ink dark:text-white dark:border-mistral-slate dark:hover:bg-mistral-charcoal',
        ].join(' '),
        cream: [
            'bg-mistral-cream text-mistral-ink border border-mistral-beige-deep',
            'hover:bg-mistral-cream-deeper',
            'active:bg-mistral-beige-deep',
            'focus-visible:outline-mistral-primary',
        ].join(' '),
        dark: [
            'bg-mistral-ink text-white shadow-sm',
            'hover:bg-mistral-charcoal hover:shadow-md',
            'active:bg-mistral-charcoal active:shadow-sm',
            'focus-visible:outline-mistral-primary',
            'dark:bg-white dark:text-mistral-ink dark:hover:bg-mistral-surface',
        ].join(' '),
        'on-cream': [
            'bg-white text-mistral-ink border border-mistral-beige-deep',
            'hover:bg-mistral-surface',
            'active:bg-mistral-hairline-soft',
            'focus-visible:outline-mistral-primary',
        ].join(' '),
        link: [
            'bg-transparent text-mistral-primary hover:underline',
            'active:text-mistral-primary-deep',
            'focus-visible:outline-mistral-primary',
            'px-0 h-auto',
        ].join(' '),
        danger: [
            'bg-mistral-danger text-white shadow-sm',
            'hover:bg-red-700 hover:shadow-md',
            'active:bg-red-800 active:shadow-sm',
            'focus-visible:outline-mistral-danger',
            'dark:bg-mistral-danger dark:hover:bg-red-700',
        ].join(' '),
        success: [
            'bg-mistral-success text-white shadow-sm',
            'hover:bg-green-700 hover:shadow-md',
            'active:bg-green-800 active:shadow-sm',
            'focus-visible:outline-mistral-success',
            'dark:bg-mistral-success dark:hover:bg-green-700',
        ].join(' '),
        warning: [
            'bg-mistral-warning text-white shadow-sm',
            'hover:bg-amber-700 hover:shadow-md',
            'active:bg-amber-800 active:shadow-sm',
            'focus-visible:outline-mistral-warning',
            'dark:bg-mistral-warning dark:hover:bg-amber-700',
        ].join(' '),
        ghost: [
            'bg-transparent text-mistral-steel',
            'hover:bg-mistral-surface hover:text-mistral-ink',
            'active:bg-mistral-hairline-soft',
            'focus-visible:outline-mistral-primary',
            'dark:text-mistral-on-dark-muted dark:hover:bg-white/10 dark:hover:text-white',
        ].join(' '),
        icon: [
            'bg-transparent text-mistral-steel',
            'hover:bg-mistral-surface hover:text-mistral-ink',
            'active:bg-mistral-hairline-soft',
            'focus-visible:outline-mistral-primary',
            'dark:text-mistral-on-dark-muted dark:hover:bg-white/10 dark:hover:text-white',
        ].join(' '),
    };
    return map[props.variant] || map.primary;
});

const pressedClass = computed(() => {
    if (!props.pressed) return '';
    return 'ring-2 ring-mistral-primary/40 shadow-inner';
});

const radiusClass = computed(() => {
    if (props.variant === 'link') return '';
    return 'rounded-lg';
});

const blockClass = computed(() => (props.block ? 'w-full' : ''));

const isDisabled = computed(() => props.disabled || props.loading);

const loadingSpinnerColor = computed(() => {
    const lightBg = ['secondary', 'cream', 'on-cream', 'ghost', 'icon', 'link'];
    if (lightBg.includes(props.variant)) return 'primary';
    return 'white';
});

const buttonClass = computed(() => [
    'inline-flex items-center justify-center font-medium leading-tight cursor-pointer transition-all duration-150',
    'focus-visible:outline-2 focus-visible:outline-offset-2',
    'disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none disabled:pointer-events-none',
    sizeClasses.value,
    variantClasses.value,
    radiusClass.value,
    blockClass.value,
    pressedClass.value,
]);
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="buttonClass"
        :aria-disabled="isDisabled"
        :aria-busy="loading"
        :aria-pressed="pressed || undefined"
        :aria-label="ariaLabel"
        :dir="dir"
    >
        <LoadingSpinner v-if="loading" size="sm" :color="loadingSpinnerColor" />
        <i v-else-if="icon && iconPosition === 'start'" :class="icon" aria-hidden="true"></i>
        <slot />
        <i v-if="!loading && icon && iconPosition === 'end'" :class="icon" aria-hidden="true"></i>
    </Link>
    <button
        v-else
        :type="type"
        :class="buttonClass"
        :disabled="isDisabled"
        :aria-busy="loading"
        :aria-pressed="pressed || undefined"
        :aria-label="ariaLabel"
        :dir="dir"
        @click="$emit('click', $event)"
    >
        <LoadingSpinner v-if="loading" size="sm" :color="loadingSpinnerColor" />
        <i v-else-if="icon && iconPosition === 'start'" :class="icon" aria-hidden="true"></i>
        <slot />
        <i v-if="!loading && icon && iconPosition === 'end'" :class="icon" aria-hidden="true"></i>
    </button>
</template>
