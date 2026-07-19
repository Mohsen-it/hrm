<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const inputId = computed(() => props.id || props.name || `switch-${Math.random().toString(36).slice(2, 9)}`);

function onChange(e) {
    emit('update:modelValue', e.target.checked);
    emit('change', e);
}
</script>

<template>
    <label
        :for="inputId"
        :class="['inline-flex items-center gap-3 cursor-pointer select-none', disabled ? 'cursor-not-allowed opacity-50' : '']"
        :dir="dir"
    >
        <span class="relative inline-block w-10 h-[22px]">
            <input
                :id="inputId"
                :name="name"
                type="checkbox"
                role="switch"
                :checked="modelValue"
                :disabled="disabled"
                class="sr-only peer"
                @change="onChange"
            />
            <span
                :class="[
                    'absolute inset-0 rounded-full transition-colors duration-200',
                    modelValue ? 'bg-mistral-primary' : 'bg-mistral-hairline-strong',
                ]"
            ></span>
            <span
                :class="[
                    'absolute top-[3px] w-[16px] h-[16px] bg-white rounded-full shadow-sm transition-transform duration-200',
                    modelValue
                        ? (dir === 'rtl' ? '-translate-x-[18px]' : 'translate-x-[18px]')
                        : (dir === 'rtl' ? '-translate-x-0.5' : 'translate-x-[3px]'),
                ]"
            ></span>
        </span>
        <span v-if="label" class="text-[13px] text-mistral-ink">
            {{ label }}
        </span>
    </label>
</template>
