<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import IdleGapControl from '@/Components/UserActivity/IdleGapControl.vue';
import { Avatar, Badge, Button, Card, FormDatepicker, FormSelect, PageHeader, StatCard, EmptyState } from '@/Components/ui';
import DashboardChart from '@/Components/dashboard/DashboardChart.vue';
import DashboardWidget from '@/Components/dashboard/DashboardWidget.vue';
import { usePeriodFilter } from '@/composables/usePeriodFilter';
import { useTranslations } from '@/composables/useTranslations';
import { actionMeta } from './actionMeta';

const props = defineProps({
    user: { type: Object, required: true },
    detail: { type: Object, required: true },
    filters: { type: Object, default: () => ({ from: '', to: '', period: 'custom' }) },
    idle_gap_minutes: { type: Number, default: 2 },
});

const { t, isRtl, direction, translations } = useTranslations();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const { period, periodOptions, isCustom, applyPeriod } = usePeriodFilter(
    from,
    to,
    props.filters.period,
    applyFilters,
);

function applyFilters() {
    router.get(
        route('user-activity.show', props.user.id),
        { from: from.value, to: to.value, period: period.value },
        { preserveState: true, preserveScroll: true, replace: true, only: ['detail', 'filters'] },
    );
}

function formatHours(minutes) {
    const m = Math.max(0, Number(minutes) || 0);
    const h = Math.floor(m / 60);
    const rem = Math.round(m % 60);
    if (h === 0 && rem === 0) return `0 ${t('useractivity.hours_label')}`;
    if (h === 0) return `${rem} ${t('useractivity.minutes_label')}`;
    if (rem === 0) return `${h} ${t('useractivity.hours_label')}`;
    return `${h} ${t('useractivity.hours_label')} ${rem} ${t('useractivity.minutes_label')}`;
}

