<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Badge, Avatar, EmptyState, Button, FormDatepicker } from '@/Components/ui';
import DashboardWidget from '@/Components/dashboard/DashboardWidget.vue';
import DashboardChart from '@/Components/dashboard/DashboardChart.vue';
import LiveCounter from '@/Components/dashboard/LiveCounter.vue';
import AttendanceHeatmap from '@/Components/dashboard/AttendanceHeatmap.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t, isRtl } = useTranslations();

const props = defineProps({
    title: { type: String, default: '' },
    dashboard: {
        type: Object,
        default: () => ({
            today: '',
            liveCounters: {},
            dailyKpis: {},
            weeklyTrend: [],
            monthlyTrend: [],
            departmentStats: [],
            monthlyKpis: {},
            topLate: [],
            shiftOverview: { shifts: [], upcoming: [] },
            recentApprovals: [],
            recentSyncs: [],
            anomalies: [],
            health: {},
            heatmapData: [],
            massLateness: {},
            massAbsence: {},
            activeDevices: 0,
            totalDevices: 0,
        }),
    },
    recentAttendance: {
        type: Array,
        default: () => [],
    },
});

const data = ref({ ...props.dashboard });
const recentData = ref([...(props.recentAttendance || [])]);
const selectedDate = ref(props.dashboard.today);
const isRefreshing = ref(false);
const showForceHoliday = ref(false);
const activeTab = ref('overview');

// Polling
const POLL_INTERVAL_MS = 15000;
let pollTimer = null;
const inflightControllers = new Set();
let isPolling = false;
let isTabVisible = !document.hidden;
let isComponentMounted = true;

// Stat cards configuration
const statCards = computed(() => [
    { label: t('dashboard.total_employees'), key: 'employees', icon: 'fas fa-users', color: 'primary' },
    { label: t('dashboard.present_today'), key: 'present', icon: 'fas fa-user-check', color: 'success' },
    { label: t('dashboard.absent_today'), key: 'absent', icon: 'fas fa-user-xmark', color: 'danger' },
    { label: t('dashboard.currently_inside'), key: 'inside', icon: 'fas fa-door-open', color: 'info' },
    { label: t('dashboard.currently_outside'), key: 'outside', icon: 'fas fa-door-closed', color: 'warning' },
    { label: t('dashboard.late_today'), key: 'late', icon: 'fas fa-clock', color: 'purple' },
    { label: t('dashboard.on_leave'), key: 'on_leave', icon: 'fas fa-umbrella-beach', color: 'vacation' },
    { label: t('dashboard.pending_requests'), key: 'pending_requests', icon: 'fas fa-hourglass-half', color: 'warning' },
    { label: t('dashboard.missing_fingerprints'), key: 'missing_fingerprints', icon: 'fas fa-fingerprint', color: 'danger' },
    { label: t('dashboard.active_devices'), key: 'active_devices', icon: 'fas fa-microchip', color: 'success' },
]);

// Chart data: Attendance Doughnut
const attendanceDoughnutData = computed(() => ({
    labels: [t('dashboard.present'), t('dashboard.absent'), t('dashboard.late'), t('dashboard.early_leave')],
    datasets: [{
        data: [
            data.value.dailyKpis?.present - (data.value.dailyKpis?.late || 0) - (data.value.dailyKpis?.early_leave || 0) || 0,
            data.value.dailyKpis?.absent || 0,
            data.value.dailyKpis?.late || 0,
            data.value.dailyKpis?.early_leave || 0,
        ],
        backgroundColor: ['#16a34a', '#dc2626', '#d97706', '#2563eb'],
        borderWidth: 0,
        hoverOffset: 6,
    }],
}));

const doughnutOptions = {
    cutout: '68%',
    plugins: {
        legend: { position: 'bottom' },
    },
};

