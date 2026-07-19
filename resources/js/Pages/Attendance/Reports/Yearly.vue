<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, StatCard, FormInput, Alert, DataTable } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    kpis: { type: Object, default: () => ({}) },
    months: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const year = ref(props.filters?.year ?? new Date().getFullYear());

function applyFilters() {
    router.get(
        route('attendance.reports.yearly'),
        { year: year.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const flashSuccess = computed(() => page.props.flash?.success);

const monthlyColumns = [
    { key: 'month', label: t('attendance.fields.month') },
    { key: 'records', label: t('attendance.monthly_page.records') },
    { key: 'present', label: t('attendance.kpis.present') },
    { key: 'absent', label: t('attendance.kpis.absent') },
    { key: 'late', label: t('attendance.kpis.late') },
    { key: 'work_minutes', label: t('attendance.monthly_page.work_minutes') },
    { key: 'overtime_minutes', label: t('attendance.monthly_page.overtime_minutes') },
];

const userColumns = [
    { key: 'name', label: t('attendance.fields.user') },
    { key: 'present_days', label: t('attendance.reports_page.present_days') },
    { key: 'absent_days', label: t('attendance.reports_page.absent_days') },
    { key: 'late_days', label: t('attendance.reports_page.late_days') },
    { key: 'work_minutes', label: t('attendance.monthly_page.work_minutes') },
    { key: 'overtime_minutes', label: t('attendance.monthly_page.overtime_minutes') },
];

const deptColumns = [
    { key: 'department_name', label: t('attendance.fields.department') },
    { key: 'employees', label: t('attendance.reports_page.employees') },
    { key: 'present_days', label: t('attendance.reports_page.present_days') },
    { key: 'absent_days', label: t('attendance.reports_page.absent_days') },
    { key: 'late_days', label: t('attendance.reports_page.late_days') },
    { key: 'overtime_minutes', label: t('attendance.monthly_page.overtime_minutes') },
];

const monthlyData = computed(() => ({ data: props.months, links: [] }));
const userData = computed(() => ({ data: props.users, links: [] }));
const deptData = computed(() => ({ data: props.departments, links: [] }));
</script>

<template>
    <AppLayout :title="t('attendance.yearly_page.title')">
        <PageHeader
            :title="t('attendance.yearly_page.title')"
            :description="t('attendance.yearly_page.description')"
        />

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <Card variant="base" padding="none" class="mb-4">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-3 flex-wrap">
                    <FormInput
                        v-model.number="year"
                        type="number"
                        :label="t('attendance.fields.year')"
                        min="2000"
                        max="2100"
                        class="max-w-[120px]"
                    />
                    <Button variant="primary" icon="fas fa-search" @click="applyFilters" class="self-end">
                        {{ t('common.search') }}
                    </Button>
                </div>
            </div>
        </Card>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-mistral-ink">
            {{ t('attendance.yearly_page.yearly_kpis') }}
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <StatCard :label="t('attendance.kpis.total')" :value="kpis.totals?.records || 0" color="info" icon="fas fa-database" />
            <StatCard :label="t('attendance.yearly_page.working_days')" :value="kpis.working_days || 0" color="info" icon="fas fa-calendar-day" />
            <StatCard :label="t('attendance.monthly_page.work_minutes')" :value="kpis.totals?.work_minutes || 0" color="success" icon="fas fa-briefcase" />
            <StatCard :label="t('attendance.monthly_page.overtime_minutes')" :value="kpis.totals?.overtime_minutes || 0" color="warning" icon="fas fa-hourglass-half" />
        </div>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-mistral-ink">
            {{ t('attendance.yearly_page.monthly_breakdown') }}
        </h3>
        <Card variant="base" padding="none" class="mb-6">
            <DataTable
                :columns="monthlyColumns"
                :data="monthlyData"
                storage-key="yearly-monthly"
                :enable-search="false"
                :enable-filters="false"
                :enable-pagination="false"
                :enable-export="false"
                :enable-density="false"
                :enable-column-visibility="false"
                :selectable="false"
            >
                <template #cell-month="{ value }">
                    <span dir="ltr">{{ String(value).padStart(2, '0') }}</span>
                </template>
            </DataTable>
        </Card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Card variant="base" padding="none">
                <div class="px-5 py-3 border-b border-mistral-hairline-soft">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ t('attendance.yearly_page.user_table') }}
                    </h3>
                </div>
                <DataTable
                    :columns="userColumns"
                    :data="userData"
                    storage-key="yearly-users"
                    :enable-search="false"
                    :enable-filters="false"
                    :enable-pagination="false"
                    :enable-export="false"
                    :enable-density="false"
                    :enable-column-visibility="false"
                    :selectable="false"
                />
            </Card>

            <Card variant="base" padding="none">
                <div class="px-5 py-3 border-b border-mistral-hairline-soft">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ t('attendance.yearly_page.department_table') }}
                    </h3>
                </div>
                <DataTable
                    :columns="deptColumns"
                    :data="deptData"
                    storage-key="yearly-departments"
                    :enable-search="false"
                    :enable-filters="false"
                    :enable-pagination="false"
                    :enable-export="false"
                    :enable-density="false"
                    :enable-column-visibility="false"
                    :selectable="false"
                />
            </Card>
        </div>
    </AppLayout>
</template>
