<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    label: { type: String, default: '' },
    options: { type: Array, required: true },
    placeholder: { type: String, default: 'اختر...' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    autofocus: { type: Boolean, default: false },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || props.name || `select-${Math.random().toString(36).slice(2, 9)}`);
const selectRef = ref(null);

function onChange(e) {
    emit('update:modelValue', e.target.value);
}

function focus() {
    selectRef.value?.focus();
}

defineExpose({ focus });
</script>

<template>
    <div class="w-full text-start" :dir="dir">
        <label
            v-if="label"
            :for="inputId"
            class="mb-1.5 flex items-center gap-1 text-[13px] font-medium leading-5 text-mistral-ink"
        >
            {{ label }}
            <span v-if="required" class="text-mistral-danger" aria-hidden="true">*</span>
        </label>
        <div class="relative" @click="focus">
            <select
                :id="inputId"
                ref="selectRef"
                :name="name"
                :value="modelValue"
                :required="required"
                :disabled="disabled"
                :autofocus="autofocus"
                :aria-invalid="!!error"
                :aria-describedby="error ? `${inputId}-error` : undefined"
                :class="[
                    'w-full h-11 px-3 text-[14px] text-mistral-ink bg-white border rounded-lg transition-all duration-200 appearance-none cursor-pointer select-with-arrow',
                    'focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary',
                    'disabled:bg-mistral-surface disabled:text-mistral-muted disabled:cursor-not-allowed',
                    error
                        ? 'border-mistral-danger focus:ring-mistral-danger/20 focus:border-mistral-danger'
                        : 'border-mistral-hairline-strong hover:border-mistral-stone',
                ]"
                @change="onChange"
            >
                <option value="" disabled hidden>{{ placeholder }}</option>
                <option v-for="opt in options" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
        </div>
        <p v-if="hint && !error" class="text-[12px] text-mistral-stone mt-1">
            {{ hint }}
        </p>
        <p v-if="error" :id="`${inputId}-error`" class="text-[12px] text-mistral-danger mt-1 flex items-center gap-1" role="alert">
            <i class="fas fa-exclamation-circle text-[10px]" aria-hidden="true"></i>
            {{ error }}
        </p>
    </div>
</template>
