<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    type: { type: String, default: 'info' },
    message: { type: String, required: true },
    dismissible: { type: Boolean, default: false },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['dismiss']);

const visible = ref(true);

const alertClass = computed(() => {
    const map = {
        success: 'bg-mistral-success/8 border-mistral-success/30 text-mistral-success',
        danger: 'bg-mistral-danger/8 border-mistral-danger/30 text-mistral-danger',
        error: 'bg-mistral-danger/8 border-mistral-danger/30 text-mistral-danger',
        warning: 'bg-mistral-warning/8 border-mistral-warning/30 text-mistral-warning',
        info: 'bg-mistral-info/8 border-mistral-info/30 text-mistral-info',
    };
    return map[props.type] || map.info;
});

const iconClass = computed(() => {
    const map = {
        success: 'fas fa-circle-check',
        danger: 'fas fa-circle-exclamation',
        error: 'fas fa-circle-exclamation',
        warning: 'fas fa-triangle-exclamation',
        info: 'fas fa-circle-info',
    };
    return map[props.type] || map.info;
});

function dismiss() {
    visible.value = false;
    emit('dismiss');
}
</script>

<template>
    <Transition
        enter-active-class="duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
    >
        <div
            v-if="visible && message"
            :class="['flex items-center justify-between gap-3 px-4 py-3 rounded-lg border text-sm font-medium', alertClass]"
            role="alert"
            :dir="dir"
        >
            <div class="flex items-center gap-2.5">
                <i :class="[iconClass, 'text-[14px]']" aria-hidden="true"></i>
                <span>{{ message }}</span>
            </div>
            <button
                v-if="dismissible"
                type="button"
                class="shrink-0 opacity-60 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-current focus-visible:outline-offset-2 rounded transition-opacity"
                :aria-label="dir === 'rtl' ? 'إغلاق' : 'Dismiss'"
                @click="dismiss"
            >
                <i class="fas fa-xmark text-[14px]" aria-hidden="true"></i>
            </button>
        </div>
    </Transition>
</template>
