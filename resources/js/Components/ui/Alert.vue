<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'info' },
    message: { type: String, required: true },
    dismissible: { type: Boolean, default: false },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['dismiss']);

const alertClass = computed(() => {
    const map = {
        success: 'bg-mistral-success-bg border-mistral-success text-mistral-success',
        danger: 'bg-mistral-danger-bg border-mistral-danger text-mistral-danger',
        error: 'bg-mistral-danger-bg border-mistral-danger text-mistral-danger',
        warning: 'bg-mistral-warning-bg border-mistral-warning text-mistral-warning',
        info: 'bg-mistral-info-bg border-mistral-info text-mistral-info',
    };
    return map[props.type] || map.info;
});

const iconClass = computed(() => {
    const map = {
        success: 'fas fa-check-circle',
        danger: 'fas fa-exclamation-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle',
    };
    return map[props.type] || map.info;
});

function dismiss() {
    emit('dismiss');
}
</script>

<template>
    <div
        v-if="message"
        :class="['flex items-center justify-between gap-3 px-4 py-3 rounded-md border-s-4 text-sm', alertClass]"
        role="alert"
        :dir="dir"
    >
        <div class="flex items-center gap-2">
            <i :class="iconClass" aria-hidden="true"></i>
            <span>{{ message }}</span>
        </div>
        <button
            v-if="dismissible"
            type="button"
            class="opacity-70 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-current focus-visible:outline-offset-2 rounded"
            :aria-label="dir === 'rtl' ? 'إغلاق' : 'Dismiss'"
            @click="dismiss"
        >
            <i class="fas fa-times" aria-hidden="true"></i>
        </Button>
    </div>
</template>
