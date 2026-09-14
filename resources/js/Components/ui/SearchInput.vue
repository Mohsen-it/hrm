<script setup>
import { computed, ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
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

const resolvedPlaceholder = computed(() => props.placeholder || t('common.search'));
const clearLabel = computed(() => t('common.remove'));
</script>

<template>
    <div class="relative w-full sm:w-64" :dir="dir">
        <i
            class="fas fa-magnifying-glass absolute start-3 top-1/2 -translate-y-1/2 text-mistral-muted text-[13px]"
            aria-hidden="true"
        ></i>
        <input
            v-model="localValue"
            type="search"
            :placeholder="resolvedPlaceholder"
            :class="[
                'h-9 w-full text-[13px] text-mistral-ink bg-mistral-canvas border border-mistral-hairline-strong rounded-md transition-all duration-150',
                'placeholder:text-mistral-muted',
                'focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary',
                'ps-9 pe-8',
            ]"
        />
        <button
            v-if="localValue"
            type="button"
            class="absolute end-2 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center rounded-full text-mistral-muted hover:text-mistral-ink hover:bg-mistral-surface transition-colors"
            :aria-label="clearLabel"
            @click="clear"
        >
            <i class="fas fa-xmark text-[10px]" aria-hidden="true"></i>
        </button>
    </div>
</template>
