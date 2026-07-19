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

function clear() {
    localValue.value = '';
}
</script>

<template>
    <div class="relative w-full sm:w-64" :dir="dir">
        <i
            class="fas fa-magnifying-glass absolute top-1/2 -translate-y-1/2 text-mistral-muted text-[13px]"
            :class="dir === 'rtl' ? 'right-3' : 'left-3'"
            aria-hidden="true"
        ></i>
        <input
            v-model="localValue"
            type="search"
            :placeholder="placeholder"
            :class="[
                'h-9 w-full text-[13px] text-mistral-ink bg-white border border-mistral-hairline-strong rounded-lg transition-all duration-150',
                'placeholder:text-mistral-muted',
                'focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary',
                dir === 'rtl' ? 'pr-9 pl-8' : 'pl-9 pr-8',
            ]"
        />
        <button
            v-if="localValue"
            type="button"
            class="absolute top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center rounded-full text-mistral-muted hover:text-mistral-ink hover:bg-mistral-surface transition-colors"
            :class="dir === 'rtl' ? 'left-2' : 'right-2'"
            aria-label="مسح"
            @click="clear"
        >
            <i class="fas fa-xmark text-[10px]" aria-hidden="true"></i>
        </button>
    </div>
</template>
