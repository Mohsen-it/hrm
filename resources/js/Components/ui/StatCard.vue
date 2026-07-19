<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    trend: { type: String, default: null },
    trendDirection: { type: String, default: 'up' },
    icon: { type: String, default: null },
    color: { type: String, default: 'primary' },
    dir: { type: String, default: 'rtl' },
});

const colorClasses = computed(() => {
    const map = {
        primary: 'bg-mistral-primary/10 text-mistral-primary',
        success: 'bg-mistral-success/10 text-mistral-success',
        danger: 'bg-mistral-danger/10 text-mistral-danger',
        warning: 'bg-mistral-warning/10 text-mistral-warning',
        info: 'bg-mistral-info/10 text-mistral-info',
        vacation: 'bg-cyan-50 text-cyan-600',
    };
    return map[props.color] || map.primary;
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
    <div class="bg-white border border-mistral-hairline-soft rounded-xl p-5 hover:shadow-level-1 transition-shadow duration-200" :dir="dir">
        <div class="flex items-start justify-between mb-3">
            <span class="text-[12px] text-mistral-steel font-medium">
                {{ label }}
            </span>
            <div v-if="icon" :class="['w-9 h-9 rounded-lg flex items-center justify-center', colorClasses]">
                <i :class="[icon, 'text-[14px]']" aria-hidden="true"></i>
            </div>
        </div>
        <div class="text-[26px] font-bold text-mistral-ink leading-none tracking-tight">
            {{ typeof value === 'number' ? value.toLocaleString() : value }}
        </div>
        <div v-if="trend" :class="['flex items-center gap-1 mt-2 text-[12px] font-medium', trendClass]">
            <i :class="[trendIcon, 'rtl-flip text-[10px]']" aria-hidden="true"></i>
            <span>{{ trend }}</span>
        </div>
    </div>
</template>
