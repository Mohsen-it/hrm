<script setup>
import { ref, computed, watch, nextTick, useId } from 'vue';

const props = defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: [String, Number], default: null },
    variant: { type: String, default: 'underline' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const baseId = useId();
const listId = `tabs-list-${baseId}`;
const panelId = `tabpanel-${baseId}`;

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

const enabledTabs = computed(() => props.tabs.filter((tab) => !tab.disabled));

function tabElement(tab) {
    return document.getElementById(`${listId}-tab-${tab.value}`);
}

function focusTab(tab) {
    if (!tab || tab.disabled) return;
    select(tab);
    nextTick(() => tabElement(tab)?.focus());
}

// WAI-ARIA tabs pattern: ←/→ moves focus (direction-aware), Home/End jump,
// Enter/Space activates. In RTL the ←/→ keys map to the visual direction.
function onTablistKeydown(e) {
    const enabled = enabledTabs.value;
    if (enabled.length === 0) return;

    const currentIndex = Math.max(
        0,
        enabled.findIndex((tab) => isActive(tab)),
    );
    const isRtl = props.dir === 'rtl';
    const nextKey = isRtl ? 'ArrowLeft' : 'ArrowRight';
    const prevKey = isRtl ? 'ArrowRight' : 'ArrowLeft';
    const forwardKeys = ['ArrowDown', nextKey];
    const backwardKeys = ['ArrowUp', prevKey];

    let targetIndex = null;

    if (forwardKeys.includes(e.key)) {
        targetIndex = (currentIndex + 1) % enabled.length;
    } else if (backwardKeys.includes(e.key)) {
        targetIndex = (currentIndex - 1 + enabled.length) % enabled.length;
    } else if (e.key === 'Home') {
        targetIndex = 0;
    } else if (e.key === 'End') {
        targetIndex = enabled.length - 1;
    } else if (e.key === 'Enter' || e.key === ' ') {
        // Buttons already activate on Enter/Space; keep focus handling native.
        return;
    } else {
        return;
    }

    e.preventDefault();
    focusTab(enabled[targetIndex]);
}
</script>

<template>
    <div :dir="dir">
        <div
            v-if="variant === 'pill'"
            :id="listId"
            class="inline-flex items-center gap-1 p-1 bg-mistral-surface rounded-full flex-wrap"
            role="tablist"
            :aria-orientation="'horizontal'"
            @keydown="onTablistKeydown"
        >
            <button
                v-for="tab in tabs"
                :id="`${listId}-tab-${tab.value}`"
                :key="tab.value"
                type="button"
                role="tab"
                :aria-selected="isActive(tab)"
                :aria-controls="panelId"
                :tabindex="isActive(tab) ? 0 : -1"
                :disabled="tab.disabled"
                :class="[
                    'px-4 py-1.5 text-[13px] font-medium rounded-full transition-all duration-150',
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mistral-primary',
                    isActive(tab)
                        ? 'bg-mistral-ink text-white shadow-sm'
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
            :id="listId"
            class="flex items-center gap-0 border-b border-mistral-hairline-soft overflow-x-auto"
            role="tablist"
            :aria-orientation="'horizontal'"
            @keydown="onTablistKeydown"
        >
            <button
                v-for="tab in tabs"
                :id="`${listId}-tab-${tab.value}`"
                :key="tab.value"
                type="button"
                role="tab"
                :aria-selected="isActive(tab)"
                :aria-controls="panelId"
                :tabindex="isActive(tab) ? 0 : -1"
                :disabled="tab.disabled"
                :class="[
                    'px-4 py-2.5 text-[13px] font-medium transition-all duration-150 border-b-2 whitespace-nowrap',
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mistral-primary',
                    isActive(tab)
                        ? 'text-mistral-primary border-mistral-primary'
                        : 'text-mistral-stone border-transparent hover:text-mistral-ink hover:border-mistral-hairline',
                    tab.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                ]"
                @click="select(tab)"
            >
                {{ tab.label }}
            </button>
        </div>
        <div
            :id="panelId"
            class="mt-4"
            role="tabpanel"
            :aria-labelledby="`${listId}-tab-${active}`"
        >
            <slot :active="active" />
        </div>
    </div>
</template>