// Chart data: Weekly Trend
const weeklyTrendData = computed(() => ({
    labels: (data.value.weeklyTrend || []).map((d) => {
        const date = new Date(d.date);
        return date.toLocaleDateString('ar-SA', { weekday: 'short' });
    }),
    datasets: [
        {
            label: t('dashboard.present'),
            data: (data.value.weeklyTrend || []).map((d) => d.present),
            backgroundColor: '#16a34a',
            borderRadius: 6,
            barPercentage: 0.6,
        },
        {
            label: t('dashboard.absent'),
            data: (data.value.weeklyTrend || []).map((d) => d.absent),
            backgroundColor: '#dc2626',
            borderRadius: 6,
            barPercentage: 0.6,
        },
        {
            label: t('dashboard.late'),
            data: (data.value.weeklyTrend || []).map((d) => d.late),
            backgroundColor: '#d97706',
            borderRadius: 6,
            barPercentage: 0.6,
        },
    ],
}));

// Chart data: Monthly Trend Line
const monthlyTrendData = computed(() => ({
    labels: (data.value.monthlyTrend || []).map((d) => {
        const date = new Date(d.date);
        return date.getDate() + '/' + (date.getMonth() + 1);
    }),
    datasets: [
        {
            label: t('dashboard.present'),
            data: (data.value.monthlyTrend || []).map((d) => d.present),
            borderColor: '#16a34a',
            backgroundColor: 'rgba(22,163,74,0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 5,
        },
        {
            label: t('dashboard.absent'),
            data: (data.value.monthlyTrend || []).map((d) => d.absent),
            borderColor: '#dc2626',
            backgroundColor: 'rgba(220,38,38,0.05)',
            fill: true,
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 5,
        },
    ],
}));

const lineChartOptions = {
    plugins: {
        legend: { position: 'bottom' },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { maxTicksLimit: 10, font: { size: 9 } },
        },
        y: {
            grid: { color: '#ededed' },
            beginAtZero: true,
        },
    },
};

// Quick actions
const quickActions = computed(() => [
    { label: t('dashboard.action_view_attendance'), icon: 'fas fa-calendar-check', route: 'attendance.sessions.index', color: 'bg-mistral-primary/10 text-mistral-primary' },
    { label: t('dashboard.action_view_reports'), icon: 'fas fa-chart-line', route: 'attendance.reports.index', color: 'bg-mistral-info/10 text-mistral-info' },
    { label: t('dashboard.action_manage_devices'), icon: 'fas fa-microchip', route: 'fingerprint-devices.index', color: 'bg-mistral-success/10 text-mistral-success' },
    { label: t('dashboard.action_vacation_requests'), icon: 'fas fa-inbox', route: 'vacations.requests.index', color: 'bg-mistral-warning/10 text-mistral-warning' },
    { label: t('dashboard.action_view_employees'), icon: 'fas fa-users', route: 'users.index', color: 'bg-purple-50 text-purple-600' },
    { label: t('dashboard.action_live_monitoring'), icon: 'fas fa-satellite-dish', route: 'attendance.live.index', color: 'bg-cyan-50 text-cyan-600' },
]);

function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return parts[0].substring(0, 2).toUpperCase();
}

function getStatusColor(status) {
    const map = {
        approved: 'active',
        rejected: 'danger',
        pending: 'pending',
    };
    return map[status] || 'inactive';
}

function getStatusLabel(status) {
    const map = {
        approved: t('dashboard.approved'),
        rejected: t('dashboard.rejected'),
        pending: t('dashboard.pending_requests'),
    };
    return map[status] || status;
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now - date;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'الآن';
    if (diffMin < 60) return diffMin + ' دقيقة';
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24) return diffH + ' ساعة';
    const diffD = Math.floor(diffH / 24);
    return diffD + ' يوم';
}

async function fetchJson(url) {
    const controller = new AbortController();
    inflightControllers.add(controller);
    try {
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        if (!res.ok) return null;
        return await res.json();
    } catch (e) {
        if (e.name !== 'AbortError') { /* ignore */ }
        return null;
    } finally {
        inflightControllers.delete(controller);
    }
}

async function poll() {
    if (isPolling || !isTabVisible || !isComponentMounted) {
        scheduleNext();
        return;
    }
    isPolling = true;
    try {
        const [pullJson, snapshotJson] = await Promise.all([
            fetchJson(route('dashboard.pullEvents')),
            fetchJson(route('dashboard.snapshot')),
        ]);
        if (pullJson?.events) recentData.value = pullJson.events;
        if (snapshotJson?.dashboard) data.value = snapshotJson.dashboard;
    } finally {
        isPolling = false;
        scheduleNext();
    }
}

