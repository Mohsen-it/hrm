<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    trend: { type: String, default: null },
    trendDirection: { type: String, default: 'up' },
    icon: { type: String, default: null },
    dir: { type: String, default: 'rtl' },
});

const trendClass = computed(() => {
    if (!props.trend) return '';
    return props.trendDirection === 'up' ? 'text-mistral-success' : 'text-mistral-danger';
});

const trendIcon = computed(() => {
    if (!props.trend) return '';
    return props.trendDirection === 'up' ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
});
</script>

<template>
    <div class="bg-mistral-canvas border border-mistral-hairline-soft rounded-lg p-6" :dir="dir">
        <div class="flex items-start justify-between mb-2">
            <span class="text-[13px] text-mistral-steel font-medium uppercase tracking-wide">
                {{ label }}
            </span>
            <i v-if="icon" :class="[icon, 'text-[18px] text-mistral-muted']" aria-hidden="true"></i>
        </div>
        <div class="text-[28px] font-semibold text-mistral-ink leading-tight">
            {{ value }}
        </div>
        <div v-if="trend" :class="['flex items-center gap-1 mt-2 text-[12px] font-semibold', trendClass]">
            <i :class="[trendIcon, 'rtl-flip']" aria-hidden="true"></i>
            <span>{{ trend }}</span>
        </div>
    </div>
</template>
