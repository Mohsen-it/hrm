<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, StatCard, Badge, FormInput, DataTable } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    userId: { type: [String, Number], required: true },
    filters: { type: Object, default: () => ({}) },
    report: { type: Object, default: () => ({ totals: {}, by_status: {}, sessions: [] }) },
    overtime: { type: Object, default: () => ({ by_day: [] }) },
});

const from = ref(props.filters?.from ?? new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
const to = ref(props.filters?.to ?? new Date().toISOString().slice(0, 10));

function applyFilters() {
    router.get(
        route('attendance.reports.user', props.userId),
        { from: from.value, to: to.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const statusVariant = (status) => {
    return {
        present: 'active',
        late: 'pending',
        early_leave: 'info',
        missing_punch: 'absent',
        unassigned: 'warning',
    }[status] || 'inactive';
};

const sessionColumns = [
    { key: 'attendance_date', label: t('attendance.fields.attendance_date') },
    { key: 'check_in_at', label: t('attendance.fields.check_in_at') },
    { key: 'check_out_at', label: t('attendance.fields.check_out_at') },
    { key: 'work_minutes', label: t('attendance.fields.work_human') },
    { key: 'late_minutes', label: t('attendance.fields.late_human') },
    { key: 'status', label: t('attendance.fields.status') },
];

const sessionData = computed(() => ({ data: props.report.sessions || [], links: [] }));
</script>

<template>
    <AppLayout :title="t('attendance.user_report') + ' #' + userId">
        <PageHeader
            :title="t('attendance.user_report') + ' #' + userId"
            :description="`${report.from} → ${report.to}`"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('attendance.reports.index')">
                    {{ t('attendance.actions.back') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none" class="mb-4">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-3 flex-wrap">
                    <FormInput
                        v-model="from"
                        type="date"
                        :label="t('attendance.fields.from')"
                        class="max-w-[170px]"
                    />
                    <FormInput
                        v-model="to"
                        type="date"
                        :label="t('attendance.fields.to')"
                        class="max-w-[170px]"
                    />
                    <Button variant="primary" icon="fas fa-search" @click="applyFilters" class="self-end">
                        {{ t('common.search') }}
                    </Button>
                </div>
            </div>
        </Card>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <StatCard :label="t('attendance.fields.work_minutes')" :value="report.totals?.work_minutes || 0" color="success" icon="fas fa-briefcase" />
            <StatCard :label="t('attendance.fields.late_minutes')" :value="report.totals?.late_minutes || 0" color="warning" icon="fas fa-clock" />
            <StatCard :label="t('attendance.fields.overtime_minutes')" :value="report.totals?.overtime_minutes || 0" color="info" icon="fas fa-hourglass-half" />
            <StatCard :label="t('attendance.reports_page.absent_days')" :value="report.totals?.days_absent || 0" color="danger" icon="fas fa-user-times" />
        </div>

        <Card variant="base" padding="none" class="mb-4">
            <div class="p-5 sm:p-6">
                <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                    {{ t('attendance.fields.status') }}
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-2 text-[12px]">
                    <div v-for="(v, k) in (report.by_status || {})" :key="k" class="p-2 rounded-lg bg-mistral-surface">
                        <div class="font-semibold text-mistral-ink">{{ t(`attendance.status.${k}`, k) }}</div>
                        <div class="text-mistral-steel">{{ v }}</div>
                    </div>
                </div>
            </div>
        </Card>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-mistral-ink">
            {{ t('attendance.sessions') }}
        </h3>
        <Card variant="base" padding="none">
            <DataTable
                :columns="sessionColumns"
                :data="sessionData"
                storage-key="user-report"
                :enable-search="false"
                :enable-filters="false"
                :enable-pagination="false"
                :enable-export="false"
                :enable-density="false"
                :enable-column-visibility="false"
                :selectable="false"
            >
                <template #cell-work_minutes="{ row }">
                    {{ row.work_minutes || 0 }}m
                </template>
                <template #cell-late_minutes="{ row }">
                    {{ row.late_minutes || 0 }}m
                </template>
                <template #cell-status="{ row }">
                    <Badge
                        :text="t(`attendance.status.${row.status}`, row.status)"
                        :variant="statusVariant(row.status)"
                    />
                </template>
            </DataTable>
        </Card>
    </AppLayout>
</template>
