<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean, null], default: null },
    value: { type: [String, Number, Boolean], required: true },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    size: { type: String, default: 'md' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const inputId = computed(() => props.id || props.name || `radio-${Math.random().toString(36).slice(2, 9)}`);

const isChecked = computed(() => props.modelValue === props.value);

const sizeClass = computed(() => {
    return { sm: 'w-[14px] h-[14px]', md: 'w-4 h-4', lg: 'w-5 h-5' }[props.size] || 'w-4 h-4';
});

function onChange(e) {
    emit('update:modelValue', props.value);
    emit('change', e);
}
</script>

<template>
    <div class="flex items-center gap-2" :dir="dir">
        <div class="relative flex items-center justify-center">
            <input
                :id="inputId"
                :name="name"
                type="radio"
                :checked="isChecked"
                :disabled="disabled"
                :class="[
                    'appearance-none cursor-pointer border-2 rounded-full transition-colors',
                    sizeClass,
                    isChecked ? 'bg-mistral-canvas border-mistral-primary' : 'bg-mistral-canvas border-mistral-hairline-strong',
                    disabled ? 'cursor-not-allowed opacity-50' : '',
                    'focus-visible:outline-2 focus-visible:outline-mistral-primary focus-visible:outline-offset-2',
                ]"
                @change="onChange"
            />
            <span
                v-if="isChecked"
                :class="['absolute rounded-full bg-mistral-primary pointer-events-none', sizeClass === 'w-4 h-4' ? 'w-2 h-2' : 'w-2.5 h-2.5']"
                aria-hidden="true"
            ></span>
        </div>
        <label
            v-if="label"
            :for="inputId"
            :class="['text-[14px] text-mistral-ink cursor-pointer', disabled ? 'cursor-not-allowed opacity-50' : '']"
        >
            {{ label }}
        </label>
    </div>
</template>
