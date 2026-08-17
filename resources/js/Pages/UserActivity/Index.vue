<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import IdleGapControl from '@/Components/UserActivity/IdleGapControl.vue';
import { Avatar, Badge, Button, DataTable, FormDatepicker, FormSelect, PageHeader, SearchInput, StatCard } from '@/Components/ui';
import { usePeriodFilter } from '@/composables/usePeriodFilter';
import { useTranslations } from '@/composables/useTranslations';
import { actionMeta } from './actionMeta';

const props = defineProps({
    overview: { type: Object, required: true },
    filters: { type: Object, default: () => ({ from: '', to: '', search: '', period: 'custom' }) },
    idle_gap_minutes: { type: Number, default: 2 },
});

const { t, isRtl, direction, translations } = useTranslations();

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const search = ref(props.filters.search);

const { period, periodOptions, isCustom, applyPeriod, today, daysAgo } = usePeriodFilter(
    from,
    to,
    props.filters.period,
    applyFilters,
);

const hasActiveFilters = computed(() =>
    period.value !== 'custom' || search.value !== '' || from.value !== daysAgo(29) || to.value !== today(),
);

function applyFilters() {
    router.get(
        route('user-activity.index'),
        { from: from.value, to: to.value, search: search.value, period: period.value, page: 1 },
        { preserveState: true, preserveScroll: true, replace: true, only: ['overview', 'filters'] },
    );
}

function resetFilters() {
    period.value = 'custom';
    from.value = daysAgo(29);
    to.value = today();
    search.value = '';
    applyFilters();
}

function onSearch(value) {
    search.value = value;
    applyFilters();
}

