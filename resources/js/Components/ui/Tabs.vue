<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: [String, Number], default: null },
    variant: { type: String, default: 'pill' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const active = ref(props.modelValue ?? props.tabs[0]?.value);

watch(() => props.modelValue, (val) => {
    if (val !== null && val !== undefined) active.value = val;
});

function select(tab) {
    if (tab.disabled) return;
    active.value = tab.value;
    emit('update:modelValue', tab.value);
    emit('change', tab);
}

const isActive = (tab) => active.value === tab.value;
</script>

<template>
    <div :dir="dir">
        <div
            v-if="variant === 'pill'"
            class="inline-flex items-center gap-1 p-1 bg-mistral-surface rounded-full flex-wrap"
            role="tablist"
        >
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                role="tab"
                :aria-selected="isActive(tab)"
                :disabled="tab.disabled"
                :class="[
                    'px-4 py-1.5 text-[13px] font-medium rounded-full transition-colors',
                    isActive(tab)
                        ? 'bg-mistral-ink text-mistral-on-dark'
                        : 'bg-transparent text-mistral-steel hover:text-mistral-ink',
                    tab.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                ]"
                @click="select(tab)"
            >
                {{ tab.label }}
            </button>
        </div>
        <div
            v-else
            class="flex items-center gap-0 border-b border-mistral-hairline-soft overflow-x-auto"
            role="tablist"
        >
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                role="tab"
                :aria-selected="isActive(tab)"
                :disabled="tab.disabled"
                :class="[
                    'px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2',
                    isActive(tab)
                        ? 'text-mistral-primary border-mistral-primary'
                        : 'text-mistral-steel border-transparent hover:text-mistral-ink',
                    tab.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                ]"
                @click="select(tab)"
            >
                {{ tab.label }}
            </button>
        </div>
        <div class="mt-4">
            <slot :active="active" />
        </div>
    </div>
</template>
