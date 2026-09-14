<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { PageHeader, Button, Card, StatCard, FormInput, FormSelect, FormMultiSelect, DataTable, Badge } from '@/Components/ui';

const props = defineProps({
    report: { type: Object, default: () => ({ rows: [], stats: {} }) },
    filters: { type: Object, default: () => ({}) },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

const date = ref(props.filters.date || new Date().toISOString().slice(0, 10));
const cutoffTime = ref(props.filters.cutoff_time || '09:00');
const branchId = ref(props.filters.branch_id || '');
const departmentIds = ref(
    Array.isArray(props.filters.department_ids) && props.filters.department_ids.length
        ? props.filters.department_ids.map(Number)
        : (props.filters.department_id ? [Number(props.filters.department_id)] : []),
);
const userId = ref(props.filters.user_id || '');
const status = ref(props.filters.status || '');
const statusOptions = computed(() => [
    { value: '', label: t('attendance.daily_report.status_all') },
    { value: 'absent', label: t('attendance.daily_report.status_absent') },
    { value: 'awaiting', label: t('attendance.daily_report.status_awaiting') },
    { value: 'late', label: t('attendance.daily_report.status_late') },
    { value: 'leave', label: t('attendance.daily_report.status_leave') },
    { value: 'mission', label: t('attendance.daily_report.status_mission') },
    { value: 'no_fingerprint', label: t('attendance.daily_report.status_no_fingerprint') },
    { value: 'incomplete', label: t('attendance.daily_report.status_incomplete') },
    { value: 'holiday', label: t('attendance.daily_report.status_holiday') },
]);
const data = computed(() => ({ data: props.report.rows || [], links: [] }));
const columns = computed(() => [
    { key: 'name', label: t('attendance.daily_report.col_name') },
    { key: 'employee_code', label: t('attendance.daily_report.col_employee_code') },
    { key: 'department_name', label: t('attendance.daily_report.col_department') },
    { key: 'status_label', label: t('attendance.daily_report.col_status') },
    { key: 'check_in', label: t('attendance.daily_report.col_check_in') },
    { key: 'check_out', label: t('attendance.daily_report.col_check_out') },
    { key: 'late_minutes', label: t('attendance.daily_report.col_late_minutes') },
    { key: 'notes', label: t('attendance.daily_report.col_notes') },
]);

function filterParams() { return { date: date.value, cutoff_time: cutoffTime.value, branch_id: branchId.value || undefined, department_ids: departmentIds.value.length ? departmentIds.value : undefined, user_id: userId.value || undefined, status: status.value || undefined }; }
function applyFilters() { router.get(route('attendance.daily-summaries.daily-report'), filterParams(), { preserveState: true, replace: true }); }
function exportReport() { window.location.href = route('attendance.daily-summaries.daily-report.export', filterParams()); }
function badgeVariant(status) { return ({ present: 'active', late: 'warning', absent: 'absent', awaiting: 'pending', leave: 'info', mission: 'primary', incomplete: 'warning', no_fingerprint: 'neutral', rest: 'neutral', holiday: 'neutral' }[status] || 'neutral'); }


usePageTitle(t('attendance.daily_report.title'));
</script>

<template>
    
        <PageHeader :title="t('attendance.daily_report.title')" :description="t('attendance.daily_report.description')">
            <template #actions><Button variant="primary" icon="fas fa-file-word" @click="exportReport">{{ t('attendance.daily_report.export_word') }}</Button></template>
        </PageHeader>

        <Card variant="cream-soft" class="mb-5">
            <div class="flex flex-wrap items-end gap-4">
                <FormInput v-model="date" type="date" :label="t('attendance.daily_report.report_date')" />
                <FormInput v-model="cutoffTime" type="time" :label="t('attendance.daily_report.cutoff_time')" />
                <FormSelect v-model="branchId" :label="t('attendance.daily_report.branch')" :options="[{ value: '', label: t('attendance.daily_report.all_branches') }, ...branches]" />
                <FormMultiSelect v-model="departmentIds" :label="t('attendance.daily_report.departments')" :placeholder="t('attendance.daily_report.all_departments')" :options="departments" />
                <FormSelect v-model="userId" :label="t('attendance.daily_report.employee')" :options="[{ value: '', label: t('attendance.daily_report.all_employees') }, ...users]" />
                <FormSelect v-model="status" :label="t('attendance.daily_report.report_type')" :options="statusOptions" />
                <Button variant="primary" icon="fas fa-search" @click="applyFilters">{{ t('attendance.daily_report.view_report') }}</Button>
            </div>
        </Card>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            <StatCard :label="t('attendance.daily_report.total')" :value="report.stats.total || 0" color="info" icon="fas fa-users" />
            <StatCard :label="t('attendance.daily_report.present')" :value="report.stats.present || 0" color="success" icon="fas fa-user-check" />
            <StatCard :label="t('attendance.daily_report.late')" :value="report.stats.late || 0" color="warning" icon="fas fa-clock" />
            <StatCard :label="t('attendance.daily_report.absent')" :value="report.stats.absent || 0" color="danger" icon="fas fa-user-xmark" />
            <StatCard :label="t('attendance.daily_report.awaiting')" :value="report.stats.awaiting || 0" color="warning" icon="fas fa-hourglass-half" />
            <StatCard :label="t('attendance.daily_report.leave')" :value="report.stats.leave || 0" color="info" icon="fas fa-umbrella-beach" />
            <StatCard :label="t('attendance.daily_report.mission')" :value="report.stats.mission || 0" color="warning" icon="fas fa-briefcase" />
            <StatCard :label="t('attendance.daily_report.incomplete')" :value="report.stats.incomplete || 0" color="warning" icon="fas fa-fingerprint" />
        </div>

        <Card variant="base" padding="none" class="overflow-hidden">
            <div class="px-5 py-3 text-[12px] text-mistral-steel border-b border-mistral-hairline-soft">
                {{ t('attendance.daily_report.checkout_note') }}
            </div>
            <DataTable :columns="columns" :data="data" storage-key="attendance-daily-report" :enable-search="true" :enable-filters="false" :enable-export="false" :enable-pagination="false">
                <template #cell-status_label="{ row }"><Badge :text="row.status_label" :variant="badgeVariant(row.status)" dot /></template>
                <template #cell-check_in="{ row }"><span dir="ltr">{{ row.check_in || '—' }}</span></template>
                <template #cell-check_out="{ row }"><span dir="ltr">{{ row.check_out || '—' }}</span></template>
                <template #cell-notes="{ row }"><span class="text-[12px] text-mistral-steel">{{ row.notes || '—' }}</span></template>
            </DataTable>
        </Card>
    </template>
