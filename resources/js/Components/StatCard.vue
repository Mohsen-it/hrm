<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: null },
    color: {
        type: String,
        default: 'primary',
        validator: (v) =>
            ['primary', 'success', 'warning', 'danger', 'info', 'overtime', 'vacation'].includes(v),
    },
    trend: { type: Number, default: null },
    trendLabel: { type: String, default: null },
});

const colorClasses = computed(() => {
    const map = {
        primary: { bg: 'bg-[#dbeafe]', text: 'text-[#2563eb]' },
        success: { bg: 'bg-[#dcfce7]', text: 'text-[#16a34a]' },
        warning: { bg: 'bg-[#fef3c7]', text: 'text-[#d97706]' },
        danger: { bg: 'bg-[#fee2e2]', text: 'text-[#dc2626]' },
        info: { bg: 'bg-[#dbeafe]', text: 'text-[#2563eb]' },
        overtime: { bg: 'bg-[#ede9fe]', text: 'text-[#7c3aed]' },
        vacation: { bg: 'bg-[#cffafe]', text: 'text-[#0891b2]' },
    };
    return map[props.color] || map.primary;
});

const trendPositive = computed(() => props.trend !== null && props.trend >= 0);
</script>

<template>
    <div class="card p-4 flex flex-col gap-2" dir="rtl">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-1">
                <span class="text-[12px] font-medium text-[#475569] leading-tight">{{ label }}</span>
                <span class="text-[24px] font-bold text-[#0f172a] leading-tight">{{ value }}</span>
            </div>
            <div
                v-if="icon"
                class="w-10 h-10 rounded-md flex items-center justify-center text-[18px]"
                :class="[colorClasses.bg, colorClasses.text]"
            >
                <i :class="icon"></i>
            </div>
        </div>
        <div v-if="trend !== null" class="flex items-center gap-1 text-[11px]">
            <span
                :class="trendPositive ? 'text-[#16a34a]' : 'text-[#dc2626]'"
                class="font-medium"
            >
                <i :class="trendPositive ? 'fas fa-arrow-up' : 'fas fa-arrow-down rtl-flip'"></i>
                {{ Math.abs(trend) }}%
            </span>
            <span v-if="trendLabel" class="text-[#94a3b8]">{{ trendLabel }}</span>
        </div>
    </div>
</template>
