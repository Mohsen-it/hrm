<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import Card from './Card.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' },
    closeOnBackdrop: { type: Boolean, default: true },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'close']);

const isOpen = computed(() => props.modelValue);
const panelRef = ref(null);
let previouslyFocusedElement = null;

const sizeClass = computed(() => {
    return {
        sm: 'max-w-md',
        md: 'max-w-2xl',
        lg: 'max-w-4xl',
        xl: 'max-w-6xl',
        full: 'max-w-[calc(100vw-2rem)]',
    }[props.size] || 'max-w-2xl';
});

function close() {
    emit('update:modelValue', false);
    emit('close');
}

function onBackdropClick() {
    if (props.closeOnBackdrop) close();
}

function onEsc(e) {
    if (e.key === 'Escape' && isOpen.value) close();
}

watch(isOpen, async (val) => {
    if (val) {
        previouslyFocusedElement = document.activeElement;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onEsc);
        await nextTick();
        panelRef.value?.$el?.focus();
    } else {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onEsc);
        previouslyFocusedElement?.focus?.();
        previouslyFocusedElement = null;
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onEsc);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 max-sm:p-0" :dir="dir">
                <div class="absolute inset-0 bg-mistral-ink/40 backdrop-blur-sm" @click="onBackdropClick"></div>
                <Transition
                    enter-active-class="duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2"
                >
                    <Card
                        v-if="isOpen"
                        ref="panelRef"
                        variant="base"
                        padding="none"
                        :dir="dir"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="title || 'Dialog'"
                        tabindex="-1"
                        :class="['relative z-10 flex w-full max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl shadow-level-4 max-sm:max-h-[100dvh] max-sm:max-w-full max-sm:rounded-none', sizeClass]"
                    >
                        <div v-if="title || $slots.header" class="flex shrink-0 items-center justify-between border-b border-mistral-hairline-soft px-6 py-5 max-sm:px-5">
                            <h3 class="text-[16px] font-semibold text-mistral-ink">
                                <slot name="header">{{ title }}</slot>
                            </h3>
                            <button
                                type="button"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface transition-colors"
                                :aria-label="dir === 'rtl' ? 'إغلاق' : 'Close'"
                                @click="close"
                            >
                                <i class="fas fa-xmark text-[14px]" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto p-6 max-sm:p-5">
                            <slot />
                        </div>
                        <div v-if="$slots.footer" class="flex shrink-0 items-center justify-end gap-2 border-t border-mistral-hairline-soft bg-mistral-surface/30 px-6 py-4 max-sm:px-5">
                            <slot name="footer" />
                        </div>
                    </Card>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
