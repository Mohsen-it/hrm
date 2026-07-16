<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'cream', 'dark', 'on-cream', 'link', 'danger', 'ghost', 'icon'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
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
        return { sm: 'w-8 h-8', md: 'w-10 h-10', lg: 'w-11 h-11' }[props.size] || 'w-10 h-10';
    }
    return { sm: 'h-8 text-xs px-3', md: 'h-10 text-[13px] px-5', lg: 'h-11 text-sm px-6' }[props.size] || 'h-10 text-[13px] px-5';
});

const variantClasses = computed(() => {
    const map = {
        primary: 'bg-mistral-primary text-mistral-on-primary hover:bg-mistral-primary-deep active:bg-mistral-primary-deep disabled:bg-mistral-hairline disabled:text-mistral-muted',
        secondary: 'bg-mistral-canvas text-mistral-ink border border-mistral-hairline-strong hover:bg-mistral-surface disabled:opacity-50',
        cream: 'bg-mistral-cream text-mistral-ink border border-mistral-beige-deep hover:bg-mistral-cream-deeper disabled:opacity-50',
        dark: 'bg-mistral-ink text-mistral-on-dark hover:bg-mistral-charcoal disabled:opacity-50',
        'on-cream': 'bg-mistral-canvas text-mistral-ink border border-mistral-beige-deep hover:bg-mistral-surface disabled:opacity-50',
        link: 'bg-transparent text-mistral-primary hover:underline px-0 h-auto',
        danger: 'bg-mistral-danger text-mistral-on-primary hover:bg-mistral-primary-deep disabled:opacity-50',
        ghost: 'bg-transparent text-mistral-ink hover:bg-mistral-surface disabled:opacity-50',
        icon: 'bg-transparent text-mistral-ink hover:bg-mistral-surface disabled:opacity-50',
    };
    return map[props.variant] || map.primary;
});

const radiusClass = computed(() => {
    if (props.variant === 'link') return '';
    if (props.variant === 'icon') return 'rounded-md';
    return 'rounded-md';
});

const blockClass = computed(() => (props.block ? 'w-full' : ''));

const isDisabled = computed(() => props.disabled || props.loading);

const buttonClass = computed(() => [
    'inline-flex items-center justify-center gap-2 font-medium leading-tight cursor-pointer transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-mistral-primary focus-visible:outline-offset-2',
    sizeClasses.value,
    variantClasses.value,
    radiusClass.value,
    blockClass.value,
    isDisabled.value ? 'cursor-not-allowed opacity-50' : '',
]);
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="buttonClass"
        :aria-disabled="isDisabled"
        :aria-busy="loading"
        :aria-label="ariaLabel"
        :dir="dir"
    >
        <LoadingSpinner v-if="loading" size="sm" />
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
        :aria-label="ariaLabel"
        :dir="dir"
        @click="$emit('click', $event)"
    >
        <LoadingSpinner v-if="loading" size="sm" />
        <i v-else-if="icon && iconPosition === 'start'" :class="icon" aria-hidden="true"></i>
        <slot />
        <i v-if="!loading && icon && iconPosition === 'end'" :class="icon" aria-hidden="true"></i>
    </button>
</template>
