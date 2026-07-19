<script setup>
import { computed, watch, onMounted, onUnmounted } from 'vue';
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
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
        full: 'max-w-6xl',
    }[props.size] || 'max-w-lg';
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

watch(isOpen, (val) => {
    if (val) {
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onEsc);
    } else {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onEsc);
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
                        variant="base"
                        padding="none"
                        :class="['relative w-full shadow-level-4 z-10 max-sm:rounded-none max-sm:max-w-full max-sm:max-h-full max-sm:overflow-y-auto', sizeClass]"
                    >
                        <div v-if="title || $slots.header" class="flex items-center justify-between px-6 py-4 border-b border-mistral-hairline-soft">
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
                        <div class="p-6">
                            <slot />
                        </div>
                        <div v-if="$slots.footer" class="px-6 py-4 border-t border-mistral-hairline-soft flex items-center justify-end gap-2 bg-mistral-surface/30 rounded-b-xl">
                            <slot name="footer" />
                        </div>
                    </Card>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
