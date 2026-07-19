<script setup>
import { computed, watch } from 'vue';
import Card from './Card.vue';
import Button from './Button.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, required: true },
    message: { type: String, required: true },
    confirmText: { type: String, default: null },
    cancelText: { type: String, default: null },
    confirmVariant: { type: String, default: 'danger' },
    icon: { type: String, default: 'fas fa-triangle-exclamation' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel']);

const isOpen = computed(() => props.modelValue);

function close() {
    emit('update:modelValue', false);
}

function confirm() {
    emit('confirm');
    close();
}

function cancel() {
    emit('cancel');
    close();
}

watch(isOpen, (val) => {
    if (val) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
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
            <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" :dir="dir">
                <div class="absolute inset-0 bg-mistral-ink/40 backdrop-blur-sm" @click="cancel"></div>
                <Transition
                    enter-active-class="duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <Card
                        v-if="isOpen"
                        variant="base"
                        padding="none"
                        class="relative max-w-md w-full shadow-level-4 z-10"
                    >
                        <div class="p-6 text-center">
                            <div
                                :class="[
                                    'w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4',
                                    confirmVariant === 'danger' ? 'bg-mistral-danger/10' : confirmVariant === 'success' ? 'bg-mistral-success/10' : 'bg-mistral-warning/10',
                                ]"
                            >
                                <i
                                    :class="[
                                        icon,
                                        'text-[20px]',
                                        confirmVariant === 'danger' ? 'text-mistral-danger' : confirmVariant === 'success' ? 'text-mistral-success' : 'text-mistral-warning',
                                    ]"
                                    aria-hidden="true"
                                ></i>
                            </div>
                            <h3 class="text-[16px] font-semibold text-mistral-ink mb-2">
                                {{ title }}
                            </h3>
                            <p class="text-[13px] text-mistral-steel leading-relaxed">
                                {{ message }}
                            </p>
                        </div>
                        <div class="px-6 py-4 border-t border-mistral-hairline-soft flex items-center justify-center gap-3 bg-mistral-surface/30 rounded-b-xl">
                            <Button variant="secondary" class="flex-1" @click="cancel">
                                {{ cancelText || t('common.cancel') }}
                            </Button>
                            <Button :variant="confirmVariant" class="flex-1" @click="confirm">
                                {{ confirmText || t('common.confirm') }}
                            </Button>
                        </div>
                    </Card>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
