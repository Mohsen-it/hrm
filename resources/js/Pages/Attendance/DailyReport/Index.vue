<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();

import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { PageHeader, Button, Card, StatCard, FormInput, FormSelect, FormSearchableSelect, FormMultiSelect, DataTable, Badge, Alert, Avatar } from '@/Components/ui';

const props = defineProps({
    report: { type: Object, default: () => ({ rows: [], stats: {} }) },
    filters: { type: Object, default: () => ({}) },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

// ------------------------------------------------------------------
// Filter state
// ------------------------------------------------------------------

// Local-timezone "today" — toISOString() is UTC and lands on yesterday
// for UTC+ timezones during the first hours of the day.
function localDateStr(d = new Date()) {
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
}
const todayStr = localDateStr();

const date = ref(props.filters.date || todayStr);
const cutoffTime = ref(props.filters.cutoff_time || '09:00');
const branchId = ref(props.filters.branch_id || '');
const departmentIds = ref(
    Array.isArray(props.filters.department_ids) && props.filters.department_ids.length
        ? props.filters.department_ids.map(Number)
        : (props.filters.department_id ? [Number(props.filters.department_id)] : []),
);
const userId = ref(props.filters.user_id || '');
const status = ref(props.filters.status || '');
const searchQuery = ref('');
const isLoading = ref(false);

const isToday = computed(() => date.value === todayStr);

const branchOptions = computed(() => [
    { value: '', label: t('attendance.daily_report.all_branches') },
    ...props.branches,
]);
const userOptions = computed(() => [
    { value: '', label: t('attendance.daily_report.all_employees') },
    ...props.users,
]);

// ------------------------------------------------------------------
// Stats: KPI cards + interactive status chips
// ------------------------------------------------------------------

const stats = computed(() => props.report.stats || {});

// The four headline counters a manager reads first; clicking one drills
// the table down to that group ("الإجمالي" clears the drill-down).
const kpiCards = computed(() => [
    { statusKey: '', label: t('attendance.daily_report.total'), value: stats.value.total || 0, color: 'info', icon: 'fas fa-users' },
    { statusKey: 'present', label: t('attendance.daily_report.present'), value: stats.value.present || 0, color: 'success', icon: 'fas fa-user-check' },
    { statusKey: 'late', label: t('attendance.daily_report.late'), value: stats.value.late || 0, color: 'warning', icon: 'fas fa-clock' },
    { statusKey: 'absent', label: t('attendance.daily_report.absent'), value: stats.value.absent || 0, color: 'danger', icon: 'fas fa-user-xmark' },
]);

const chipTones = {
    primary: {
        active: 'bg-mistral-primary/10 border-mistral-primary/50 text-mistral-primary',
        icon: 'text-mistral-primary',
        countActive: 'bg-mistral-primary text-white',
    },
    success: {
        active: 'bg-mistral-success/10 border-mistral-success/50 text-mistral-success',
        icon: 'text-mistral-success',
        countActive: 'bg-mistral-success text-white',
    },
    warning: {
        active: 'bg-mistral-warning/10 border-mistral-warning/50 text-mistral-warning',
        icon: 'text-mistral-warning',
        countActive: 'bg-mistral-warning text-white',
    },
    danger: {
        active: 'bg-mistral-danger/10 border-mistral-danger/50 text-mistral-danger',
        icon: 'text-mistral-danger',
        countActive: 'bg-mistral-danger text-white',
    },
    info: {
        active: 'bg-mistral-info/10 border-mistral-info/50 text-mistral-info',
        icon: 'text-mistral-info',
        countActive: 'bg-mistral-info text-white',
    },
    neutral: {
        active: 'bg-mistral-surface border-mistral-stone/50 text-mistral-ink',
        icon: 'text-mistral-stone',
        countActive: 'bg-mistral-ink text-white',
    },
};

function toneOf(tone) {
    return chipTones[tone] || chipTones.neutral;
}

// Every report status as a one-tap filter chip. The last three are the
// cross-cutting fingerprint violations (they overlap any status), hence
// the visual divider before them.
const chips = computed(() => [
    { key: '', label: t('attendance.daily_report.status_all'), icon: 'fas fa-layer-group', tone: 'primary', count: stats.value.total || 0 },
    { key: 'present', label: t('attendance.daily_report.present'), icon: 'fas fa-user-check', tone: 'success', count: stats.value.present || 0 },
    { key: 'late', label: t('attendance.daily_report.late'), icon: 'fas fa-clock', tone: 'warning', count: stats.value.late || 0 },
    { key: 'absent', label: t('attendance.daily_report.absent'), icon: 'fas fa-user-xmark', tone: 'danger', count: stats.value.absent || 0 },
    { key: 'awaiting', label: t('attendance.daily_report.awaiting'), icon: 'fas fa-hourglass-half', tone: 'warning', count: stats.value.awaiting || 0 },
    { key: 'leave', label: t('attendance.daily_report.leave'), icon: 'fas fa-umbrella-beach', tone: 'info', count: stats.value.leave || 0 },
    { key: 'mission', label: t('attendance.daily_report.mission'), icon: 'fas fa-briefcase', tone: 'info', count: stats.value.mission || 0 },
    { key: 'rest', label: t('attendance.daily_report.rest'), icon: 'fas fa-bed', tone: 'neutral', count: stats.value.rest || 0 },
    { key: 'holiday', label: t('attendance.daily_report.holiday'), icon: 'fas fa-calendar-check', tone: 'info', count: stats.value.holiday || 0 },
    { key: 'unassigned', label: t('attendance.daily_report.unassigned'), icon: 'fas fa-user-slash', tone: 'neutral', count: stats.value.unassigned || 0 },
    { key: 'incomplete', label: t('attendance.daily_report.incomplete'), icon: 'fas fa-fingerprint', tone: 'danger', count: stats.value.incomplete || 0, cross: true },
    { key: 'evening', label: t('attendance.daily_report.evening'), icon: 'fas fa-moon', tone: 'warning', count: stats.value.evening || 0, cross: true },
    { key: 'no_fingerprint', label: t('attendance.daily_report.no_fingerprint'), icon: 'fas fa-ban', tone: 'danger', count: stats.value.no_fingerprint || 0, cross: true },
]);

const activeStatusLabel = computed(
    () => chips.value.find((chip) => chip.key === status.value)?.label || t('attendance.daily_report.status_all'),
);

// ------------------------------------------------------------------
// Navigation
// ------------------------------------------------------------------

function filterParams() {
    return {
        date: date.value,
        cutoff_time: cutoffTime.value,
        branch_id: branchId.value || undefined,
        department_ids: departmentIds.value.length ? departmentIds.value : undefined,
        user_id: userId.value || undefined,
        status: status.value || undefined,
    };
}

function applyFilters() {
    router.get(route('attendance.daily-summaries.daily-report'), filterParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['report', 'filters'],
        onStart: () => { isLoading.value = true; },
        onFinish: () => { isLoading.value = false; },
    });
}

function selectStatus(key) {
    if (status.value === key || isLoading.value) return;
    status.value = key;
    applyFilters();
}

function shiftDate(days) {
    if (isLoading.value) return;
    const d = new Date(`${date.value}T00:00:00`);
    if (Number.isNaN(d.getTime())) return;
    d.setDate(d.getDate() + days);
    date.value = localDateStr(d);
    applyFilters();
}

function goToday() {
    if (isToday.value || isLoading.value) return;
    date.value = todayStr;
    applyFilters();
}

// A full remount (preserveState: false) clears every local ref AND the
// DataTable toolbar's internal search/column state in one shot.
function resetFilters() {
    router.get(
        route('attendance.daily-summaries.daily-report'),
        { date: todayStr, cutoff_time: '09:00' },
        {
            preserveState: false,
            replace: true,
            only: ['report', 'filters'],
            onStart: () => { isLoading.value = true; },
            onFinish: () => { isLoading.value = false; },
        },
    );
}

function exportReport() {
    window.location.href = route('attendance.daily-summaries.daily-report.export', filterParams());
}

// ------------------------------------------------------------------
// Table: columns, client-side search, cell helpers
// ------------------------------------------------------------------

const columns = computed(() => [
    { key: 'name', label: t('attendance.daily_report.col_name'), sortable: true },
    { key: 'department_name', label: t('attendance.daily_report.col_department'), sortable: true },
    { key: 'status_label', label: t('attendance.daily_report.col_status'), cellClass: 'text-center' },
    { key: 'check_in', label: t('attendance.daily_report.col_check_in'), cellClass: 'text-center' },
    { key: 'check_out', label: t('attendance.daily_report.col_check_out'), cellClass: 'text-center' },
    { key: 'evening_punch', label: t('attendance.daily_report.col_evening_punch'), cellClass: 'text-center' },
    { key: 'late_minutes', label: t('attendance.daily_report.col_late_minutes'), cellClass: 'text-center', sortable: true },
    { key: 'notes', label: t('attendance.daily_report.col_notes') },
]);

// The toolbar search filters the already-loaded rows client-side: the
// report always ships the full day roster, so re-querying per keystroke
// would be wasteful.
const filteredRows = computed(() => {
    const rows = props.report.rows || [];
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter((row) =>
        [row.name, row.employee_code, row.department_name, row.status_label]
            .some((value) => String(value || '').toLowerCase().includes(q)),
    );
});

const data = computed(() => ({ data: filteredRows.value, links: [] }));

function onTableSearch(value) {
    searchQuery.value = value;
}

function badgeVariant(s) {
    return ({
        present: 'active',
        late: 'pending',
        absent: 'absent',
        awaiting: 'pending',
        leave: 'info',
        mission: 'info',
        incomplete: 'absent',
        no_fingerprint: 'danger',
        unassigned: 'inactive',
        rest: 'inactive',
        holiday: 'vacation',
        evening: 'pending',
    }[s] || 'inactive');
}

// The backend joins a row's notes with "،" — split them back into
// scannable lines and tint the ones a reviewer must act on.
function noteList(row) {
    return String(row.notes || '')
        .split('،')
        .map((note) => note.trim())
        .filter(Boolean);
}

function noteTone(note) {
    if (note.includes('لم يسجل') || note.includes('غير مسجل') || note.includes('بلا إسناد')) return 'danger';
    if (note.includes('دون جلسة') || note.includes('خروج مبكر') || note.includes('تنبيه')) return 'warning';
    return 'muted';
}

// ------------------------------------------------------------------
// Context line (formatted report date) + dismissible explainer note
// ------------------------------------------------------------------

const formattedDate = computed(() => {
    const raw = props.report.date || date.value;
    try {
        return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SY' : 'en-GB', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(new Date(`${raw}T00:00:00`));
    } catch {
        return raw;
    }
});

const NOTE_DISMISS_KEY = 'hrm-daily-report-note-dismissed';
const showNote = ref(true);
try {
    showNote.value = window.localStorage.getItem(NOTE_DISMISS_KEY) !== '1';
} catch { /* localStorage unavailable */ }

function dismissNote() {
    showNote.value = false;
    try {
        window.localStorage.setItem(NOTE_DISMISS_KEY, '1');
    } catch { /* localStorage unavailable */ }
}

usePageTitle(t('attendance.daily_report.title'));
</script>

<template>
    <PageHeader :title="t('attendance.daily_report.title')" :description="t('attendance.daily_report.description')">
        <template #actions>
            <Button variant="secondary" icon="fas fa-rotate" :loading="isLoading" @click="applyFilters">
                {{ t('common.refresh') }}
            </Button>
            <Button variant="primary" icon="fas fa-file-word" @click="exportReport">
                {{ t('attendance.daily_report.export_word') }}
            </Button>
        </template>
    </PageHeader>

    <!-- ============ Filters ============ -->
    <Card variant="cream-soft" class="mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <FormInput
                v-model="date"
                type="date"
                name="date"
                :label="t('attendance.daily_report.report_date')"
            />
            <FormInput
                v-model="cutoffTime"
                type="time"
                name="cutoff_time"
                :label="t('attendance.daily_report.cutoff_time')"
            />
            <FormSelect
                v-model="branchId"
                name="branch_id"
                :label="t('attendance.daily_report.branch')"
                :options="branchOptions"
            />
            <FormMultiSelect
                v-model="departmentIds"
                name="department_ids"
                :label="t('attendance.daily_report.departments')"
                :placeholder="t('attendance.daily_report.all_departments')"
                :options="departments"
                :max-visible-tags="2"
            />
            <FormSearchableSelect
                v-model="userId"
                name="user_id"
                :label="t('attendance.daily_report.employee')"
                :placeholder="t('attendance.daily_report.all_employees')"
                :search-placeholder="t('attendance.daily_report.search_employee')"
                :options="userOptions"
            />
        </div>

        <div class="mt-5 pt-4 border-t border-mistral-beige-deep/50 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Button variant="primary" icon="fas fa-search" :loading="isLoading" @click="applyFilters">
                    {{ t('attendance.daily_report.view_report') }}
                </Button>
                <Button variant="ghost" icon="fas fa-rotate-left" :disabled="isLoading" @click="resetFilters">
                    {{ t('attendance.daily_report.reset_filters') }}
                </Button>
            </div>

            <!-- Quick day navigation: a daily report is browsed day by day -->
            <div
                class="flex items-stretch rounded-md border border-mistral-hairline-strong bg-mistral-canvas overflow-hidden shadow-sm"
                role="group"
            >
                <button
                    type="button"
                    :disabled="isLoading"
                    class="h-9 px-3 flex items-center gap-1.5 text-[12px] font-medium text-mistral-steel border-e border-mistral-hairline-soft transition-colors hover:bg-mistral-surface hover:text-mistral-ink disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-2 focus-visible:outline-mistral-primary"
                    @click="shiftDate(-1)"
                >
                    <i class="fas fa-chevron-left rtl-flip text-[9px]" aria-hidden="true"></i>
                    <span>{{ t('attendance.daily_report.quick_prev_day') }}</span>
                </button>
                <button
                    type="button"
                    :disabled="isLoading || isToday"
                    :class="isToday
                        ? 'text-mistral-primary bg-mistral-primary/5 font-bold'
                        : 'text-mistral-steel font-medium hover:bg-mistral-surface hover:text-mistral-ink'"
                    class="h-9 px-4 flex items-center text-[12px] transition-colors disabled:cursor-not-allowed focus-visible:outline-2 focus-visible:outline-mistral-primary"
                    @click="goToday"
                >
                    {{ t('attendance.daily_report.quick_today') }}
                </button>
                <button
                    type="button"
                    :disabled="isLoading"
                    class="h-9 px-3 flex items-center gap-1.5 text-[12px] font-medium text-mistral-steel border-s border-mistral-hairline-soft transition-colors hover:bg-mistral-surface hover:text-mistral-ink disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-2 focus-visible:outline-mistral-primary"
                    @click="shiftDate(1)"
                >
                    <span>{{ t('attendance.daily_report.quick_next_day') }}</span>
                    <i class="fas fa-chevron-right rtl-flip text-[9px]" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </Card>

    <!-- ============ Headline KPIs (tap to drill down) ============ -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <button
            v-for="card in kpiCards"
            :key="card.statusKey || 'all'"
            type="button"
            :aria-pressed="status === card.statusKey"
            class="rounded-lg text-start focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mistral-primary"
            @click="selectStatus(card.statusKey)"
        >
            <StatCard
                :label="card.label"
                :value="card.value"
                :color="card.color"
                :icon="card.icon"
                :class="status === card.statusKey ? 'ring-2 ring-mistral-primary/40 shadow-level-1' : ''"
            />
        </button>
    </div>

    <!-- ============ Status chips ============ -->
    <div class="mb-5">
        <div class="flex items-center gap-2 mb-2.5">
            <i class="fas fa-filter text-mistral-primary text-[11px]" aria-hidden="true"></i>
            <h2 class="text-[13px] font-semibold text-mistral-ink">
                {{ t('attendance.daily_report.filter_by_status') }}
            </h2>
        </div>
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
            <template v-for="chip in chips" :key="chip.key || 'all'">
                <span
                    v-if="chip.key === 'incomplete'"
                    class="w-px h-6 bg-mistral-hairline-strong shrink-0 mx-1"
                    aria-hidden="true"
                ></span>
                <button
                    type="button"
                    :disabled="isLoading"
                    :aria-pressed="status === chip.key"
                    :class="[
                        'inline-flex items-center gap-2 h-9 ps-3 pe-2 rounded-lg border text-[12px] font-medium whitespace-nowrap shrink-0 transition-all duration-150',
                        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mistral-primary',
                        'disabled:opacity-60 disabled:cursor-wait',
                        status === chip.key
                            ? toneOf(chip.tone).active
                            : 'bg-mistral-canvas border-mistral-hairline-soft text-mistral-steel hover:border-mistral-hairline-strong hover:text-mistral-ink hover:shadow-level-1',
                    ]"
                    @click="selectStatus(chip.key)"
                >
                    <i :class="[chip.icon, 'text-[11px]', toneOf(chip.tone).icon]" aria-hidden="true"></i>
                    <span>{{ chip.label }}</span>
                    <span
                        :class="[
                            'min-w-[26px] h-5 px-1.5 rounded-full text-[11px] font-bold inline-flex items-center justify-center tabular-nums',
                            status === chip.key ? toneOf(chip.tone).countActive : 'bg-mistral-surface text-mistral-steel',
                        ]"
                    >{{ chip.count.toLocaleString() }}</span>
                </button>
            </template>
        </div>
    </div>

    <!-- ============ Report context ============ -->
    <div class="flex items-center gap-3 mb-3">
        <span class="w-9 h-9 rounded-md bg-mistral-primary/10 text-mistral-primary flex items-center justify-center shrink-0">
            <i class="fas fa-calendar-day text-[14px]" aria-hidden="true"></i>
        </span>
        <div class="min-w-0">
            <div class="text-[14px] font-bold text-mistral-ink leading-tight">
                {{ formattedDate }}
            </div>
            <div class="text-[12px] text-mistral-stone">
                {{ activeStatusLabel }} · {{ t('attendance.daily_report.results_count', { count: filteredRows.length }) }}
            </div>
        </div>
    </div>

    <Alert
        v-if="showNote"
        type="info"
        :message="t('attendance.daily_report.checkout_note')"
        dismissible
        class="mb-4"
        @dismiss="dismissNote"
    />

    <!-- ============ Report table ============ -->
    <DataTable
        :columns="columns"
        :data="data"
        :loading="isLoading"
        storage-key="attendance-daily-report"
        :enable-search="true"
        :enable-filters="false"
        :enable-export="false"
        :enable-pagination="false"
        :selectable="false"
        :empty-title="t('attendance.daily_report.empty_title')"
        :empty-description="t('attendance.daily_report.empty_description')"
        @search="onTableSearch"
    >
        <template #cell-name="{ row }">
            <div class="flex items-center gap-2.5 min-w-[180px]">
                <Avatar :name="row.name" size="sm" />
                <div class="min-w-0">
                    <div class="font-semibold text-mistral-ink leading-tight">
                        {{ row.name }}
                    </div>
                    <div class="text-[11px] text-mistral-stone tabular-nums">
                        {{ row.employee_code }}
                    </div>
                </div>
            </div>
        </template>
        <template #cell-department_name="{ row }">
            <span class="text-[12px] text-mistral-steel">{{ row.department_name || '—' }}</span>
        </template>
        <template #cell-status_label="{ row }">
            <Badge :text="row.status_label" :variant="badgeVariant(row.status)" dot />
        </template>
        <template #cell-check_in="{ row }">
            <span
                v-if="row.check_in"
                dir="ltr"
                :class="[
                    'inline-flex items-center rounded-md px-2 py-0.5 text-[12px] font-semibold tabular-nums',
                    row.status === 'late' ? 'bg-mistral-warning/10 text-mistral-warning' : 'bg-mistral-surface/70 text-mistral-ink',
                ]"
            >{{ row.check_in }}</span>
            <span v-else class="text-mistral-muted text-[12px]">—</span>
        </template>
        <template #cell-check_out="{ row }">
            <span
                v-if="row.check_out"
                dir="ltr"
                class="inline-flex items-center rounded-md px-2 py-0.5 text-[12px] font-semibold tabular-nums bg-mistral-surface/70 text-mistral-ink"
            >
                {{ row.check_out }}<span v-if="row.check_out_next_day" class="text-mistral-primary ms-0.5">+1</span>
            </span>
            <span v-else class="text-mistral-muted text-[12px]">—</span>
        </template>
        <template #cell-evening_punch="{ row }">
            <span
                v-if="row.evening_punch"
                dir="ltr"
                class="inline-flex items-center rounded-md px-2 py-0.5 text-[12px] font-semibold tabular-nums bg-mistral-surface/70 text-mistral-ink"
            >{{ row.evening_punch }}</span>
            <span v-else class="text-mistral-muted text-[12px]">—</span>
        </template>
        <template #cell-late_minutes="{ row }">
            <Badge v-if="row.late_minutes > 0" :text="row.late_minutes" variant="pending" />
            <span v-else class="text-mistral-muted text-[12px]">—</span>
        </template>
        <template #cell-notes="{ row }">
            <div v-if="noteList(row).length" class="flex flex-col gap-1 min-w-[220px] py-0.5">
                <div
                    v-for="(note, idx) in noteList(row)"
                    :key="idx"
                    class="flex items-start gap-1.5 text-[11px] leading-relaxed"
                >
                    <span
                        :class="[
                            'w-1.5 h-1.5 rounded-full shrink-0 mt-[5px]',
                            noteTone(note) === 'danger' ? 'bg-mistral-danger' : noteTone(note) === 'warning' ? 'bg-mistral-warning' : 'bg-mistral-muted',
                        ]"
                        aria-hidden="true"
                    ></span>
                    <span :class="noteTone(note) === 'danger' ? 'text-mistral-danger font-medium' : 'text-mistral-steel'">
                        {{ note }}
                    </span>
                </div>
            </div>
            <span v-else class="text-mistral-muted text-[12px]">—</span>
        </template>
    </DataTable>
</template>
