<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    shift: { type: Object, required: true },
});

const dayLabels = computed(() => [
    { value: 0, label: t('shifts.sunday') },
    { value: 1, label: t('shifts.monday') },
    { value: 2, label: t('shifts.tuesday') },
    { value: 3, label: t('shifts.wednesday') },
    { value: 4, label: t('shifts.thursday') },
    { value: 5, label: t('shifts.friday') },
    { value: 6, label: t('shifts.saturday') },
]);

function formatDays(days) {
    if (!Array.isArray(days) || days.length === 0) return t('shifts.no_days_selected');
    const sorted = [...days].sort((a, b) => a - b);
    return sorted.map((d) => {
        const found = dayLabels.value.find((l) => l.value === Number(d));
        return found ? found.label : null;
    }).filter(Boolean).join(' - ');
}

const timeRange = computed(() => {
    const start = props.shift.start_time ? String(props.shift.start_time).slice(0, 5) : '—';
    const end = props.shift.end_time ? String(props.shift.end_time).slice(0, 5) : '—';
    return `${start} - ${end}`;
});

const fields = computed(() => [
    { label: t('shifts.code'), value: props.shift.shift_code },
    { label: t('shifts.name'), value: props.shift.shift_name },
    { label: t('shifts.company'), value: props.shift.company?.company_name || '—' },
    { label: t('shifts.branch'), value: props.shift.branch?.branch_name || '—' },
    { label: t('shifts.time_range'), value: timeRange.value, ltr: true },
    { label: t('shifts.break_minutes'), value: props.shift.break_minutes ?? 0 },
    { label: t('shifts.grace_minutes'), value: props.shift.grace_minutes ?? 0 },
    { label: t('shifts.working_hours'), value: props.shift.working_hours ?? '—' },
    { label: t('shifts.work_days'), value: formatDays(props.shift.work_days) },
    { label: t('shifts.description'), value: props.shift.description || '—' },
]);

const employeesCount = computed(() => {
    if (Array.isArray(props.shift.users)) {
        return props.shift.users.length;
    }
    return 0;
});
</script>

<template>
    <AppLayout :title="t('shifts.view_shift')">
        <PageHeader
            :title="t('shifts.view_shift')"
            :description="shift.shift_name"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('shifts.index')">
                    {{ t('common.back') }}
                </Button>
                <Button variant="primary" icon="fas fa-pen" :href="route('shifts.edit', shift.id)">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">
                    <div
                        class="w-16 h-16 rounded-xl bg-mistral-surface flex items-center justify-center border border-mistral-hairline-soft"
                    >
                        <i class="fas fa-clock text-[24px] text-mistral-stone"></i>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-[20px] font-semibold text-mistral-ink">
                            {{ shift.shift_name }}
                        </h2>
                        <p class="text-[13px] text-mistral-steel mt-1">
                            {{ shift.shift_code }}
                        </p>
                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <Badge v-if="shift.status === 1" :text="t('common.active')" variant="active" />
                            <Badge v-else :text="t('common.inactive')" variant="inactive" />
                            <Badge :text="`${t('shifts.employees_count')}: ${employeesCount}`" variant="info" />
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-end">
                        <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">
                            {{ field.label }}
                        </dt>
                        <dd
                            class="text-[14px] text-mistral-ink mt-1 break-words whitespace-pre-line"
                            :dir="field.ltr ? 'ltr' : 'rtl'"
                        >
                            {{ field.value }}
                        </dd>
                    </div>
                </dl>
            </div>
        </Card>
    </AppLayout>
</template>
