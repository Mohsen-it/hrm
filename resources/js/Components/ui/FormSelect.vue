<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    label: { type: String, default: '' },
    options: { type: Array, required: true },
    placeholder: { type: String, default: 'اختر...' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || props.name || `select-${Math.random().toString(36).slice(2, 9)}`);

function onChange(e) {
    emit('update:modelValue', e.target.value);
}
</script>

<template>
    <div class="w-full text-start" :dir="dir">
        <label
            v-if="label"
            :for="inputId"
            class="block text-[13px] text-mistral-steel mb-2 font-semibold"
        >
            {{ label }}
            <span v-if="required" class="text-mistral-primary" aria-hidden="true">*</span>
        </label>
        <select
            :id="inputId"
            :name="name"
            :value="modelValue"
            :required="required"
            :disabled="disabled"
            :aria-invalid="!!error"
            :class="[
                'h-11 w-full px-3 text-[14px] text-mistral-ink bg-mistral-canvas border rounded-md transition-colors appearance-none cursor-pointer select-with-arrow',
                'focus:outline-none focus:ring-2 focus:ring-mistral-primary focus:ring-opacity-15 focus:border-mistral-primary',
                'disabled:bg-mistral-surface disabled:text-mistral-muted disabled:cursor-not-allowed',
                error ? 'border-mistral-danger focus:border-mistral-danger' : 'border-mistral-hairline-strong',
            ]"
            @change="onChange"
        >
            <option value="">{{ placeholder }}</option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
        <p v-if="error" class="text-[12px] text-mistral-danger mt-1" role="alert">
            {{ error }}
        </p>
    </div>
</template>
