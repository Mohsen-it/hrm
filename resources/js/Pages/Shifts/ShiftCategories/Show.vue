<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    category: { type: Object, required: true },
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

const periodLabels = { daily: t('shifts.daily'), weekly: t('shifts.weekly_label'), monthly: t('shifts.monthly') };

const typeLabel = (type) => {
    const map = { cyclic: t('shifts.cyclic'), weekly: t('shifts.weekly'), hours: t('shifts.hours') };
    return map[type] || type;
};

const workPattern = computed(() => {
    const c = props.category;
    if (!c) return '—';
    if (c.type === 'cyclic') return `${c.work_days || 0}+${c.rest_days || 0}`;
    if (c.type === 'weekly' && c.work_days_json) {
        const days = c.work_days_json;
        if (Array.isArray(days)) {
            const sorted = [...days].sort((a, b) => a - b);
            return sorted
                .map((d) => dayLabels.value.find((l) => l.value === Number(d))?.label)
                .filter(Boolean)
                .join(' - ');
        }
        if (typeof days === 'object' && days !== null) {
            const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const activeDays = dayKeys.map((key, idx) => days[key] ? idx : null).filter(v => v !== null);
            return activeDays
                .map((d) => dayLabels.value.find((l) => l.value === Number(d))?.label)
                .filter(Boolean)
                .join(' - ');
        }
    }
    if (c.type === 'hours') return `${c.required_hours || 0} / ${periodLabels[c.period_type] || c.period_type || ''}`;
    return '—';
});

const infoFields = computed(() => {
    if (!props.category) return [];
    const fields = [
        { label: t('shifts.category_name'), value: props.category.name },
        { label: t('shifts.category_type'), value: typeLabel(props.category.type) },
        { label: t('shifts.work_days'), value: workPattern.value },
    ];

    if (props.category.type === 'cyclic') {
        fields.push({ label: t('shifts.rest_days'), value: props.category.rest_days ?? '—' });
    }
    if (props.category.type === 'hours') {
        fields.push({ label: t('shifts.required_hours'), value: props.category.required_hours ?? '—' });
        fields.push({ label: t('shifts.period_type'), value: periodLabels[props.category.period_type] || props.category.period_type || '—' });
    }

    fields.push({ label: t('shifts.overtime_enabled'), value: props.category.overtime_enabled ? t('common.yes') : t('common.no') });
    fields.push({ label: t('shifts.fingerprint_enabled'), value: props.category.fingerprint_enabled ? t('common.yes') : t('common.no') });
    fields.push({ label: t('shifts.work_on_holidays'), value: props.category.work_on_holidays ? t('common.yes') : t('common.no') });
    fields.push({ label: t('shifts.work_on_weekends'), value: props.category.work_on_weekends ? t('common.yes') : t('common.no') });

    return fields;
});

const schedule = computed(() => props.category?.time_schedule || null);

const breaks = computed(() => {
    if (schedule.value?.breaks && Array.isArray(schedule.value.breaks)) {
        return schedule.value.breaks;
    }
    return [];
});

const employees = computed(() => {
    if (!props.category?.employees) return [];
    return props.category.employees;
});

const employeeColumns = computed(() => [
    { key: 'employee_name', label: t('shifts.employee_name') },
    { key: 'start_date', label: t('shifts.start_date') },
    { key: 'status', label: t('shifts.status'), cellClass: 'text-center' },
]);

const formatTime = (val) => {
    if (!val) return '—';
    return String(val).slice(0, 5);
};
</script>

