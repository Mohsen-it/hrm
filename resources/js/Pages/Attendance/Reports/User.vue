<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { ref, computed, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { PageHeader, Button, Card, StatCard, Badge, FormInput, FormSelect, FormSwitch, DataTable } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';
import { CHART_VACATION as VACATION_FALLBACK_COLOR } from '@/utils/chartPalette';

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
const overtimePrintTimestamp = ref('');
const overtimeFrom = ref(props.filters?.from ?? new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
const overtimeTo = ref(props.filters?.to ?? new Date().toISOString().slice(0, 10));
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

const showLate = ref(true);

function exportMonthlyLog() {
    window.location.href = route('attendance.reports.user.monthly-log.export', {
        user: props.userId,
        year: monthlyYear.value,
        month: monthlyMonth.value,
        with_late: showLate.value ? 1 : 0,
    });
}

function applyOvertimeFilters() {
    router.get(
        route('attendance.reports.user', props.userId),
        { from: overtimeFrom.value, to: overtimeTo.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function exportOvertimeReport() {
    window.location.href = route('attendance.reports.user.overtime.export', {
        user: props.userId,
        from: overtimeFrom.value,
        to: overtimeTo.value,
    });
}

/** Print the overtime report without opening a browser tab. */
function printOvertimeReport() {
    overtimePrintTimestamp.value = new Intl.DateTimeFormat('en-CA', {
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
        const printContent = document.querySelector('.overtime-report-print')?.outerHTML;
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
                    <title>${t('attendance.overtime_report.title')}</title>
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

// Today's date in local timezone (avoids the UTC shift of toISOString).
const todayStr = (() => {
    const d = new Date();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
})();

const isFutureRow = (row) => row?.is_future ?? (row?.date > todayStr);

// Days after today are in the future: no punches exist yet, so they are
// hidden from the screen table, the totals and the printed record.
const visibleMonthlyLog = computed(() => (props.monthlyLog || []).filter((row) => !isFutureRow(row)));
const monthlyLogData = computed(() => ({ data: visibleMonthlyLog.value, links: [] }));
const overtimeData = computed(() => ({ data: (props.overtime?.daily_details || []).filter(d => d.overtime_minutes > 0), links: [] }));
const monthlyMonthLabel = computed(() => monthOptions.find(({ value }) => value === monthlyMonth.value)?.label || '');
const scheduleStatusLabels = {
    work: 'دوام',
    rest: 'يوم راحة',
    leave_excused: 'إجازة',
    swap: 'تبديل دوام',
    unassigned: 'بدون إسناد',
};
const scheduleStatusLabel = (status) => scheduleStatusLabels[status] || status;
const baseMonthlyLogColumns = [
    { key: 'date', label: t('attendance.fields.date') },
    { key: 'day_name', label: t('attendance.monthly_employee_log.day') },
    { key: 'schedule_status', label: t('attendance.monthly_employee_log.schedule_status') },
    { key: 'expected_check_in', label: t('attendance.fields.expected_check_in') },
    { key: 'expected_check_out', label: t('attendance.fields.expected_check_out') },
    { key: 'first_check_in_at', label: t('attendance.fields.first_check_in_at') },
    { key: 'last_check_out_at', label: t('attendance.fields.last_check_out_at') },
];

const monthlyLogColumns = computed(() => {
    const notes = { key: 'notes', label: t('attendance.monthly_employee_log.notes') };
    if (!showLate.value) return [...baseMonthlyLogColumns, notes];
    return [
        ...baseMonthlyLogColumns,
        { key: 'late_minutes', label: t('attendance.monthly_employee_log.late_minutes') },
        { key: 'early_leave_minutes', label: t('attendance.monthly_employee_log.early_leave') },
        notes,
    ];
});

const totalEntryLateMinutes = computed(() =>
    visibleMonthlyLog.value.reduce((sum, row) => sum + (Number(row.late_minutes) || 0), 0),
);

const totalEarlyLeaveMinutes = computed(() =>
    visibleMonthlyLog.value.reduce((sum, row) => sum + (Number(row.early_leave_minutes) || 0), 0),
);

/** Normalize a vacation-type color to a safe #RRGGBB string. */
const sanitizeHexColor = (color) => {
    const hex = String(color || '').replace(/^#/, '').toUpperCase();
    if (/^[0-9A-F]{6}$/.test(hex)) return `#${hex}`;
    if (/^[0-9A-F]{3}$/.test(hex)) return `#${hex[0]}${hex[0]}${hex[1]}${hex[1]}${hex[2]}${hex[2]}`;
    return VACATION_FALLBACK_COLOR;
};

/**
 * Vacation display for a row. A vacation only means something on a day the
 * employee was expected to work (leave_excused) — on rotation rest days the
 * resolver keeps "rest" and the vacation must not repaint the row.
 */
const vacationOf = (row) => {
    if (row?.schedule_status !== 'leave_excused' || !row?.vacation_type) return null;
    return { type: row.vacation_type, color: sanitizeHexColor(row.vacation_type_color) };
};

const vacationBadgeStyle = (row) => {
    const { color } = vacationOf(row);
    return {
        backgroundColor: `${color}1F`,
        color,
        border: `1px solid ${color}66`,
    };
};

const humanHours = (mins) => `${(mins / 60).toFixed(2)} ${t('attendance.monthly_employee_log.hours')} (${mins} ${t('attendance.monthly_employee_log.minutes')})`;

const totalLateHuman = computed(() => humanHours(totalEntryLateMinutes.value + totalEarlyLeaveMinutes.value));
const totalEntryLateHuman = computed(() => humanHours(totalEntryLateMinutes.value));
const totalEarlyLeaveHuman = computed(() => humanHours(totalEarlyLeaveMinutes.value));

const overtimeColumns = [
    { key: 'date', label: t('attendance.fields.date') },
    { key: 'day_name', label: t('attendance.monthly_employee_log.day') },
    { key: 'expected_check_in', label: t('attendance.fields.expected_check_in') },
    { key: 'expected_check_out', label: t('attendance.fields.expected_check_out') },
    { key: 'actual_check_in', label: t('attendance.fields.first_check_in_at') },
    { key: 'actual_check_out', label: t('attendance.fields.last_check_out_at') },
    { key: 'expected_work_minutes', label: 'الوقت المتوقع (ساعة)' },
    { key: 'work_minutes', label: 'وقت العمل (ساعة)' },
    { key: 'overtime_minutes', label: 'ساعات العمل الإضافي' },
];

const formatHours = (minutes) => {
    const m = Number(minutes) || 0;
    return (m / 60).toFixed(2);
};

const formatHuman = formatHours;

const totalOvertimeHours = computed(() => {
    const minutes = props.overtime?.overtime_minutes || 0;
    return formatHours(minutes);
});

const overtimeFilteredRows = computed(() => (props.overtime?.daily_details || []).filter(d => d.overtime_minutes > 0));
const overtimeTotals = computed(() => {
    let exp = 0, work = 0, ot = props.overtime?.overtime_minutes || 0;
    for (const r of overtimeFilteredRows.value) {
        exp += Number(r.expected_work_minutes) || 0;
        work += Number(r.work_minutes) || 0;
    }
    if (overtimeFilteredRows.value.length && ot === 0) {
        ot = overtimeFilteredRows.value.reduce((s, r) => s + (Number(r.overtime_minutes) || 0), 0);
    }
    // تأكيد أن الإجمالي هو مجموع الصفوف الظاهرة فعلاً (لمنع خطأ سابق)
    const recomputedOt = overtimeFilteredRows.value.reduce((s, r) => s + (Number(r.overtime_minutes) || 0), 0);
    if (recomputedOt !== ot && overtimeFilteredRows.value.length) {
        ot = recomputedOt;
    }
    return { exp, work, ot, expHuman: formatHours(exp), workHuman: formatHours(work), otHuman: formatHours(ot) };
});


const headerTitle = computed(() => `${t('attendance.user_report')} — ${props.employeeName || `#${props.userId}`}`);

usePageTitle(headerTitle.value);
</script>

<template>
    
        <PageHeader
            :title="headerTitle"
            :description="`${report.from} → ${report.to}`"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('attendance.reports.user.index')">
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
                        <FormSwitch
                            v-model="showLate"
                            :label="t('attendance.monthly_employee_log.show_late')"
                            class="self-end ms-2"
                        />
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
                        <span v-if="vacationOf(row)" class="vacation-badge" :style="vacationBadgeStyle(row)">
                            {{ t('attendance.monthly_employee_log.vacation_prefix') }}: {{ row.vacation_type }}
                        </span>
                        <span v-else>{{ scheduleStatusLabel(row.schedule_status) }}</span>
                    </template>
                    <template #cell-notes="{ row }">
                        <div v-if="row.has_justification" class="justification-note">
                            <i class="fas fa-file-signature shrink-0"></i>
                            <div>
                                <div class="font-bold">{{ t('attendance.monthly_employee_log.justification_note') }}</div>
                                <div>{{ t('attendance.monthly_employee_log.reason') }}: {{ row.justification_reason || '—' }}</div>
                            </div>
                        </div>
                        <span v-else class="text-mistral-muted">—</span>
                    </template>
                    <template #cell-last_check_out_at="{ row }">
                        {{ row.last_check_out_at }}<span v-if="row.is_overnight_checkout" class="font-bold"> (+1)</span>
                    </template>
                    <template #cell-late_minutes="{ row }">
                        {{ row.late_minutes || 0 }}
                    </template>
                    <template #cell-early_leave_minutes="{ row }">
                        {{ row.early_leave_minutes || 0 }}
                    </template>
                    <template #footer>
                        <tr v-if="showLate" class="monthly-log-late-total">
                            <td :colspan="monthlyLogColumns.length - 2" class="text-center font-bold">{{ t('attendance.monthly_employee_log.total_late') }}: {{ totalLateHuman }}</td>
                            <td class="font-bold text-center">{{ totalEntryLateHuman }}</td>
                            <td class="font-bold text-center">{{ totalEarlyLeaveHuman }}</td>
                        </tr>
                    </template>
                </DataTable>
            </Card>
        </section>

        <!-- Overtime Report Section -->
        <div class="flex items-center justify-between gap-3 flex-wrap mt-6 mb-2">
            <h3 class="text-[16px] font-semibold text-mistral-ink">
                {{ t('attendance.overtime_report.title') }}
            </h3>
            <div class="flex items-center gap-2">
                <Button
                    variant="secondary"
                    icon="fas fa-print"
                    @click="printOvertimeReport"
                >
                    {{ t('attendance.monthly_employee_log.print') }}
                </Button>
                <Button
                    variant="secondary"
                    icon="fas fa-file-excel"
                    @click="exportOvertimeReport"
                >
                    {{ t('attendance.monthly_employee_log.export_excel') }}
                </Button>
            </div>
        </div>
        <section class="overtime-report-print">
            <header class="overtime-report-print-heading">
                <h1>{{ t('attendance.overtime_report.title') }}</h1>
                <p class="overtime-report-print-employee">{{ t('attendance.overtime_report.subtitle', { employee: employeeName || userId, from: report.from, to: report.to }) }}</p>
                <p>{{ t('attendance.monthly_employee_log.export_date') }}: {{ overtimePrintTimestamp }}</p>
            </header>
            <Card variant="base" padding="none">
                <div class="overtime-controls p-5 sm:p-6 border-b border-mistral-hairline-soft">
                    <div class="flex items-center gap-3 flex-wrap">
                        <FormInput v-model="overtimeFrom" type="date" :label="t('attendance.fields.from')" class="max-w-[170px]" />
                        <FormInput v-model="overtimeTo" type="date" :label="t('attendance.fields.to')" class="max-w-[170px]" />
                        <Button variant="primary" icon="fas fa-search" @click="applyOvertimeFilters" class="self-end">
                            {{ t('common.search') }}
                        </Button>
                    </div>
                </div>
                <DataTable
                    :columns="overtimeColumns"
                    :data="overtimeData"
                    storage-key="employee-overtime-report"
                    :enable-search="false"
                    :enable-filters="false"
                    :enable-pagination="false"
                    :enable-export="false"
                    :enable-density="false"
                    :enable-column-visibility="false"
                    :selectable="false"
                >
                    <template #cell-expected_work_minutes="{ row }">
                        {{ formatHours(row.expected_work_minutes) }}
                    </template>
                    <template #cell-work_minutes="{ row }">
                        {{ formatHours(row.work_minutes) }}
                    </template>
                    <template #cell-overtime_minutes="{ row }">
                        {{ formatHours(row.overtime_minutes) }}
                    </template>
                    <template #footer>
                        <!-- سطر الشاشة: يعرض 3 مجاميع -->
                        <tr class="overtime-summary-row print:hidden">
                            <td :colspan="6" class="text-center font-bold">{{ t('attendance.overtime_report.total_overtime') }}</td>
                            <td class="font-bold text-center">{{ overtimeTotals.expHuman }}</td>
                            <td class="font-bold text-center">{{ overtimeTotals.workHuman }}</td>
                            <td class="font-bold text-center">{{ overtimeTotals.otHuman }}</td>
                        </tr>
                        <!-- سطر الطباعة: مجموع ساعات العمل الإضافي فقط -->
                        <tr class="overtime-summary-row hidden print:table-row">
                            <td :colspan="8" class="text-center font-bold">{{ t('attendance.overtime_report.total_overtime') }}</td>
                            <td class="font-bold text-center text-[14px]">{{ overtimeTotals.otHuman }}</td>
                        </tr>
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
    </template>

<style>
.monthly-log-print-heading {
    display: none;
}

.vacation-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.justification-note {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.5;
    text-align: start;
    background: var(--color-mistral-warning-bg);
    color: var(--color-mistral-warning);
    border: 1px solid color-mix(in srgb, var(--color-mistral-warning) 35%, transparent);
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

    .monthly-log-controls,
    .overtime-controls {
        display: none !important;
    }

    .monthly-log-print-heading {
        display: block;
        margin: 0 0 4mm;
        text-align: center;
        font-family: 'ITF Qomra Arabic', Cairo, sans-serif;
    }

    .monthly-log-print-heading h1 {
        margin: 0 0 2mm;
        color: var(--color-mistral-primary);
        font-size: 18px;
        font-weight: 700;
    }

    .monthly-log-print-heading p {
        margin: 1mm 0;
        color: var(--color-mistral-charcoal);
        font-size: 10px;
        font-weight: 700;
    }

    .monthly-log-print-heading .monthly-log-print-employee {
        font-size: 13px;
    }

    .monthly-log-print-heading p:last-child {
        color: var(--color-mistral-steel);
        font-size: 8px;
        font-weight: 400;
    }

    .monthly-log-print .overflow-x-auto {
        overflow: visible !important;
    }

    .monthly-log-print table {
        width: 100% !important;
        table-layout: fixed;
        font-family: 'ITF Qomra Arabic', Cairo, sans-serif;
        font-size: 6px !important;
    }

    .monthly-log-print th,
    .monthly-log-print td {
        padding: 1.5px !important;
        line-height: 1.15 !important;
        white-space: normal !important;
        overflow-wrap: anywhere;
        border: 1px solid var(--color-mistral-hairline-strong) !important;
        text-align: center !important;
        vertical-align: middle;
    }

    .monthly-log-print th {
        background: var(--color-mistral-primary) !important;
        color: var(--color-mistral-on-primary) !important;
        padding: 4px 1.5px !important;
        font-size: 10px !important;
        font-weight: 700;
    }

    .monthly-log-print tbody tr:nth-child(even) td {
        background: var(--color-mistral-surface-cream) !important;
    }

    .monthly-log-print th:nth-child(1),
    .monthly-log-print td:nth-child(1) { width: 10%; }
    .monthly-log-print th:nth-child(2),
    .monthly-log-print td:nth-child(2) { width: 5.5%; }
    .monthly-log-print th:nth-child(3),
    .monthly-log-print td:nth-child(3) { width: 9%; }
    .monthly-log-print th:nth-child(4),
    .monthly-log-print td:nth-child(4) { width: 8%; }
    .monthly-log-print th:nth-child(5),
    .monthly-log-print td:nth-child(5) { width: 8%; }
    .monthly-log-print th:nth-child(6),
    .monthly-log-print td:nth-child(6) { width: 8%; }
    .monthly-log-print th:nth-child(7),
    .monthly-log-print td:nth-child(7) { width: 8%; }
    .monthly-log-print th:nth-child(8),
    .monthly-log-print td:nth-child(8) { width: 6.5%; }
    .monthly-log-print th:nth-child(9),
    .monthly-log-print td:nth-child(9) { width: 6.5%; }

    /* عمود الملاحظات دائماً الأخير: يأخذ باقي العرض المتاح سواء ظهر
       عمودا التأخير أم لا (table-layout: fixed يوزع الباقي عليه). */
    .monthly-log-print th:last-child,
    .monthly-log-print td:last-child { width: auto; }

    .monthly-log-print .vacation-badge {
        font-size: 11px !important;
        padding: 3px 12px !important;
    }

    .monthly-log-print .justification-note {
        font-size: 10px !important;
        padding: 5px 8px !important;
        gap: 6px !important;
    }

    .monthly-log-print tr {
        break-inside: avoid;
    }

    .monthly-log-late-total td {
        background: var(--color-mistral-primary) !important;
        color: var(--color-mistral-on-primary) !important;
        font-size: 11px !important;
        font-weight: 800 !important;
        padding: 5px 3px !important;
        border: 1px solid var(--color-mistral-hairline-strong) !important;
    }
}

.overtime-report-print-heading {
    display: none;
}

@media print {
    .overtime-report-print,
    .overtime-report-print * {
        visibility: visible;
    }

    .overtime-report-print {
        position: absolute;
        inset: 0;
        width: 100%;
        direction: rtl;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .overtime-report-print * {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .overtime-report-print-heading {
        display: block;
        margin: 0 0 4mm;
        text-align: center;
        font-family: 'ITF Qomra Arabic', Cairo, sans-serif;
    }

    .overtime-report-print-heading h1 {
        margin: 0 0 2mm;
        color: var(--color-mistral-primary);
        font-size: 18px;
        font-weight: 700;
    }

    .overtime-report-print-heading p {
        margin: 1mm 0;
        color: var(--color-mistral-charcoal);
        font-size: 10px;
        font-weight: 700;
    }

    .overtime-report-print-heading .overtime-report-print-employee {
        font-size: 13px;
    }

    .overtime-report-print-heading p:last-child {
        color: var(--color-mistral-steel);
        font-size: 8px;
        font-weight: 400;
    }

    .overtime-report-print .overflow-x-auto {
        overflow: visible !important;
    }

    .overtime-report-print table {
        width: 100% !important;
        table-layout: fixed;
        font-family: 'ITF Qomra Arabic', Cairo, sans-serif;
        font-size: 11px !important;
    }

    .overtime-report-print th,
    .overtime-report-print td {
        padding: 5px 3px !important;
        line-height: 1.4 !important;
        white-space: normal !important;
        overflow-wrap: anywhere;
        border: 1px solid var(--color-mistral-hairline-strong) !important;
        text-align: center !important;
        vertical-align: middle;
    }

    .overtime-report-print th {
        background: var(--color-mistral-primary) !important;
        color: var(--color-mistral-on-primary) !important;
        padding: 7px 3px !important;
        font-size: 13px !important;
        font-weight: 700;
    }

    .overtime-report-print tbody tr:nth-child(even) td {
        background: var(--color-mistral-surface-cream) !important;
    }

    /* إخفاء عمودي الحضور المتوقع/الانصراف المتوقع في نسخة الطباعة فقط - لا يؤثر على الفوتر */
    .overtime-report-print thead th:nth-child(3),
    .overtime-report-print tbody td:nth-child(3),
    .overtime-report-print thead th:nth-child(4),
    .overtime-report-print tbody td:nth-child(4) {
        display: none !important;
    }

    /* توزيع عرض الأعمدة الظاهرة (7 أعمدة) = 100% */
    .overtime-report-print thead th:nth-child(1),
    .overtime-report-print tbody td:nth-child(1) { width: 15%; }
    .overtime-report-print thead th:nth-child(2),
    .overtime-report-print tbody td:nth-child(2) { width: 10%; }
    .overtime-report-print thead th:nth-child(5),
    .overtime-report-print tbody td:nth-child(5) { width: 15%; }
    .overtime-report-print thead th:nth-child(6),
    .overtime-report-print tbody td:nth-child(6) { width: 15%; }
    .overtime-report-print thead th:nth-child(7),
    .overtime-report-print tbody td:nth-child(7) { width: 15%; }
    .overtime-report-print thead th:nth-child(8),
    .overtime-report-print tbody td:nth-child(8) { width: 15%; }
    .overtime-report-print thead th:nth-child(9),
    .overtime-report-print tbody td:nth-child(9) { width: 15%; }

    .overtime-report-print tr {
        break-inside: avoid;
    }

    .overtime-summary-row td {
        background: var(--color-mistral-primary) !important;
        color: var(--color-mistral-on-primary) !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        padding: 7px 3px !important;
        border: 1px solid var(--color-mistral-hairline-strong) !important;
    }
}
</style>
