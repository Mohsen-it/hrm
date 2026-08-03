<script setup>
import { computed, watch } from 'vue';
import { FormInput } from '@/Components/ui';

const props = defineProps({
    startDate: { type: String, default: '' },
    endDate: { type: String, default: '' },
    daysCount: { type: [String, Number], default: '' },
    errors: { type: Object, default: () => ({}) },
    labels: { type: Object, required: true },
});

const emit = defineEmits(['update:startDate', 'update:endDate', 'update:daysCount']);

const startDate = computed({
    get: () => props.startDate,
    set: (value) => emit('update:startDate', value),
});

const endDate = computed({
    get: () => props.endDate,
    set: (value) => emit('update:endDate', value),
});

const daysCount = computed({
    get: () => props.daysCount,
    set: (value) => emit('update:daysCount', value),
});

function dateFromValue(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;

    const date = new Date(`${value}T00:00:00Z`);

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDate(date) {
    return date.toISOString().slice(0, 10);
}

function calculateEndDate(start, days) {
    const date = dateFromValue(start);
    const totalDays = Number.parseInt(days, 10);

    if (!date || !Number.isInteger(totalDays) || totalDays < 1) return null;

    date.setUTCDate(date.getUTCDate() + totalDays - 1);

    return formatDate(date);
}

function calculateDaysCount(start, end) {
    const startDateValue = dateFromValue(start);
    const endDateValue = dateFromValue(end);

    if (!startDateValue || !endDateValue || endDateValue < startDateValue) return null;

    return Math.round((endDateValue - startDateValue) / 86_400_000) + 1;
}

watch(
    () => [props.startDate, props.daysCount],
    ([start, days]) => {
        const calculatedEndDate = calculateEndDate(start, days);

        if (calculatedEndDate && calculatedEndDate !== props.endDate) {
            emit('update:endDate', calculatedEndDate);
        }
    },
);

watch(
    () => props.endDate,
    (end) => {
        const calculatedDaysCount = calculateDaysCount(props.startDate, end);

        if (calculatedDaysCount && calculatedDaysCount !== Number(props.daysCount)) {
            emit('update:daysCount', calculatedDaysCount);
        }
    },
    { immediate: true },
);
</script>

<template>
    <FormInput
        v-model="startDate"
        :label="labels.startDate"
        name="start_date"
        type="date"
        required
        :error="errors.start_date || ''"
    />
    <FormInput
        v-model="daysCount"
        :label="labels.daysCount"
        name="days_count"
        type="number"
        min="1"
        required
    />
    <FormInput
        v-model="endDate"
        :label="labels.endDate"
        name="end_date"
        type="date"
        required
        :error="errors.end_date || ''"
    />
</template>