<template>
    <AppLayout :title="props.category?.name || ''">
        <PageHeader
            :title="props.category?.name || ''"
            :description="t('shifts.shift_categories')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('shift-categories.index')">{{ t('common.back') }}</Button>
                <template v-if="props.category">
                    <Button
                        variant="primary"
                        :href="props.category?.id ? route('shift-assignments.assign', { category: props.category.id }) : undefined"
                        icon="fas fa-user-plus"
                    >
                        {{ t('shifts.assign_employee') }}
                    </Button>
                    <Button
                        variant="primary"
                        :href="props.category?.id ? route('shift-assignments.bulk-assign', { category: props.category.id }) : undefined"
                        icon="fas fa-users"
                    >
                        {{ t('shifts.bulk_assign') }}
                    </Button>
                    <Button
                        variant="on-cream"
                        :href="props.category?.id ? route('shift-assignments.index', { category_id: props.category.id }) : undefined"
                        icon="fas fa-list"
                    >
                        {{ t('shifts.view_assignments') }}
                    </Button>
                    <Button
                        variant="cream"
                        :href="props.category?.id ? route('shift-categories.schedule-preview', { id: props.category.id }) : undefined"
                        icon="fas fa-calendar-alt"
                    >
                        {{ t('shifts.schedule_preview') }}
                    </Button>
                </template>
            </template>
        </PageHeader>

        <Card variant="base" padding="lg" class="mb-6" v-if="category">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline">
                <div
                    class="w-16 h-16 rounded-md flex items-center justify-center border border-mistral-hairline"
                    :style="{ backgroundColor: category?.color || '#fa520f' }"
                >
                    <i class="fas fa-layer-group text-[24px] text-white"></i>
                </div>
                <div class="flex-1">
                    <h2 class="text-[20px] font-semibold text-mistral-ink">
                        {{ category?.name }}
                    </h2>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <Badge :text="typeLabel(category?.type)" variant="info" />
                        <Badge
                            :text="`${t('shifts.employees_count')}: ${category?.active_employees_count || employees.filter(e => e.end_date === null).length}`"
                            variant="active"
                        />
                    </div>
                </div>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                <div v-for="(field, idx) in infoFields" :key="idx" class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ field.label }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">
                        {{ field.value }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Card v-if="schedule" variant="base" padding="lg" class="mb-6">
            <template #header>
                <div class="flex items-center justify-between">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ schedule.name || t('shifts.time_schedules') }}
                    </h3>
                    <Button
                        variant="ghost"
                        size="sm"
                        :href="route('time-schedules.show', schedule.id)"
                        icon="fas fa-external-link-alt"
                    >
                        {{ t('shifts.view_schedule') }}
                    </Button>
                </div>
            </template>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ t('shifts.in_time') }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1" dir="ltr">
                        {{ formatTime(schedule.in_time) }}
                    </dd>
                </div>
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ t('shifts.out_time') }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1" dir="ltr">
                        {{ formatTime(schedule.out_time) }}
                    </dd>
                </div>
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ t('shifts.late_margin') }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">
                        {{ schedule.late_margin ?? 0 }}
                    </dd>
                </div>
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ t('shifts.early_margin') }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">
                        {{ schedule.early_margin ?? 0 }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Card v-if="breaks.length" variant="base" padding="lg" class="mb-6">
            <template #header>
                <h3 class="text-[16px] font-semibold text-mistral-ink">
                    {{ t('shifts.breaks') }}
                </h3>
            </template>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-[13px]">
                    <thead>
                        <tr class="border-b border-mistral-hairline">
                            <th class="px-3 py-2 text-mistral-slate">{{ t('shifts.break_start') }}</th>
                            <th class="px-3 py-2 text-mistral-slate">{{ t('shifts.duration') }}</th>
                            <th class="px-3 py-2 text-mistral-slate">{{ t('shifts.break_end') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(brk, i) in breaks" :key="i" class="border-b border-mistral-hairline">
                            <td class="px-3 py-2" dir="ltr">{{ formatTime(brk.break_start) }}</td>
                            <td class="px-3 py-2">{{ brk.duration }}</td>
                            <td class="px-3 py-2" dir="ltr">{{ formatTime(brk.break_end) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Card variant="base" padding="lg">
            <template #header>
                <div class="flex items-center justify-between">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ t('shifts.employees_count') }} ({{ employees.filter(e => e.end_date === null).length }})
                    </h3>
                    <Button
                        variant="ghost"
                        size="sm"
                        :href="route('shift-assignments.index', { category_id: props.category?.id })"
                        icon="fas fa-list"
                    >
                        {{ t('shifts.view_assignments') }}
                    </Button>
                </div>
            </template>

            <EmptyState
                v-if="employees.length === 0"
                icon="fas fa-users"
                :title="t('shifts.no_employees_selected')"
                :description="t('shifts.assign_employees_to_category')"
            />

            <DataTable
                v-else
                :columns="employeeColumns"
                :data="{ data: employees }"
            >
                <template #cell-employee_name="{ row }">
                    {{ row.employee?.first_name }} {{ row.employee?.last_name }}
                    <span class="text-mistral-muted text-[12px]">({{ row.employee?.emp_code }})</span>
                </template>

                <template #cell-start_date="{ row }">
                    {{ row.start_date || '—' }}
                </template>

                <template #cell-status="{ row }">
                    <Badge v-if="row.end_date === null" :text="t('shifts.active')" variant="active" />
                    <Badge v-else :text="t('shifts.closed')" variant="inactive" />
                </template>
            </DataTable>
        </Card>
    </AppLayout>
</template>