function timeAgo(dateStr) {
    if (!dateStr) return '—';
    const diff = Math.max(0, new Date() - new Date(String(dateStr).replace(' ', 'T')));
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return isRtl.value ? 'الآن' : 'now';
    if (mins < 60) return `${mins} ${isRtl.value ? 'دقيقة' : 'min'}`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} ${isRtl.value ? 'ساعة' : 'h'}`;
    return `${Math.floor(hours / 24)} ${isRtl.value ? 'يوم' : 'd'}`;
}

function actionLabel(action) {
    const label = translations.value?.useractivity?.actions?.[action];
    return label || action;
}

function entityLabel(entity) {
    const label = translations.value?.useractivity?.entities?.[entity || 'other'];
    if (label) return label;
    return String(entity || 'other').replace(/[._-]/g, ' ');
}

function shortDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('ar', { day: 'numeric', month: 'short' });
}


const kpiCards = computed(() => [
    { label: t('useractivity.total_actions'), value: props.detail.kpis.total_actions, icon: 'fas fa-bolt', color: 'primary' },
    { label: t('useractivity.real_actions'), value: props.detail.kpis.real_actions, icon: 'fas fa-circle-check', color: 'success' },
    { label: t('useractivity.views_count'), value: props.detail.kpis.views, icon: 'fas fa-eye', color: 'info' },
    { label: t('useractivity.active_hours'), value: formatHours(props.detail.kpis.active_minutes), icon: 'fas fa-clock', color: 'warning' },
    { label: t('useractivity.active_days'), value: props.detail.kpis.active_days, icon: 'fas fa-calendar-days', color: 'info' },
    { label: t('useractivity.last_activity'), value: props.detail.kpis.last_active_at ? timeAgo(props.detail.kpis.last_active_at) : '—', icon: 'fas fa-clock-rotate-left', color: 'warning' },
]);

// Bar chart: actions per day
const dailyActionsData = computed(() => ({
    labels: (props.detail.daily || []).map((d) => shortDate(d.date)),
    datasets: [{
        label: t('useractivity.daily_actions'),
        data: (props.detail.daily || []).map((d) => d.actions),
        backgroundColor: '#fa520f',
        borderRadius: 6,
        barPercentage: 0.6,
    }],
}));

// Line chart: active hours per day
const dailyHoursData = computed(() => ({
    labels: (props.detail.daily || []).map((d) => shortDate(d.date)),
    datasets: [{
        label: t('useractivity.daily_hours'),
        data: (props.detail.daily || []).map((d) => Math.round((d.active_minutes / 60) * 10) / 10),
        borderColor: '#16a34a',
        backgroundColor: 'rgba(22,163,74,0.1)',
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 6,
    }],
}));

// Doughnut: breakdown by action type
const actionBreakdownData = computed(() => {
    const byAction = {};
    (props.detail.breakdown || []).forEach((b) => {
        byAction[b.action] = (byAction[b.action] || 0) + b.count;
    });
    const entries = Object.entries(byAction).sort((a, b) => b[1] - a[1]).slice(0, 8);
    return {
        labels: entries.map(([action]) => actionLabel(action)),
        datasets: [{
            data: entries.map(([, count]) => count),
            backgroundColor: ['#fa520f', '#16a34a', '#2563eb', '#d97706', '#9333ea', '#0891b2', '#dc2626', '#78716c'],
            borderWidth: 0,
            hoverOffset: 6,
        }],
    };
});

const doughnutOptions = { cutout: '68%', plugins: { legend: { position: 'bottom' } } };

const maxBreakdown = Math.max(1, ...(props.detail.breakdown || []).map((b) => b.count));

function pageLabel(url) {
    if (!url) return '';
    try {
        return decodeURIComponent(url.split('?')[0]);
    } catch {
        return url;
    }
}
</script>

<template>
    <AppLayout :title="t('useractivity.title')">
        <div class="space-y-5">
            <PageHeader :title="t('useractivity.title')" :description="t('useractivity.subtitle')" :dir="direction">
                <template #actions>
                    <Button variant="secondary" size="sm" icon="fas fa-arrow-right" :class="isRtl ? 'rtl-flip' : ''" :href="route('user-activity.index')">
                        {{ t('useractivity.back') }}
                    </Button>
                </template>
            </PageHeader>

            <!-- User profile card -->
            <div class="bg-white border border-mistral-hairline-soft rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <Avatar :name="user.full_name || user.name" :src="user.avatar_url" size="xl" />
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-[18px] font-bold text-mistral-ink">{{ user.full_name || user.name }}</h2>
                        <Badge v-if="user.employee_code" :text="user.employee_code" variant="cream" size="sm" />
                    </div>
                    <div class="text-[13px] text-mistral-steel mt-1" dir="ltr">{{ user.email }}</div>
                    <div class="flex flex-wrap gap-3 mt-2 text-[12px] text-mistral-stone">
                        <span v-if="user.department_name"><i class="fas fa-sitemap text-[10px] me-1" aria-hidden="true"></i>{{ user.department_name }}</span>
                        <span v-if="user.position_name"><i class="fas fa-briefcase text-[10px] me-1" aria-hidden="true"></i>{{ user.position_name }}</span>
                        <span v-if="user.last_login_at"><i class="fas fa-right-to-bracket text-[10px] me-1" aria-hidden="true"></i>{{ user.last_login_at }}</span>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-end gap-3">
                    <div class="flex flex-col sm:flex-row flex-wrap items-end gap-3">
                        <div class="w-44">
                            <FormSelect
                                v-model="period"
                                :options="periodOptions"
                                :label="t('useractivity.period')"
                                :dir="direction"
                                @change="applyPeriod"
                            />
                        </div>
                        <div class="w-44">
                            <FormDatepicker
                                v-model="from"
                                :label="t('useractivity.from')"
                                :disabled="!isCustom"
                                :dir="direction"
                            />
                        </div>
                        <div class="w-44">
                            <FormDatepicker
                                v-model="to"
                                :label="t('useractivity.to')"
                                :disabled="!isCustom"
                                :dir="direction"
                            />
                        </div>
                    </div>
                    <IdleGapControl :value="idle_gap_minutes" />
                    <Button variant="primary" size="sm" icon="fas fa-filter" @click="applyFilters">
                        {{ t('useractivity.apply') }}
                    </Button>
                </div>
            </div>

            <p class="flex items-center gap-1.5 text-[12px] text-mistral-stone">
                <i class="fas fa-circle-info text-mistral-info text-[11px]" aria-hidden="true"></i>
                {{ t('useractivity.hours_tooltip') }}
            </p>

            <!-- KPIs -->
            <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                <StatCard
                    v-for="card in kpiCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :icon="card.icon"
                    :color="card.color"
                    :dir="direction"
                />
            </div>

            <template v-if="detail.kpis.total_actions > 0">
                <!-- Charts row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <DashboardWidget :title="t('useractivity.daily_actions')" icon="fas fa-chart-bar" icon-color="primary">
                        <DashboardChart type="bar" :data="dailyActionsData" :height="240" />
                    </DashboardWidget>

                    <DashboardWidget :title="t('useractivity.daily_hours')" icon="fas fa-chart-line" icon-color="success">
                        <DashboardChart type="line" :data="dailyHoursData" :height="240" />
                    </DashboardWidget>

                    <DashboardWidget :title="t('useractivity.actions_breakdown')" icon="fas fa-chart-pie" icon-color="info">
                        <DashboardChart type="doughnut" :data="actionBreakdownData" :options="doughnutOptions" :height="240" />
                    </DashboardWidget>
                </div>

                <!-- Breakdown list -->
                <div class="bg-white border border-mistral-hairline-soft rounded-xl overflow-hidden">
                    <div class="flex items-center gap-3 px-5 py-4 border-b border-mistral-hairline-soft">
                        <div class="w-8 h-8 rounded-lg bg-mistral-info/10 text-mistral-info flex items-center justify-center shrink-0">
                            <i class="fas fa-table-list text-[14px]" aria-hidden="true"></i>
                        </div>
                        <h3 class="text-[14px] font-semibold text-mistral-ink">{{ t('useractivity.actions_breakdown') }}</h3>
                    </div>
                    <div v-if="detail.breakdown.length" class="divide-y divide-mistral-hairline-soft/60">
                        <div
                            v-for="(item, i) in detail.breakdown"
                            :key="`${item.entity}-${item.action}-${i}`"
                            class="flex items-center gap-4 px-5 py-3"
                        >
                            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', actionMeta(item.action).color]">
                                <i :class="[actionMeta(item.action).icon, 'text-[12px]']" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[13px] font-medium text-mistral-ink truncate">
                                        {{ actionLabel(item.action) }} · {{ entityLabel(item.entity) }}
                                    </span>
                                    <span class="text-[13px] font-bold text-mistral-ink font-mono" dir="ltr">{{ item.count }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 bg-mistral-surface rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-mistral-info to-mistral-primary transition-all duration-500"
                                        :style="{ width: Math.max(3, (item.count / maxBreakdown) * 100) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <DashboardWidget :title="t('useractivity.recent_activity')" icon="fas fa-list-ul" icon-color="warning" :padded="false">
                    <div v-if="detail.timeline.length" class="divide-y divide-mistral-hairline-soft/60 max-h-[520px] overflow-y-auto">
                        <div
                            v-for="log in detail.timeline"
                            :key="log.id"
                            class="flex items-start gap-3 px-5 py-3 hover:bg-mistral-surface/40 transition-colors"
                        >
                            <div class="mt-0.5 flex flex-col items-center">
                                <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', actionMeta(log.action).color]">
                                    <i :class="[actionMeta(log.action).icon, 'text-[12px]']" aria-hidden="true"></i>
                                </div>
                                <span v-if="log.id !== detail.timeline[detail.timeline.length - 1].id" class="w-px flex-1 bg-mistral-hairline-soft my-1"></span>
                            </div>
                            <div class="min-w-0 flex-1 pb-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="text-[13px] font-semibold text-mistral-ink">{{ actionLabel(log.action) }}</span>
                                    <span class="text-[12px] text-mistral-stone">·</span>
                                    <span class="text-[12px] text-mistral-stone">{{ entityLabel(log.entity) }}</span>
                                    <Badge v-if="log.method" :text="log.method" variant="inactive" size="sm" class="!text-[10px]" />
                                </div>
                                <div v-if="log.url" class="text-[11px] text-mistral-muted font-mono truncate max-w-[520px] mt-0.5" dir="ltr">
                                    <i class="fas fa-link text-[9px] me-1" aria-hidden="true"></i>{{ pageLabel(log.url) }}
                                </div>
                                <div class="flex items-center gap-3 mt-0.5 text-[11px] text-mistral-stone">
                                    <span class="font-mono" dir="ltr">{{ log.created_at }}</span>
                                    <span>{{ timeAgo(log.created_at) }}</span>
                                    <span v-if="log.ip_address" class="font-mono" dir="ltr">{{ log.ip_address }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <EmptyState v-else icon="fas fa-hourglass-half" :title="t('useractivity.no_activity')" :description="t('useractivity.no_activity_in_range')" class="py-12" />
                </DashboardWidget>
            </template>

            <Card v-else variant="base">
                <EmptyState icon="fas fa-hourglass-half" :title="t('useractivity.no_activity')" :description="t('useractivity.no_activity_in_range')" />
            </Card>
        </div>
    </AppLayout>
</template>
