<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

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
    href: { type: String, default: null },
    dir: { type: String, default: 'rtl' },
});

defineEmits(['click']);

const sizeClasses = computed(() => {
    return { sm: 'w-8 h-8', md: 'w-9 h-9', lg: 'w-11 h-11' }[props.size] || 'w-9 h-9';
});

const variantClasses = computed(() => {
    const map = {
        primary: 'bg-mistral-primary/10 text-mistral-primary hover:bg-mistral-primary hover:text-mistral-on-primary',
        secondary: 'bg-mistral-canvas text-mistral-ink border border-mistral-hairline-strong hover:bg-mistral-surface',
        ghost: 'bg-mistral-surface text-mistral-steel hover:bg-mistral-hairline-soft hover:text-mistral-ink',
        info: 'bg-mistral-info/10 text-mistral-info hover:bg-mistral-info hover:text-mistral-on-primary',
        success: 'bg-mistral-success/10 text-mistral-success hover:bg-mistral-success hover:text-mistral-on-primary',
        warning: 'bg-mistral-primary/10 text-mistral-primary hover:bg-mistral-primary hover:text-mistral-on-primary',
        danger: 'bg-mistral-danger/10 text-mistral-danger hover:bg-mistral-danger hover:text-mistral-on-primary',
    };
    return map[props.variant] || map.ghost;
});

const isDisabled = computed(() => props.disabled);

const buttonClass = computed(() => [
    'inline-flex items-center justify-center rounded-md cursor-pointer transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-mistral-primary focus-visible:outline-offset-2',
    sizeClasses.value,
    variantClasses.value,
    isDisabled.value ? 'cursor-not-allowed opacity-50' : '',
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="buttonClass" :aria-label="ariaLabel" :aria-disabled="isDisabled" :dir="dir" :preserve-state="false">
        <i :class="icon" aria-hidden="true"></i>
    </Link>
    <button
        v-else
        type="button"
        :class="buttonClass"
        :disabled="isDisabled"
        :aria-label="ariaLabel"
        :dir="dir"
        @click="$emit('click', $event)"
    >
        <i :class="icon" aria-hidden="true"></i>
    </Button>
</template>
