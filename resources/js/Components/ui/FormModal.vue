<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Card from './Card.vue';
import Button from './Button.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' },
    closeOnBackdrop: { type: Boolean, default: true },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'close']);

const isOpen = computed(() => props.modelValue);

const sizeClass = computed(() => {
    return {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
    }[props.size] || 'max-w-md';
});

function close() {
    emit('update:modelValue', false);
    emit('close');
}

function onBackdropClick() {
    if (props.closeOnBackdrop) close();
}
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 max-sm:p-0" :dir="dir">
            <div class="modal-overlay absolute inset-0" @click="onBackdropClick"></div>
            <Card variant="base" padding="none" :class="['relative w-full shadow-level-4 z-10 max-sm:rounded-none max-sm:max-w-full max-sm:max-h-full max-sm:overflow-y-auto', sizeClass]">
                <div v-if="title || $slots.header" class="flex items-center justify-between px-6 py-4 border-b border-mistral-hairline-soft">
                    <h3 class="text-[18px] font-semibold text-mistral-ink">
                        <slot name="header">{{ title }}</slot>
                    </h3>
                    <button
                        type="button"
                        :class="['w-8 h-8 flex items-center justify-center rounded-md text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface transition-colors', dir === 'rtl' ? 'me-[-8px]' : 'ms-[-8px]']"
                        :aria-label="dir === 'rtl' ? 'إغلاق' : 'Close'"
                        @click="close"
                    >
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </Button>
                </div>
                <div class="p-6">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="px-6 py-4 border-t border-mistral-hairline-soft flex items-center justify-end gap-2">
                    <slot name="footer" />
                </div>
            </Card>
        </div>
    </Teleport>
</template>
