<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    rows: { type: Number, default: 4 },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || props.name || `textarea-${Math.random().toString(36).slice(2, 9)}`);

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
        <textarea
            :id="inputId"
            :name="name"
            :value="modelValue"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            :rows="rows"
            :aria-invalid="!!error"
            :class="[
                'w-full p-3 text-[14px] text-mistral-ink bg-mistral-canvas border rounded-md transition-colors resize-y',
                'focus:outline-none focus:ring-2 focus:ring-mistral-primary focus:ring-opacity-15 focus:border-mistral-primary',
                'disabled:bg-mistral-surface disabled:text-mistral-muted disabled:cursor-not-allowed',
                error ? 'border-mistral-danger focus:border-mistral-danger' : 'border-mistral-hairline-strong',
            ]"
            @input="onInput"
        ></textarea>
        <p v-if="hint && !error" class="text-[12px] text-mistral-stone mt-1">
            {{ hint }}
        </p>
        <p v-if="error" class="text-[12px] text-mistral-danger mt-1" role="alert">
            {{ error }}
        </p>
    </div>
</template>
