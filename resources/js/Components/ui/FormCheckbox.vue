<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Boolean, Array], default: false },
    value: { type: [String, Number, Boolean], default: null },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    indeterminate: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    size: { type: String, default: 'md' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const inputId = computed(() => props.id || props.name || `cb-${Math.random().toString(36).slice(2, 9)}`);

const isChecked = computed(() => {
    if (Array.isArray(props.modelValue)) {
        return props.modelValue.includes(props.value);
    }
    return Boolean(props.modelValue);
});

const sizeClass = computed(() => {
    return { sm: 'w-[14px] h-[14px]', md: 'w-4 h-4', lg: 'w-5 h-5' }[props.size] || 'w-4 h-4';
});

function onChange(e) {
    const checked = e.target.checked;
    if (Array.isArray(props.modelValue)) {
        const arr = [...props.modelValue];
        const idx = arr.indexOf(props.value);
        if (checked && idx === -1) arr.push(props.value);
        if (!checked && idx > -1) arr.splice(idx, 1);
        emit('update:modelValue', arr);
    } else {
        emit('update:modelValue', checked);
    }
    emit('change', e);
}
</script>

<template>
    <div class="w-full" :dir="dir">
        <div class="flex items-start gap-2">
            <div class="relative flex items-center justify-center pt-0.5">
                <input
                    :id="inputId"
                    :name="name"
                    type="checkbox"
                    :checked="isChecked"
                    :disabled="disabled"
                    :required="required"
                    :aria-invalid="!!error"
                    :aria-describedby="error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined"
                    :class="[
                        'appearance-none cursor-pointer border-2 rounded-xs transition-colors',
                        sizeClass,
                        isChecked ? 'bg-mistral-primary border-mistral-primary' : 'bg-mistral-canvas border-mistral-hairline-strong',
                        disabled ? 'cursor-not-allowed opacity-50' : '',
                        error ? 'border-mistral-danger' : '',
                        'focus-visible:outline-2 focus-visible:outline-mistral-primary focus-visible:outline-offset-2',
                    ]"
                    @change="onChange"
                />
                <i
                    v-if="isChecked && !indeterminate"
                    :class="['fas fa-check absolute text-mistral-on-primary pointer-events-none', sizeClass === 'w-4 h-4' ? 'text-[10px]' : 'text-[12px]']"
                    aria-hidden="true"
                ></i>
                <i
                    v-else-if="indeterminate"
                    :class="['fas fa-minus absolute text-mistral-on-primary pointer-events-none', sizeClass === 'w-4 h-4' ? 'text-[10px]' : 'text-[12px]']"
                    aria-hidden="true"
                ></i>
            </div>
            <label
                v-if="label || $slots.default"
                :for="inputId"
                :class="['text-[14px] text-mistral-ink cursor-pointer flex-1', disabled ? 'cursor-not-allowed opacity-50' : '']"
            >
                <span>
                    {{ label }}
                    <span v-if="required" class="text-mistral-primary" aria-hidden="true">*</span>
                </span>
                <slot />
                <span v-if="hint" :id="`${inputId}-hint`" class="block text-[12px] text-mistral-stone mt-1">
                    {{ hint }}
                </span>
            </label>
        </div>
        <p v-if="error" :id="`${inputId}-error`" class="text-[12px] text-mistral-danger mt-1 ms-6" role="alert">
            {{ error }}
        </p>
    </div>
</template>
