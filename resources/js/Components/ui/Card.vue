<script setup>
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    variant: {
        type: String,
        default: 'base',
        validator: (v) => ['base', 'feature', 'cream', 'cream-soft', 'feature-product', 'stat'].includes(v),
    },
    padding: {
        type: String,
        default: 'md',
        validator: (v) => ['none', 'xs', 'sm', 'md', 'lg', 'xl'].includes(v),
    },
    bordered: { type: Boolean, default: true },
    hoverable: { type: Boolean, default: false },
    as: { type: String, default: 'div' },
    dir: { type: String, default: 'rtl' },
});

const variantClasses = computed(() => {
    const map = {
        base: 'bg-white border border-mistral-hairline-soft',
        feature: 'bg-white border border-mistral-hairline-soft',
        cream: 'bg-mistral-cream text-mistral-ink border border-mistral-beige-deep',
        'cream-soft': 'bg-mistral-surface-cream-soft text-mistral-ink',
        'feature-product': 'bg-white border border-mistral-hairline-soft shadow-level-2',
        stat: 'bg-white border border-mistral-hairline-soft',
    };
    return map[props.variant] || map.base;
});

const paddingClasses = computed(() => {
    const map = {
        none: 'p-0',
        xs: 'p-2',
        sm: 'p-3 sm:p-4',
        md: 'p-4 sm:p-5',
        lg: 'p-5 sm:p-6',
        xl: 'p-6 sm:p-8',
    };
    return map[props.padding] || map.md;
});

const hoverClass = computed(() => (props.hoverable ? 'hover:shadow-level-2 transition-shadow duration-200' : ''));

const borderClass = computed(() => (props.bordered ? '' : 'border-0'));

const cardClass = computed(() => [
    'rounded-lg',
    variantClasses.value,
    paddingClasses.value,
    borderClass.value,
    hoverClass.value,
]);
</script>

<template>
    <component :is="as" v-bind="$attrs" :class="cardClass" :dir="dir">
        <div v-if="$slots.header" class="mb-4 pb-4 border-b border-mistral-hairline-soft">
            <slot name="header" />
        </div>
        <slot />
        <div v-if="$slots.footer" class="mt-4 pt-4 border-t border-mistral-hairline-soft">
            <slot name="footer" />
        </div>
    </component>
</template>
