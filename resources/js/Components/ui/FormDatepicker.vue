<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    min: { type: String, default: null },
    max: { type: String, default: null },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || props.name || `date-${Math.random().toString(36).slice(2, 9)}`);

function onInput(e) {
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
        <div class="relative">
            <input
                :id="inputId"
                :name="name"
                type="date"
                :value="modelValue"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :min="min"
                :max="max"
                :aria-invalid="!!error"
                :class="[
                    'h-11 w-full px-3 text-[14px] text-mistral-ink bg-mistral-canvas border rounded-md transition-colors',
                    'focus:outline-none focus:ring-2 focus:ring-mistral-primary focus:ring-opacity-15 focus:border-mistral-primary',
                    'disabled:bg-mistral-surface disabled:text-mistral-muted disabled:cursor-not-allowed',
                    error ? 'border-mistral-danger focus:border-mistral-danger' : 'border-mistral-hairline-strong',
                ]"
                @input="onInput"
            />
        </div>
        <p v-if="hint && !error" class="text-[12px] text-mistral-stone mt-1">
            {{ hint }}
        </p>
        <p v-if="error" class="text-[12px] text-mistral-danger mt-1" role="alert">
            {{ error }}
        </p>
    </div>
</template>
