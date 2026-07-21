<script setup>
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, StatCard, FormInput, Alert, DataTable } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    kpis: { type: Object, default: () => ({}) },
    trend: { type: Array, default: () => [] },
    departmentComparison: { type: Array, default: () => [] },
    topLate: { type: Array, default: () => [] },
});

const from = ref(props.filters?.from ?? new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10));
const to = ref(props.filters?.to ?? new Date().toISOString().slice(0, 10));
const date = ref(props.filters?.date ?? new Date().toISOString().slice(0, 10));

function applyFilters() {
    router.get(
        route('attendance.reports.index'),
        { from: from.value, to: to.value, date: date.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function exportReport() {
    window.location.href = route('attendance.reports.export', { from: from.value, to: to.value, date: date.value });
}

const trendMax = computed(() => {
    return Math.max(1, ...props.trend.map((d) => (d.present || 0) + (d.absent || 0) + (d.late || 0)));
});

const flashSuccess = computed(() => page.props.flash?.success);

const deptColumns = [
    { key: 'department_name', label: t('attendance.fields.department') },
    { key: 'employees', label: t('attendance.reports_page.employees') },
    { key: 'present_days', label: t('attendance.reports_page.present_days') },
    { key: 'late_days', label: t('attendance.reports_page.late_days') },
    { key: 'absent_days', label: t('attendance.reports_page.absent_days') },
    { key: 'overtime_minutes', label: t('attendance.reports_page.overtime_minutes') },
];

const topLateColumns = [
    { key: 'name', label: t('attendance.reports_page.employee_name') },
    { key: 'late_minutes', label: t('attendance.reports_page.late_minutes') },
    { key: 'absent_days', label: t('attendance.reports_page.absent_days') },
];

const deptData = computed(() => ({ data: props.departmentComparison, links: [] }));
const topLateData = computed(() => ({ data: props.topLate, links: [] }));
</script>

<template>
    <AppLayout :title="t('attendance.reports_page.title')">
        <PageHeader
            :title="t('attendance.reports_page.title')"
            :description="t('attendance.reports_page.index_description')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-download" @click="exportReport">
                    {{ t('common.export') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <Card variant="base" padding="none" class="mb-4">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-3 flex-wrap">
                    <FormInput
                        v-model="date"
                        type="date"
                        :label="t('attendance.fields.date')"
                        class="max-w-[170px]"
                    />
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

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-mistral-ink">
            {{ t('attendance.reports_page.daily_kpis') }}
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <StatCard :label="t('attendance.kpis.present')" :value="kpis.present || 0" color="success" icon="fas fa-user-check" />
            <StatCard :label="t('attendance.kpis.late')" :value="kpis.late || 0" color="warning" icon="fas fa-clock" />
            <StatCard :label="t('attendance.kpis.absent')" :value="kpis.absent || 0" color="danger" icon="fas fa-user-times" />
            <StatCard :label="t('attendance.kpis.early_leave')" :value="kpis.early_leave || 0" color="info" icon="fas fa-sign-out-alt" />
            <StatCard :label="t('attendance.kpis.missing_punch')" :value="kpis.missing_punch || 0" color="warning" icon="fas fa-exclamation-triangle" />
            <StatCard :label="t('attendance.kpis.total')" :value="kpis.total || 0" color="info" icon="fas fa-users" />
        </div>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-mistral-ink">
            {{ t('attendance.reports_page.daily_trend') }}
        </h3>
        <Card variant="base" padding="none" class="mb-6">
            <div class="p-5 sm:p-6">
                <div v-if="trend.length === 0" class="text-center text-mistral-steel py-8">
                    —
                </div>
                <div v-else class="grid grid-cols-7 gap-2 items-end" style="min-height:160px;">
                    <div
                        v-for="d in trend"
                        :key="d.date"
                        class="flex flex-col items-center gap-1"
                    >
                        <div
                            class="w-full rounded-t-lg"
                            :style="{
                                height: ((d.present + d.absent + d.late) / trendMax * 140) + 'px',
                                background: 'var(--color-mistral-primary)',
                                opacity: 0.7,
                            }"
                            :title="`${d.date}: ${d.present} / ${d.late} / ${d.absent}`"
                        ></div>
                        <span class="text-[10px] text-mistral-steel" dir="ltr">
                            {{ d.date.slice(5) }}
                        </span>
                    </div>
                </div>
            </div>
        </Card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Card variant="base" padding="none">
                <div class="px-5 py-3 border-b border-mistral-hairline-soft">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ t('attendance.reports_page.department_comparison') }}
                    </h3>
                </div>
                <DataTable
                    :columns="deptColumns"
                    :data="deptData"
                    storage-key="dept-comparison"
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
                        {{ t('attendance.reports_page.top_late') }}
                    </h3>
                </div>
                <DataTable
                    :columns="topLateColumns"
                    :data="topLateData"
                    storage-key="top-late"
                    :enable-search="false"
                    :enable-filters="false"
                    :enable-pagination="false"
                    :enable-export="false"
                    :enable-density="false"
                    :enable-column-visibility="false"
                    :selectable="false"
                >
                    <template #cell-name="{ row }">
                        <Link :href="route('attendance.reports.user', row.user_id)" class="text-mistral-primary font-semibold">
                            {{ row.name || ('#' + row.user_id) }}
                        </Link>
                    </template>
                </DataTable>
            </Card>
        </div>
    </AppLayout>
</template>
