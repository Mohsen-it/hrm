<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Card from './Card.vue';
import Button from './Button.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, required: true },
    message: { type: String, required: true },
    confirmText: { type: String, default: 'تأكيد' },
    cancelText: { type: String, default: 'إلغاء' },
    confirmVariant: { type: String, default: 'danger' },
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
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" :dir="dir">
            <div class="modal-overlay absolute inset-0" @click="cancel"></div>
            <Card variant="base" padding="lg" class="relative max-w-md w-full shadow-level-4 z-10">
                <h3 class="text-[18px] font-semibold text-mistral-ink mb-2">
                    {{ title }}
                </h3>
                <p class="text-[14px] text-mistral-steel mb-6">
                    {{ message }}
                </p>
                <div class="flex items-center justify-end gap-2">
                    <Button variant="secondary" @click="cancel">
                        {{ cancelText }}
                    </Button>
                    <Button :variant="confirmVariant" @click="confirm">
                        {{ confirmText }}
                    </Button>
                </div>
            </Card>
        </div>
    </Teleport>
</template>