function scheduleNext() {
    if (!isComponentMounted) return;
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(poll, POLL_INTERVAL_MS);
}

function handleVisibilityChange() {
    isTabVisible = !document.hidden;
    if (isTabVisible && !pollTimer) poll();
}

function refreshDashboard() {
    isRefreshing.value = true;
    router.reload({
        only: ['dashboard', 'recentAttendance'],
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
}

function exportDashboard() {
    window.print();
}

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    poll();
});

onBeforeUnmount(() => {
    isComponentMounted = false;
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    if (pollTimer) clearTimeout(pollTimer);
    inflightControllers.forEach((c) => c.abort());
    inflightControllers.clear();
});
</script>

<template>
    <AppLayout :title="t('dashboard.title')">
        <div class="space-y-5">
            <!-- ===== TOP BAR ===== -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-[22px] font-bold text-mistral-ink tracking-tight">
                        {{ t('dashboard.title') }}
                    </h1>
                    <p class="text-[13px] text-mistral-steel mt-1">
                        {{ t('dashboard.last_updated') }}: {{ new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' }) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Date picker -->
                    <FormDatepicker
                        v-model="selectedDate"
                        class="w-auto"
                    />
                    <!-- Refresh -->
                    <Button
                        variant="secondary"
                        size="sm"
                        icon="fas fa-sync-alt"
                        :class="{ 'animate-spin': isRefreshing }"
                        @click="refreshDashboard"
                    >
                        <span class="hidden sm:inline">{{ t('dashboard.refresh') }}</span>
                    </Button>
                    <!-- Export -->
                    <Button
                        variant="secondary"
                        size="sm"
                        icon="fas fa-download"
                        @click="exportDashboard"
                    >
                        <span class="hidden sm:inline">{{ t('dashboard.export') }}</span>
                    </Button>
                </div>
            </div>

            <!-- ===== MASS ALERTS ===== -->
            <div
                v-if="data.massLateness?.is_alert || data.massAbsence?.is_alert"
                class="flex flex-col sm:flex-row gap-3"
            >
                <div
                    v-if="data.massLateness?.is_alert"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl bg-mistral-danger/5 border border-mistral-danger/20 flex-1"
                >
                    <div class="w-8 h-8 rounded-lg bg-mistral-danger/10 flex items-center justify-center shrink-0">
                        <i class="fas fa-exclamation-triangle text-mistral-danger text-[14px]" aria-hidden="true"></i>
                    </div>
                    <div>
                        <span class="text-[13px] font-semibold text-mistral-danger">{{ t('dashboard.mass_lateness_alert') }}</span>
                        <span class="text-[12px] text-mistral-steel ms-2">
                            {{ data.massLateness.late_count }} / {{ data.massLateness.total }}
                            ({{ Math.round(data.massLateness.ratio * 100) }}% {{ t('dashboard.of_employees') }})
                        </span>
                    </div>
                </div>
                <div
                    v-if="data.massAbsence?.is_alert"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl bg-mistral-warning/5 border border-mistral-warning/20 flex-1"
                >
                    <div class="w-8 h-8 rounded-lg bg-mistral-warning/10 flex items-center justify-center shrink-0">
                        <i class="fas fa-users-slash text-mistral-warning text-[14px]" aria-hidden="true"></i>
                    </div>
                    <div>
                        <span class="text-[13px] font-semibold text-mistral-warning">{{ t('dashboard.mass_absence_alert') }}</span>
                        <span class="text-[12px] text-mistral-steel ms-2">
                            {{ data.massAbsence.absent_count }} / {{ data.massAbsence.total }}
                            ({{ Math.round(data.massAbsence.ratio * 100) }}% {{ t('dashboard.of_employees') }})
                        </span>
                    </div>
                </div>
            </div>

            <!-- ===== LIVE COUNTERS ===== -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-10 gap-3">
                <div
                    v-for="card in statCards"
                    :key="card.key"
                    class="bg-white border border-mistral-hairline-soft rounded-xl p-4 hover:shadow-level-1 transition-all duration-200 group cursor-default"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div
                            :class="[
                                'w-7 h-7 rounded-lg flex items-center justify-center transition-transform group-hover:scale-110',
                                {
                                    'bg-mistral-primary/10 text-mistral-primary': card.color === 'primary',
                                    'bg-mistral-success/10 text-mistral-success': card.color === 'success',
                                    'bg-mistral-danger/10 text-mistral-danger': card.color === 'danger',
                                    'bg-mistral-warning/10 text-mistral-warning': card.color === 'warning',
                                    'bg-mistral-info/10 text-mistral-info': card.color === 'info' || card.color === 'vacation' || card.color === 'purple',
                                },
                            ]"
                        >
                            <i :class="[card.icon, 'text-[12px]']" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="text-[22px] font-bold text-mistral-ink leading-none">
                        <LiveCounter :value="data.liveCounters?.[card.key] ?? 0" />
                    </div>
                    <div class="text-[11px] text-mistral-steel mt-1.5 leading-tight">{{ card.label }}</div>
                </div>
            </div>

            <!-- ===== CHARTS ROW ===== -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Attendance Doughnut -->
                <DashboardWidget
                    :title="t('dashboard.attendance_overview')"
                    icon="fas fa-chart-pie"
                    icon-color="primary"
                >
                    <DashboardChart
                        type="doughnut"
                        :data="attendanceDoughnutData"
                        :options="doughnutOptions"
                        :height="240"
                    />
                </DashboardWidget>

                <!-- Weekly Trend -->
                <DashboardWidget
                    :title="t('dashboard.weekly_trend')"
                    icon="fas fa-chart-bar"
                    icon-color="info"
                    class="lg:col-span-2"
                >
                    <DashboardChart
                        type="bar"
                        :data="weeklyTrendData"
                        :height="240"
                    />
                </DashboardWidget>
            </div>

            <!-- ===== MONTHLY TREND ===== -->
            <DashboardWidget
                :title="t('dashboard.monthly_trend')"
                icon="fas fa-chart-line"
                icon-color="success"
            >
                <DashboardChart
                    type="line"
                    :data="monthlyTrendData"
                    :options="lineChartOptions"
                    :height="280"
                />
            </DashboardWidget>

            <!-- ===== DEPARTMENT STATS + TOP LATE ===== -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Department Statistics -->
                <DashboardWidget
                    :title="t('dashboard.department_statistics')"
                    icon="fas fa-sitemap"
                    icon-color="purple"
                    class="lg:col-span-2"
                    :padded="false"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="border-b border-mistral-hairline-soft">
                                    <th class="text-start px-5 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.department_statistics') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.total_employees') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.present') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.absent') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.late') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.overtime') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-mistral-hairline-soft/60">
                                <tr
                                    v-for="dept in data.departmentStats"
                                    :key="dept.department_id"
                                    class="hover:bg-mistral-surface/40 transition-colors"
                                >
                                    <td class="px-5 py-3 text-mistral-ink font-medium">
                                        {{ dept.department_name || '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center text-mistral-slate">
                                        {{ dept.employees }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="text-mistral-success font-semibold">{{ dept.present_days }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="text-mistral-danger font-semibold">{{ dept.absent_days }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="text-mistral-warning font-semibold">{{ dept.late_days }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center text-mistral-slate font-mono" dir="ltr">
                                        {{ Math.floor(dept.overtime_minutes / 60) }}h {{ dept.overtime_minutes % 60 }}m
                                    </td>
                                </tr>
                                <tr v-if="!data.departmentStats?.length">
                                    <td colspan="6" class="px-5 py-8 text-center text-mistral-stone text-[13px]">
                                        {{ t('common.no_data') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </DashboardWidget>

                <!-- Top Late -->
                <DashboardWidget
                    :title="t('dashboard.top_late_employees')"
                    icon="fas fa-user-clock"
                    icon-color="danger"
                    :padded="false"
                >
                    <div class="divide-y divide-mistral-hairline-soft/60">
                        <div
                            v-for="(emp, i) in data.topLate"
                            :key="emp.user_id"
                            class="flex items-center gap-3 px-5 py-3 hover:bg-mistral-surface/40 transition-colors"
                        >
                            <span class="text-[11px] text-mistral-muted font-mono w-5 text-center" dir="ltr">{{ i + 1 }}</span>
                            <Avatar :name="emp.name" size="sm" />
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-medium text-mistral-ink truncate">{{ emp.name }}</div>
                                <div class="text-[11px] text-mistral-stone">
                                    {{ emp.late_minutes }} {{ t('dashboard.minutes_late').replace(':minutes', '') }}
                                </div>
                            </div>
                            <Badge
                                :text="emp.absent_days + ' ' + t('dashboard.absent')"
                                variant="danger"
                                size="sm"
                            />
                        </div>
                        <EmptyState
                            v-if="!data.topLate?.length"
                            icon="fas fa-check-circle"
                            :title="t('dashboard.no_anomalies')"
                            class="py-8"
                        />
                    </div>
                </DashboardWidget>
            </div>

            <!-- ===== HEATMAP ===== -->
            <DashboardWidget
                :title="t('dashboard.attendance_heatmap')"
                icon="fas fa-fire"
                icon-color="warning"
            >
                <AttendanceHeatmap :data="data.heatmapData || []" />
            </DashboardWidget>

            <!-- ===== SHIFTS + UPCOMING ===== -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <!-- Shift Overview -->
                <DashboardWidget
                    :title="t('dashboard.shift_overview')"
                    icon="fas fa-clock"
                    icon-color="info"
                    :padded="false"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="border-b border-mistral-hairline-soft">
                                    <th class="text-start px-5 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.shift_name') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider" dir="ltr">
                                        {{ t('dashboard.start_time') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider" dir="ltr">
                                        {{ t('dashboard.end_time') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.employees_assigned') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-mistral-hairline-soft/60">
                                <tr
                                    v-for="shift in data.shiftOverview?.shifts"
                                    :key="shift.id"
                                    class="hover:bg-mistral-surface/40 transition-colors"
                                >
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-mistral-ink font-medium">{{ shift.name }}</span>
                                            <Badge :text="shift.code" variant="cream" size="sm" />
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center text-mistral-slate font-mono" dir="ltr">
                                        {{ shift.start_time }}
                                    </td>
                                    <td class="px-3 py-3 text-center text-mistral-slate font-mono" dir="ltr">
                                        {{ shift.end_time }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-mistral-info/10 text-mistral-info text-[12px] font-bold">
                                            {{ shift.employee_count }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!data.shiftOverview?.shifts?.length">
                                    <td colspan="4" class="px-5 py-8 text-center text-mistral-stone text-[13px]">
                                        {{ t('dashboard.no_shifts') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </DashboardWidget>

                <!-- Upcoming Shifts -->
                <DashboardWidget
                    :title="t('dashboard.upcoming_shifts')"
                    icon="fas fa-calendar-alt"
                    icon-color="primary"
                >
                    <div class="space-y-3">
                        <div
                            v-for="day in data.shiftOverview?.upcoming"
                            :key="day.date"
                            class="flex items-center justify-between px-4 py-3 rounded-xl border border-mistral-hairline-soft hover:border-mistral-primary/30 hover:bg-mistral-primary/[0.02] transition-all"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-mistral-primary/10 flex items-center justify-center">
                                    <span class="text-[14px] font-bold text-mistral-primary">
                                        {{ new Date(day.date).getDate() }}
                                    </span>
                                </div>
                                <div>
                                    <div class="text-[13px] font-semibold text-mistral-ink">{{ day.day_name }}</div>
                                    <div class="text-[11px] text-mistral-stone">{{ day.date }}</div>
                                </div>
                            </div>
                            <Badge
                                v-if="day.is_weekend"
                                :text="t('common.leave')"
                                variant="vacation"
                                size="sm"
                            />
                            <Badge
                                v-else
                                :text="t('dashboard.present')"
                                variant="active"
                                size="sm"
                            />
                        </div>
                    </div>
                </DashboardWidget>
            </div>

            <!-- ===== QUICK ACTIONS + APPROVALS + SYNC ===== -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Quick Actions -->
                <DashboardWidget
                    :title="t('dashboard.quick_actions')"
                    icon="fas fa-bolt"
                    icon-color="warning"
                >
                    <div class="grid grid-cols-2 gap-2">
                        <Link
                            v-for="action in quickActions"
                            :key="action.route"
                            :href="route(action.route)"
                            class="flex flex-col items-center gap-2 px-3 py-4 rounded-xl border border-mistral-hairline-soft hover:border-mistral-primary/30 hover:shadow-level-1 transition-all group"
                        >
                            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110', action.color]">
                                <i :class="[action.icon, 'text-[16px]']" aria-hidden="true"></i>
                            </div>
                            <span class="text-[11px] text-mistral-steel font-medium text-center leading-tight">{{ action.label }}</span>
                        </Link>
                    </div>
                </DashboardWidget>

                <!-- Recent Approvals -->
                <DashboardWidget
                    :title="t('dashboard.recent_approvals')"
                    icon="fas fa-check-double"
                    icon-color="success"
                    :padded="false"
                >
                    <div class="divide-y divide-mistral-hairline-soft/60 max-h-[340px] overflow-y-auto">
                        <div
                            v-for="approval in data.recentApprovals"
                            :key="approval.id"
                            class="flex items-center gap-3 px-5 py-3 hover:bg-mistral-surface/40 transition-colors"
                        >
                            <Avatar :name="approval.employee_name" size="sm" />
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-medium text-mistral-ink truncate">{{ approval.employee_name }}</div>
                                <div class="text-[11px] text-mistral-stone">
                                    {{ approval.type }} · {{ approval.start_date }} → {{ approval.end_date }}
                                </div>
                            </div>
                            <Badge :text="getStatusLabel(approval.status)" :variant="getStatusColor(approval.status)" size="sm" />
                        </div>
                        <EmptyState
                            v-if="!data.recentApprovals?.length"
                            icon="fas fa-inbox"
                            :title="t('dashboard.no_approvals')"
                            class="py-8"
                        />
                    </div>
                </DashboardWidget>

                <!-- Fingerprint Sync -->
                <DashboardWidget
                    :title="t('dashboard.fingerprint_sync')"
                    icon="fas fa-sync-alt"
                    icon-color="info"
                    :padded="false"
                >
                    <div class="divide-y divide-mistral-hairline-soft/60 max-h-[340px] overflow-y-auto">
                        <div
                            v-for="sync in data.recentSyncs"
                            :key="sync.id"
                            class="flex items-center gap-3 px-5 py-3 hover:bg-mistral-surface/40 transition-colors"
                        >
                            <div
                                :class="[
                                    'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                                    sync.status === 'online' ? 'bg-mistral-success/10 text-mistral-success' : 'bg-mistral-surface text-mistral-stone',
                                ]"
                            >
                                <i class="fas fa-microchip text-[12px]" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-medium text-mistral-ink truncate">{{ sync.name }}</div>
                                <div class="text-[11px] text-mistral-stone">
                                    {{ sync.attendance_log_count }} {{ t('dashboard.unprocessed_logs') }}
                                </div>
                            </div>
                            <div class="text-[11px] text-mistral-muted" dir="ltr">
                                {{ timeAgo(sync.last_synced_at) }}
                            </div>
                        </div>
                        <EmptyState
                            v-if="!data.recentSyncs?.length"
                            icon="fas fa-sync"
                            :title="t('common.no_data')"
                            class="py-8"
                        />
                    </div>
                </DashboardWidget>
            </div>

            <!-- ===== ANOMALIES + SYSTEM HEALTH ===== -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Anomalies -->
                <DashboardWidget
                    :title="t('dashboard.attendance_anomalies')"
                    icon="fas fa-exclamation-circle"
                    icon-color="danger"
                    class="lg:col-span-2"
                    :padded="false"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="border-b border-mistral-hairline-soft">
                                    <th class="text-start px-5 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.total_employees') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.attendance_heatmap') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider">
                                        {{ t('dashboard.late_today') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider" dir="ltr">
                                        {{ t('dashboard.start_time') }}
                                    </th>
                                    <th class="text-center px-3 py-3 text-[11px] font-semibold text-mistral-steel uppercase tracking-wider" dir="ltr">
                                        {{ t('dashboard.end_time') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-mistral-hairline-soft/60">
                                <tr
                                    v-for="anomaly in data.anomalies"
                                    :key="anomaly.id"
                                    class="hover:bg-mistral-surface/40 transition-colors"
                                >
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <Avatar :name="anomaly.employee_name" size="xs" />
                                            <div>
                                                <div class="text-mistral-ink font-medium">{{ anomaly.employee_name }}</div>
                                                <div class="text-[11px] text-mistral-stone">{{ anomaly.employee_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <Badge
                                            :text="anomaly.status === 'absent' ? t('dashboard.absent') : t('dashboard.missing_punch')"
                                            :variant="anomaly.status === 'absent' ? 'danger' : 'warning'"
                                            size="sm"
                                        />
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span v-if="anomaly.late_minutes > 0" class="text-mistral-danger font-semibold">
                                            {{ anomaly.late_minutes }} {{ t('dashboard.minutes_late').replace(':minutes', '') }}
                                        </span>
                                        <span v-else class="text-mistral-muted">—</span>
                                    </td>
                                    <td class="px-3 py-3 text-center text-mistral-slate font-mono" dir="ltr">
                                        {{ anomaly.first_check_in || '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-center text-mistral-slate font-mono" dir="ltr">
                                        {{ anomaly.last_check_out || '—' }}
                                    </td>
                                </tr>
                                <tr v-if="!data.anomalies?.length">
                                    <td colspan="5" class="px-5 py-8 text-center text-mistral-stone text-[13px]">
                                        {{ t('dashboard.no_anomalies') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </DashboardWidget>

                <!-- System Health -->
                <DashboardWidget
                    :title="t('dashboard.system_health')"
                    icon="fas fa-heartbeat"
                    :icon-color="data.health?.anomalies > 0 ? 'danger' : 'success'"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between px-3 py-2.5 rounded-lg bg-mistral-surface/60">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-satellite-dish text-mistral-success text-[12px]" aria-hidden="true"></i>
                                <span class="text-[13px] text-mistral-ink">{{ t('dashboard.live_sessions') }}</span>
                            </div>
                            <span class="text-[14px] font-bold text-mistral-ink">
                                <LiveCounter :value="data.health?.live_sessions ?? 0" />
                            </span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2.5 rounded-lg bg-mistral-surface/60">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock text-mistral-danger text-[12px]" aria-hidden="true"></i>
                                <span class="text-[13px] text-mistral-ink">{{ t('dashboard.missing_checkouts') }}</span>
                            </div>
                            <span class="text-[14px] font-bold" :class="(data.health?.missing_checkouts ?? 0) > 0 ? 'text-mistral-danger' : 'text-mistral-ink'">
                                <LiveCounter :value="data.health?.missing_checkouts ?? 0" />
                            </span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2.5 rounded-lg bg-mistral-surface/60">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-file-alt text-mistral-warning text-[12px]" aria-hidden="true"></i>
                                <span class="text-[13px] text-mistral-ink">{{ t('dashboard.unprocessed_logs') }}</span>
                            </div>
                            <span class="text-[14px] font-bold" :class="(data.health?.unprocessed_raw_logs ?? 0) > 0 ? 'text-mistral-warning' : 'text-mistral-ink'">
                                <LiveCounter :value="data.health?.unprocessed_raw_logs ?? 0" />
                            </span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2.5 rounded-lg bg-mistral-surface/60">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle text-mistral-info text-[12px]" aria-hidden="true"></i>
                                <span class="text-[13px] text-mistral-ink">{{ t('dashboard.anomaly_count') }}</span>
                            </div>
                            <span class="text-[14px] font-bold" :class="(data.health?.anomalies ?? 0) > 0 ? 'text-mistral-danger' : 'text-mistral-ink'">
                                <LiveCounter :value="data.health?.anomalies ?? 0" />
                            </span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2.5 rounded-lg bg-mistral-surface/60">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-microchip text-mistral-success text-[12px]" aria-hidden="true"></i>
                                <span class="text-[13px] text-mistral-ink">{{ t('dashboard.devices_online') }}</span>
                            </div>
                            <span class="text-[14px] font-bold text-mistral-ink">
                                {{ data.activeDevices }} / {{ data.totalDevices }}
                            </span>
                        </div>
                        <!-- Health status -->
                        <div
                            class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl border mt-2"
                            :class="(data.health?.anomalies ?? 0) > 0 || (data.health?.missing_checkouts ?? 0) > 0
                                ? 'bg-mistral-warning/5 border-mistral-warning/20'
                                : 'bg-mistral-success/5 border-mistral-success/20'"
                        >
                            <i
                                :class="[
                                    (data.health?.anomalies ?? 0) > 0 || (data.health?.missing_checkouts ?? 0) > 0
                                        ? 'fas fa-exclamation-circle text-mistral-warning'
                                        : 'fas fa-check-circle text-mistral-success',
                                    'text-[14px]',
                                ]"
                                aria-hidden="true"
                            ></i>
                            <span
                                class="text-[13px] font-semibold"
                                :class="(data.health?.anomalies ?? 0) > 0 || (data.health?.missing_checkouts ?? 0) > 0
                                    ? 'text-mistral-warning'
                                    : 'text-mistral-success'"
                            >
                                {{ (data.health?.anomalies ?? 0) > 0 || (data.health?.missing_checkouts ?? 0) > 0
                                    ? t('dashboard.attention_needed')
                                    : t('dashboard.healthy')
                                }}
                            </span>
                        </div>
                    </div>
                </DashboardWidget>
            </div>

            <!-- ===== LIVE ATTENDANCE FEED ===== -->
            <DashboardWidget
                :title="t('dashboard.live_attendance_feed')"
                icon="fas fa-satellite-dish"
                icon-color="success"
                :padded="false"
            >
                <template #actions>
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-mistral-success animate-pulse"></span>
                        <span class="text-[11px] text-mistral-stone">{{ t('dashboard.live') }}</span>
                    </div>
                </template>

                <div v-if="recentData.length > 0" class="divide-y divide-mistral-hairline-soft/60">
                    <div
                        v-for="log in recentData"
                        :key="log.id"
                        class="flex items-center justify-between px-5 py-3 hover:bg-mistral-surface/50 transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <img
                                    v-if="log.avatar_url"
                                    :src="log.avatar_url"
                                    :alt="log.employee_name"
                                    class="w-9 h-9 rounded-full object-cover ring-2 ring-white shadow-sm"
                                />
                                <div
                                    v-else
                                    class="w-9 h-9 rounded-full bg-mistral-primary/10 flex items-center justify-center text-mistral-primary text-[11px] font-bold ring-2 ring-white shadow-sm"
                                >
                                    {{ getInitials(log.employee_name) }}
                                </div>
                                <span
                                    v-if="log.source === 'live'"
                                    class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-mistral-success border-2 border-white rounded-full animate-pulse"
                                ></span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[13px] font-semibold text-mistral-ink">{{ log.employee_name }}</span>
                                    <Badge v-if="log.source === 'live'" text="LIVE" variant="active" size="sm" :dot="true" />
                                </div>
                                <div class="text-[11px] text-mistral-stone">{{ log.employee_code }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span
                                class="text-[11px] font-medium px-2.5 py-1 rounded-full"
                                :class="log.status === 'check_in' ? 'bg-mistral-success/10 text-mistral-success' : 'bg-mistral-danger/10 text-mistral-danger'"
                            >
                                {{ log.type === 'دخول' ? t('dashboard.check_in') : t('dashboard.check_out') }}
                            </span>
                            <div class="text-[13px] text-mistral-slate font-mono" dir="ltr">
                                {{ log.time }}
                            </div>
                            <div class="text-[11px] text-mistral-stone max-w-[120px] truncate hidden sm:block" :title="log.device_name">
                                {{ log.device_name || '—' }}
                            </div>
                        </div>
                    </div>
                </div>
                <EmptyState
                    v-else
                    icon="fas fa-inbox"
                    :title="t('common.no_data')"
                    :description="t('dashboard.no_attendance_yet')"
                />
            </DashboardWidget>
        </div>
    </AppLayout>
</template>
