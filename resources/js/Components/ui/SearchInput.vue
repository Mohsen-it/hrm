<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'بحث...' },
    debounce: { type: Number, default: 300 },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'search']);

const localValue = ref(props.modelValue);
let debounceTimer = null;

watch(() => props.modelValue, (val) => {
    localValue.value = val;
});

watch(localValue, (val) => {
    emit('update:modelValue', val);
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('search', val);
    }, props.debounce);
});
</script>

<template>
    <div class="relative w-full sm:w-72" :dir="dir">
        <i
            :class="[dir === 'rtl' ? 'fa-magnifying-glass' : 'fa-magnifying-glass', 'fas absolute top-1/2 -translate-y-1/2 text-mistral-muted text-[14px]', dir === 'rtl' ? 'right-3' : 'left-3']"
            aria-hidden="true"
        ></i>
        <input
            v-model="localValue"
            type="search"
            :placeholder="placeholder"
            :class="[
                'h-10 w-full text-[14px] text-mistral-ink bg-mistral-canvas border border-mistral-hairline-strong rounded-md transition-colors',
                'focus:outline-none focus:ring-2 focus:ring-mistral-primary focus:ring-opacity-15 focus:border-mistral-primary',
                dir === 'rtl' ? 'pr-9 pl-3' : 'pl-9 pr-3',
            ]"
        />
    </div>
</template>
