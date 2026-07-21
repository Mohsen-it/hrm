<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    schedule: { type: Object, required: true },
});

const fields = computed(() => [
    { label: t('shifts.schedule_name'), value: props.schedule.name || '—' },
    {
        label: t('shifts.in_time'),
        value: props.schedule.in_time ? String(props.schedule.in_time).slice(0, 5) : '—',
        ltr: true,
    },
    {
        label: t('shifts.out_time'),
        value: props.schedule.out_time ? String(props.schedule.out_time).slice(0, 5) : '—',
        ltr: true,
    },
    { label: t('shifts.late_margin'), value: props.schedule.late_margin ?? 0 },
    { label: t('shifts.early_margin'), value: props.schedule.early_margin ?? 0 },
    { label: t('shifts.in_ahead_margin'), value: props.schedule.in_ahead_margin ?? 0 },
    { label: t('shifts.in_above_margin'), value: props.schedule.in_above_margin ?? 0 },
    { label: t('shifts.out_ahead_margin'), value: props.schedule.out_ahead_margin ?? 0 },
    { label: t('shifts.out_above_margin'), value: props.schedule.out_above_margin ?? 0 },
]);

const breaksList = computed(() => {
    if (!Array.isArray(props.schedule.breaks) || props.schedule.breaks.length === 0) return [];
    return props.schedule.breaks;
});

const linkedCategoryId = computed(() => props.schedule.linked_category_id || null);

const breakColumns = [
    { key: 'break_start', label: t('shifts.break_start') },
    { key: 'break_end', label: t('shifts.break_end') },
    { key: 'duration', label: t('shifts.break_duration') || t('shifts.duration') },
];

const breaksData = computed(() => ({
    data: props.schedule.breaks || [],
    links: [],
    total: (props.schedule.breaks || []).length,
    current_page: 1,
    last_page: 1,
    per_page: 1000,
    from: 1,
    to: (props.schedule.breaks || []).length,
}));
</script>

<template>
    <AppLayout :title="t('shifts.view_schedule')">
        <PageHeader
            :title="t('shifts.view_schedule')"
            :description="schedule.name"
        >
            <template #actions>
                <Button variant="secondary" :href="route('time-schedules.index')">{{ t('common.back') }}</Button>
                <Button variant="primary" :href="route('time-schedules.edit', schedule.id)" icon="fas fa-edit">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="lg" class="mb-6">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline">
                <div class="w-16 h-16 rounded-md bg-mistral-surface flex items-center justify-center border border-mistral-hairline">
                    <i class="fas fa-clock text-[24px] text-mistral-slate"></i>
                </div>
                <div class="flex-1">
                    <h2 class="text-[20px] font-semibold text-mistral-ink">
                        {{ schedule.name }}
                    </h2>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <Badge
                            v-if="schedule.is_multi_day"
                            :text="t('shifts.continuous')"
                            variant="active"
                        />
                        <Badge
                            v-else
                            :text="t('shifts.daily')"
                            variant="inactive"
                        />
                        <Link
                            v-if="linkedCategoryId"
                            :href="route('shift-categories.show', linkedCategoryId)"
                        >
                            <Badge :text="schedule.linked_category_name" variant="info" />
                        </Link>
                    </div>
                </div>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ field.label }}
                    </dt>
                    <dd
                        class="text-[14px] text-mistral-ink mt-1 break-words"
                        :dir="field.ltr ? 'ltr' : 'rtl'"
                    >
                        {{ field.value }}
                    </dd>
                </div>
            </dl>
        </Card>

        <Card v-if="breaksList.length > 0" variant="base" padding="lg" class="mb-6">
            <template #header>
                <h3 class="text-[16px] font-semibold text-mistral-ink">
                    {{ t('shifts.breaks') }}
                </h3>
            </template>

            <DataTable
                :columns="breakColumns"
                :data="breaksData"
                :selectable="false"
                :enable-search="false"
                :enable-filters="false"
                :enable-pagination="false"
                :enable-export="false"
                :enable-density="false"
                :enable-column-visibility="false"
                storage-key="time-schedule-breaks"
            >
                <template #cell-break_start="{ row }">
                    <span dir="ltr">{{ row.break_start ? String(row.break_start).slice(0, 5) : '—' }}</span>
                </template>
                <template #cell-break_end="{ row }">
                    <span dir="ltr">{{ row.break_end ? String(row.break_end).slice(0, 5) : '—' }}</span>
                </template>
                <template #cell-duration="{ row }">
                    {{ row.duration ?? '—' }}
                </template>
            </DataTable>
        </Card>

        <Card v-if="linkedCategoryId" variant="cream" padding="md">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <i class="fas fa-info-circle text-mistral-primary"></i>
                    <span class="text-[14px] text-mistral-ink">
                        {{ t('shifts.linked_category') }}: <strong>{{ schedule.linked_category_name }}</strong>
                    </span>
                </div>
                <Button
                    variant="on-cream"
                    :href="route('shift-categories.show', linkedCategoryId)"
                    icon="fas fa-arrow-left"
                    iconPosition="start"
                >
                    {{ t('shifts.view_category') }}
                </Button>
            </div>
        </Card>
    </AppLayout>
</template>
