<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, StatCard, Badge, FormInput, FormSelect, DataTable } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    userId: { type: [String, Number], required: true },
    employeeName: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
    report: { type: Object, default: () => ({ totals: {}, by_status: {}, sessions: [] }) },
    overtime: { type: Object, default: () => ({ by_day: [] }) },
    monthlyLog: { type: Array, default: () => [] },
    monthlyLogFilters: { type: Object, default: () => ({}) },
});

const from = ref(props.filters?.from ?? new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
const to = ref(props.filters?.to ?? new Date().toISOString().slice(0, 10));
const monthlyYear = ref(props.monthlyLogFilters?.year ?? new Date().getFullYear());
const monthlyMonth = ref(props.monthlyLogFilters?.month ?? new Date().getMonth() + 1);
const printTimestamp = ref('');
const monthOptions = Array.from({ length: 12 }, (_, index) => ({
    value: index + 1,
    label: new Intl.DateTimeFormat('ar', { month: 'long' }).format(new Date(2026, index, 1)),
}));

// Inertia keeps this page component mounted while the filter changes. Keep the
// controls in sync with the server-confirmed month after every visit.
watch(
    () => props.monthlyLogFilters,
    (filters) => {
        monthlyYear.value = filters?.year ?? new Date().getFullYear();
        monthlyMonth.value = filters?.month ?? new Date().getMonth() + 1;
    },
    { deep: true },
);

function applyFilters() {
    router.get(
        route('attendance.reports.user', props.userId),
        { from: from.value, to: to.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function applyMonthlyLogFilters() {
    router.get(
        route('attendance.reports.user', props.userId),
        { from: from.value, to: to.value, year: monthlyYear.value, month: monthlyMonth.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function exportMonthlyLog() {
    window.location.href = route('attendance.reports.user.monthly-log.export', {
        user: props.userId,
        year: monthlyYear.value,
        month: monthlyMonth.value,
    });
}

/** Print the monthly attendance log without opening a browser tab. */
function printMonthlyLog() {
    printTimestamp.value = new Intl.DateTimeFormat('en-CA', {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).format(new Date()).replace(',', '');

    const printFrame = document.createElement('iframe');
    printFrame.setAttribute('aria-hidden', 'true');
    Object.assign(printFrame.style, {
        position: 'fixed',
        width: '1px',
        height: '1px',
        border: '0',
        opacity: '0',
        pointerEvents: 'none',
    });
    document.body.appendChild(printFrame);

    nextTick(() => {
        const printContent = document.querySelector('.monthly-log-print')?.outerHTML;
        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
            .map((element) => element.outerHTML)
            .join('');

        if (!printContent) {
            printFrame.remove();
            return;
        }

        const printDocument = printFrame.contentDocument;
        if (!printDocument) {
            printFrame.remove();
            return;
        }

        printDocument.write(`<!doctype html>
            <html dir="rtl">
                <head>
                    <meta charset="utf-8">
                    <title>${t('attendance.monthly_employee_log.title')}</title>
                    ${styles}
                </head>
                <body>${printContent}</body>
            </html>`);
        printDocument.close();

        window.setTimeout(() => {
            printFrame.contentWindow?.focus();
            printFrame.contentWindow?.print();
            window.setTimeout(() => printFrame.remove(), 1000);
        }, 250);
    });
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
const monthlyLogData = computed(() => ({ data: props.monthlyLog, links: [] }));
const monthlyMonthLabel = computed(() => monthOptions.find(({ value }) => value === monthlyMonth.value)?.label || '');
const scheduleStatusLabels = {
    work: 'دوام',
    rest: 'يوم راحة',
    leave_excused: 'إجازة',
    swap: 'تبديل دوام',
    unassigned: 'بدون إسناد',
};
const scheduleStatusLabel = (status) => scheduleStatusLabels[status] || status;
const monthlyLogColumns = [
    { key: 'date', label: t('attendance.fields.date') },
    { key: 'day_name', label: t('attendance.monthly_employee_log.day') },
    { key: 'schedule_status', label: t('attendance.monthly_employee_log.schedule_status') },
    { key: 'expected_check_in', label: t('attendance.fields.expected_check_in') },
    { key: 'expected_check_out', label: t('attendance.fields.expected_check_out') },
    { key: 'check_in_window', label: t('attendance.monthly_employee_log.check_in_window') },
    { key: 'first_check_in_at', label: t('attendance.fields.first_check_in_at') },
    { key: 'check_out_window', label: t('attendance.monthly_employee_log.check_out_window') },
    { key: 'last_check_out_at', label: t('attendance.fields.last_check_out_at') },
];
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

        <div class="flex items-center justify-between gap-3 flex-wrap mt-6 mb-2">
            <h3 class="text-[16px] font-semibold text-mistral-ink">
                {{ t('attendance.monthly_employee_log.title') }}
            </h3>
            <div class="flex items-center gap-2">
                <Button
                    variant="secondary"
                    icon="fas fa-print"
                    @click="printMonthlyLog"
                >
                    {{ t('attendance.monthly_employee_log.print') }}
                </Button>
                <Button
                    variant="secondary"
                    icon="fas fa-file-excel"
                    @click="exportMonthlyLog"
                >
                    {{ t('attendance.monthly_employee_log.export_excel') }}
                </Button>
            </div>
        </div>
        <section class="monthly-log-print">
            <header class="monthly-log-print-heading">
                <h1>{{ t('attendance.monthly_employee_log.title') }}</h1>
                <p class="monthly-log-print-employee">{{ t('attendance.monthly_employee_log.export_subtitle', { employee: employeeName || userId, month: monthlyMonthLabel }) }}</p>
                <p>{{ t('attendance.monthly_employee_log.export_date') }}: {{ printTimestamp }}</p>
            </header>
            <Card variant="base" padding="none">
                <div class="monthly-log-controls p-5 sm:p-6 border-b border-mistral-hairline-soft">
                    <div class="flex items-center gap-3 flex-wrap">
                        <FormInput v-model.number="monthlyYear" type="number" :label="t('attendance.fields.year')" class="max-w-[120px]" />
                        <FormSelect v-model.number="monthlyMonth" :options="monthOptions" :label="t('attendance.fields.month')" class="max-w-[120px]" />
                        <Button variant="primary" icon="fas fa-search" @click="applyMonthlyLogFilters" class="self-end">
                            {{ t('common.search') }}
                        </Button>
                    </div>
                </div>
                <DataTable
                    :columns="monthlyLogColumns"
                    :data="monthlyLogData"
                    storage-key="employee-monthly-attendance-log"
                    :enable-search="false"
                    :enable-filters="false"
                    :enable-pagination="false"
                    :enable-export="false"
                    :enable-density="false"
                    :enable-column-visibility="false"
                    :selectable="false"
                >
                    <template #cell-schedule_status="{ row }">
                        {{ scheduleStatusLabel(row.schedule_status) }}
                    </template>
                </DataTable>
            </Card>
        </section>

        <h3 class="text-[16px] font-semibold mt-6 mb-2 text-mistral-ink">
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

<style>
.monthly-log-print-heading {
    display: none;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    body * {
        visibility: hidden;
    }

    .monthly-log-print,
    .monthly-log-print * {
        visibility: visible;
    }

    .monthly-log-print {
        position: absolute;
        inset: 0;
        width: 100%;
        direction: rtl;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .monthly-log-print * {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .monthly-log-controls {
        display: none !important;
    }

    .monthly-log-print-heading {
        display: block;
        margin: 0 0 4mm;
        text-align: center;
        font-family: Cairo, sans-serif;
    }

    .monthly-log-print-heading h1 {
        margin: 0 0 2mm;
        color: #fa520f;
        font-size: 18px;
        font-weight: 700;
    }

    .monthly-log-print-heading p {
        margin: 1mm 0;
        color: #2c3e50;
        font-size: 10px;
        font-weight: 700;
    }

    .monthly-log-print-heading .monthly-log-print-employee {
        font-size: 13px;
    }

    .monthly-log-print-heading p:last-child {
        color: #666;
        font-size: 8px;
        font-weight: 400;
    }

    .monthly-log-print .overflow-x-auto {
        overflow: visible !important;
    }

    .monthly-log-print table {
        width: 100% !important;
        table-layout: fixed;
        font-family: Cairo, sans-serif;
        font-size: 6px !important;
    }

    .monthly-log-print th,
    .monthly-log-print td {
        padding: 1.5px !important;
        line-height: 1.15 !important;
        white-space: normal !important;
        overflow-wrap: anywhere;
        border: 1px solid #ddd !important;
        text-align: center !important;
        vertical-align: middle;
    }

    .monthly-log-print th {
        background: #fa520f !important;
        color: #fff !important;
        padding: 4px 1.5px !important;
        font-size: 10px !important;
        font-weight: 700;
    }

    .monthly-log-print tbody tr:nth-child(even) td {
        background: #f7f2ec !important;
    }

    .monthly-log-print th:nth-child(1),
    .monthly-log-print td:nth-child(1) { width: 12.1%; }
    .monthly-log-print th:nth-child(2),
    .monthly-log-print td:nth-child(2) { width: 6.6%; }
    .monthly-log-print th:nth-child(3),
    .monthly-log-print td:nth-child(3) { width: 9.9%; }
    .monthly-log-print th:nth-child(4),
    .monthly-log-print td:nth-child(4) { width: 12.6%; }
    .monthly-log-print th:nth-child(5),
    .monthly-log-print td:nth-child(5) { width: 13.6%; }
    .monthly-log-print th:nth-child(6),
    .monthly-log-print td:nth-child(6) { width: 13.8%; }
    .monthly-log-print th:nth-child(7),
    .monthly-log-print td:nth-child(7) { width: 8.4%; }
    .monthly-log-print th:nth-child(8),
    .monthly-log-print td:nth-child(8) { width: 13.8%; }
    .monthly-log-print th:nth-child(9),
    .monthly-log-print td:nth-child(9) { width: 9.2%; }

    .monthly-log-print tr {
        break-inside: avoid;
    }
}
</style>