function openUser(row) {
    router.get(route('user-activity.show', row.id));
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

const columns = [
    { key: 'user', label: t('useractivity.user'), sortable: true },
    { key: 'department', label: t('useractivity.department') },
    { key: 'actions', label: t('useractivity.actions_count'), sortable: true },
    { key: 'hours', label: t('useractivity.active_hours'), sortable: true },
    { key: 'active_days', label: t('useractivity.active_days'), sortable: true },
    { key: 'last_active', label: t('useractivity.last_activity'), sortable: true },
];

const maxTopCount = Math.max(1, ...(props.overview.top_entities || []).map((e) => e.count));

const kpiCards = [
    { label: t('useractivity.active_users'), value: props.overview.totals.active_users, icon: 'fas fa-users', color: 'primary' },
    { label: t('useractivity.total_actions'), value: props.overview.totals.total_actions, icon: 'fas fa-bolt', color: 'info' },
    { label: t('useractivity.total_active_hours'), value: formatHours(props.overview.totals.total_active_minutes), icon: 'fas fa-clock', color: 'success' },
    { label: t('useractivity.inactive_users'), value: props.overview.totals.inactive_users, icon: 'fas fa-user-xmark', color: 'danger' },
];


usePageTitle(t('useractivity.title'));
</script>

<template>
    
        <div class="space-y-5">
            <PageHeader :title="t('useractivity.title')" :description="t('useractivity.subtitle')" :dir="direction">
                <template #actions>
                    <span class="inline-flex items-center gap-1.5 text-[12px] text-mistral-stone bg-white border border-mistral-hairline-soft rounded-lg px-3 py-2">
                        <i class="fas fa-circle-info text-mistral-info text-[11px]" aria-hidden="true"></i>
                        {{ t('useractivity.tracking_note') }}
                    </span>
                </template>
            </PageHeader>

            <!-- Filters -->
            <div class="bg-white border border-mistral-hairline-soft rounded-xl p-4 shadow-sm space-y-4">
                <div class="flex items-center gap-2 text-[12px] font-semibold text-mistral-stone">
                    <i class="fas fa-sliders text-mistral-info text-[11px]" aria-hidden="true"></i>
                    {{ t('useractivity.filters_title') }}
                </div>
                <div class="flex flex-col xl:flex-row xl:items-end gap-3">
                    <div class="flex flex-col sm:flex-row flex-wrap items-end gap-3">
                        <div class="w-full sm:w-48">
                            <FormSelect
                                v-model="period"
                                :options="periodOptions"
                                :label="t('useractivity.period')"
                                :dir="direction"
                                @change="applyPeriod"
                            />
                        </div>
                        <div class="w-full sm:w-48">
                            <FormDatepicker
                                v-model="from"
                                :label="t('useractivity.from')"
                                :disabled="!isCustom"
                                :dir="direction"
                            />
                        </div>
                        <div class="w-full sm:w-48">
                            <FormDatepicker
                                v-model="to"
                                :label="t('useractivity.to')"
                                :disabled="!isCustom"
                                :dir="direction"
                            />
                        </div>
                        <Button variant="primary" size="md" icon="fas fa-filter" @click="applyFilters">
                            {{ t('useractivity.apply') }}
                        </Button>
                        <Button v-if="hasActiveFilters" variant="ghost" size="md" icon="fas fa-rotate-left" @click="resetFilters">
                            {{ t('useractivity.reset') }}
                        </Button>
                    </div>
                    <div class="flex flex-col sm:flex-row items-end gap-3 flex-1">
                        <div class="flex-1 min-w-[220px]">
                            <SearchInput
                                v-model="search"
                                :placeholder="t('useractivity.search_placeholder')"
                                :debounce="500"
                                :dir="direction"
                                @search="onSearch"
                            />
                        </div>
                        <IdleGapControl :value="idle_gap_minutes" />
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
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

            <!-- Users table -->
            <DataTable
                :columns="columns"
                :data="overview.users"
                :filters="{ from, to, search, period }"
                :route-name="'user-activity.index'"
                :only="['overview', 'filters']"
                :enable-search="false"
                :enable-filters="false"
                :enable-density="true"
                :enable-column-visibility="true"
                :enable-export="false"
                :selectable="false"
                :row-clickable="true"
                :per-page="15"
                :empty-title="t('useractivity.no_activity_in_range')"
                :dir="direction"
                @row-click="openUser"
            >
                <template #cell-user="{ row }">
                    <div class="flex items-center gap-3">
                        <Avatar :name="row.name" :src="row.avatar_url" size="sm" />
                        <div class="min-w-0">
                            <div class="text-[13px] font-semibold text-mistral-ink truncate max-w-[220px]">{{ row.name }}</div>
                            <div class="text-[11px] text-mistral-stone truncate max-w-[220px]" dir="ltr">{{ row.email || row.employee_code }}</div>
                        </div>
                    </div>
                </template>
                <template #cell-department="{ row }">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[12px] text-mistral-ink">{{ row.department_name || '—' }}</span>
                        <span class="text-[11px] text-mistral-stone">{{ row.position_name || '' }}</span>
                    </div>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-2">
                        <Badge :text="String(row.actions)" variant="primary" size="sm" />
                        <span v-if="row.logins > 0" class="text-[11px] text-mistral-stone" :title="t('useractivity.logins')">
                            <i class="fas fa-right-to-bracket text-[10px] ms-0.5" aria-hidden="true"></i>{{ row.logins }}
                        </span>
                    </div>
                </template>
                <template #cell-hours="{ row }">
                    <span
                        class="text-[13px] font-mono"
                        :class="row.active_minutes > 0 ? 'text-mistral-success font-semibold' : 'text-mistral-muted'"
                        :title="t('useractivity.hours_tooltip')"
                        dir="ltr"
                    >
                        {{ formatHours(row.active_minutes) }}
                    </span>
                </template>
                <template #cell-active_days="{ row }">
                    <span class="text-[13px]" :class="row.active_days > 0 ? 'text-mistral-ink' : 'text-mistral-muted'">{{ row.active_days }}</span>
                </template>
                <template #cell-last_active="{ row }">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[12px] text-mistral-ink">{{ timeAgo(row.last_active_at) }}</span>
                        <span v-if="row.last_active_at" class="text-[11px] text-mistral-stone font-mono" dir="ltr">{{ row.last_active_at }}</span>
                    </div>
                </template>
            </DataTable>

            <!-- Top actions -->
            <div class="bg-white border border-mistral-hairline-soft rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-mistral-hairline-soft">
                    <div class="w-8 h-8 rounded-lg bg-mistral-primary/10 text-mistral-primary flex items-center justify-center shrink-0">
                        <i class="fas fa-chart-simple text-[14px]" aria-hidden="true"></i>
                    </div>
                    <h3 class="text-[14px] font-semibold text-mistral-ink">{{ t('useractivity.top_actions') }}</h3>
                </div>
                <div v-if="overview.top_entities.length" class="divide-y divide-mistral-hairline-soft/60">
                    <div
                        v-for="item in overview.top_entities"
                        :key="`${item.entity}-${item.action}`"
                        class="flex items-center gap-4 px-5 py-3 hover:bg-mistral-surface/40 transition-colors"
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
                                    class="h-full rounded-full bg-gradient-to-r from-mistral-primary to-mistral-warning transition-all duration-500"
                                    :style="{ width: Math.max(4, (item.count / maxTopCount) * 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-10 text-center text-[13px] text-mistral-stone">
                    {{ t('useractivity.no_activity_in_range') }}
                </div>
            </div>
        </div>
    </template>
