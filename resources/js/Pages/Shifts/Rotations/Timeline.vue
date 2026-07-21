<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge, StatCard, SearchInput, FormSelect } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    rotation: { type: Object, required: true },
    groups: { type: Array, required: true },
    timeline: { type: Array, required: true },
    from: { type: String, required: true },
    to: { type: String, required: true },
    filters: { type: Object, default: () => ({ search: '', group_id: '' }) },
});

const fromDate = ref(props.from);
const toDate = ref(props.to);
const searchQuery = ref(props.filters.search || '');
const selectedGroup = ref(props.filters.group_id || '');

const groupColors = [
    { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-700', dot: 'bg-emerald-500' },
    { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700', dot: 'bg-blue-500' },
    { bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700', dot: 'bg-amber-500' },
    { bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700', dot: 'bg-red-500' },
    { bg: 'bg-violet-50', border: 'border-violet-200', text: 'text-violet-700', dot: 'bg-violet-500' },
    { bg: 'bg-cyan-50', border: 'border-cyan-200', text: 'text-cyan-700', dot: 'bg-cyan-500' },
    { bg: 'bg-pink-50', border: 'border-pink-200', text: 'text-pink-700', dot: 'bg-pink-500' },
    { bg: 'bg-teal-50', border: 'border-teal-200', text: 'text-teal-700', dot: 'bg-teal-500' },
];

const groupColorMap = computed(() => {
    const map = {};
    props.groups.forEach((group, idx) => {
        map[group.id] = groupColors[idx % groupColors.length];
    });
    return map;
});

const days = computed(() => {
    if (!props.timeline.length) return [];
    return props.timeline[0]?.days || [];
});

const dayHeaders = computed(() => {
    return days.value.map(d => {
        const date = new Date(d.date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const cellDate = new Date(d.date);
        cellDate.setHours(0, 0, 0, 0);
        return {
            date: d.date,
            dayNum: date.getDate(),
            dayOfWeek: date.getDay(),
            monthShort: date.toLocaleDateString('ar', { month: 'short' }),
            isWeekend: date.getDay() === 5 || date.getDay() === 6,
            isToday: cellDate.getTime() === today.getTime(),
        };
    });
});

const monthGroups = computed(() => {
    const groups = [];
    let currentMonth = null;
    let currentGroup = null;

    dayHeaders.value.forEach((day, idx) => {
        const monthKey = day.date.substring(0, 7);
        if (monthKey !== currentMonth) {
            currentMonth = monthKey;
            currentGroup = { month: day.monthShort, startIdx: idx, count: 0 };
            groups.push(currentGroup);
        }
        currentGroup.count++;
    });

    return groups;
});

const scrollContainer = ref(null);

function scrollToToday() {
    const today = new Date().toISOString().split('T')[0];
    const idx = days.value.findIndex(d => d.date === today);
    if (idx >= 0 && scrollContainer.value) {
        scrollContainer.value.scrollLeft = idx * 40 - 200;
    }
}

function navigateDate(direction) {
    const from = new Date(fromDate.value);
    const to = new Date(toDate.value);
    const diffDays = Math.round((to - from) / (1000 * 60 * 60 * 24));

    from.setDate(from.getDate() + direction * diffDays);
    to.setDate(to.getDate() + direction * diffDays);

    router.get(route('rotations.timeline', props.rotation.id), {
        from: from.toISOString().split('T')[0],
        to: to.toISOString().split('T')[0],
        search: searchQuery.value,
        group_id: selectedGroup.value,
    }, { preserveState: true });
}

function applyDateRange() {
    router.get(route('rotations.timeline', props.rotation.id), {
        from: fromDate.value,
        to: toDate.value,
        search: searchQuery.value,
        group_id: selectedGroup.value,
    }, { preserveState: true });
}

let searchTimer = null;
function onSearch(value) {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('rotations.timeline', props.rotation.id), {
            from: fromDate.value,
            to: toDate.value,
            search: value,
            group_id: selectedGroup.value,
        }, { preserveState: true, replace: true });
    }, 300);
}

function onGroupFilter(value) {
    selectedGroup.value = value;
    router.get(route('rotations.timeline', props.rotation.id), {
        from: fromDate.value,
        to: toDate.value,
        search: searchQuery.value,
        group_id: value,
    }, { preserveState: true, replace: true });
}

function exportExcel() {
    const params = new URLSearchParams({
        from: fromDate.value,
        to: toDate.value,
        group_id: selectedGroup.value,
    });
    window.location.href = route('rotations.timeline.export', props.rotation.id) + '?' + params.toString();
}

function getCellBg(day) {
    if (day.isWeekend) return 'bg-gray-100/70';
    return '';
}

function getCellContent(employeeDays, idx) {
    const day = employeeDays[idx];
    if (!day) return { type: 'empty' };
    return day.is_work_day ? { type: 'work' } : { type: 'rest' };
}

const totalEmployees = computed(() => props.timeline.length);
const totalWorkDays = computed(() => props.timeline.reduce((sum, emp) => sum + (emp.work_days_count || 0), 0));
const totalRestDays = computed(() => props.timeline.reduce((sum, emp) => sum + (emp.rest_days_count || 0), 0));
const workRatio = computed(() => {
    const total = totalWorkDays.value + totalRestDays.value;
    if (total === 0) return 0;
    return Math.round((totalWorkDays.value / total) * 100);
});

const groupOptions = computed(() => {
    return [
        { value: '', label: t('shifts.all_groups') },
        ...props.groups.map(g => ({ value: String(g.id), label: g.name })),
    ];
});
</script>

<template>
    <AppLayout>
        <Head :title="t('shifts.rotation_timeline')" />

        <PageHeader :title="t('shifts.rotation_timeline')">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Button variant="primary" icon="fas fa-file-export" @click="exportExcel">
                        {{ t('shifts.timeline_export') }}
                    </Button>
                    <Link :href="route('rotations.show', rotation.id)">
                        <Button variant="secondary" icon="fas fa-arrow-right">
                            {{ t('common.back') }}
                        </Button>
                    </Link>
                </div>
            </template>
        </PageHeader>

        <div class="space-y-5" dir="rtl">
            <!-- Summary Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard
                    :label="t('shifts.total_employees')"
                    :value="totalEmployees"
                    icon="fas fa-users"
                    color="primary"
                />
                <StatCard
                    :label="t('shifts.total_work_days')"
                    :value="totalWorkDays"
                    icon="fas fa-briefcase"
                    color="success"
                />
                <StatCard
                    :label="t('shifts.total_rest_days')"
                    :value="totalRestDays"
                    icon="fas fa-bed"
                    color="info"
                />
                <StatCard
                    :label="t('shifts.work_days_ratio')"
                    :value="workRatio + '%'"
                    icon="fas fa-chart-pie"
                    color="warning"
                />
            </div>

            <!-- Toolbar -->
            <Card>
                <div class="p-4">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <!-- Rotation Info -->
                        <div class="flex items-center gap-3 me-auto">
                            <h2 class="text-lg font-bold text-mistral-ink">{{ rotation.name }}</h2>
                            <Badge :text="t('shifts.cycle_length') + ': ' + rotation.cycle_length" variant="orange" size="sm" />
                            <Badge :text="rotation.pattern?.join('')" variant="cream" size="sm" />
                        </div>

                        <!-- Search -->
                        <SearchInput
                            v-model="searchQuery"
                            :placeholder="t('shifts.search_employee')"
                            @search="onSearch"
                        />

                        <!-- Group Filter -->
                        <div class="w-40">
                            <FormSelect
                                v-model="selectedGroup"
                                :options="groupOptions"
                                :placeholder="t('shifts.all_groups')"
                                @update:modelValue="onGroupFilter"
                            />
                        </div>
                    </div>

                    <!-- Date Navigation -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-mistral-steel font-medium">{{ t('shifts.range_from') }}</label>
                            <input
                                v-model="fromDate"
                                type="date"
                                class="h-9 px-3 text-sm border border-mistral-hairline-strong rounded-lg focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-mistral-steel font-medium">{{ t('shifts.range_to') }}</label>
                            <input
                                v-model="toDate"
                                type="date"
                                class="h-9 px-3 text-sm border border-mistral-hairline-strong rounded-lg focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary outline-none"
                            />
                        </div>
                        <Button variant="primary" size="sm" @click="applyDateRange">
                            {{ t('common.apply') || 'تطبيق' }}
                        </Button>

                        <div class="h-6 w-px bg-mistral-hairline mx-1"></div>

                        <Button variant="secondary" size="sm" icon="fas fa-chevron-right" @click="navigateDate(-1)">
                            {{ t('common.previous') }}
                        </Button>
                        <Button variant="secondary" size="sm" icon="fas fa-crosshairs" @click="scrollToToday">
                            {{ t('shifts.today') }}
                        </Button>
                        <Button variant="secondary" size="sm" icon="fas fa-chevron-left" @click="navigateDate(1)">
                            {{ t('common.next') }}
                        </Button>

                        <!-- Legend -->
                        <div class="flex items-center gap-4 me-auto">
                            <div class="flex items-center gap-1.5">
                                <div class="w-5 h-5 rounded-md bg-emerald-200 flex items-center justify-center">
                                    <i class="fas fa-check text-emerald-700 text-[9px]"></i>
                                </div>
                                <span class="text-xs text-mistral-steel">{{ t('shifts.work_day') }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="w-5 h-5 rounded-md bg-gray-100 flex items-center justify-center">
                                    <span class="text-gray-400 text-[10px]">—</span>
                                </div>
                                <span class="text-xs text-mistral-steel">{{ t('shifts.rest_day') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Timeline Table -->
            <Card>
                <div class="p-0">
                    <div v-if="timeline.length === 0" class="text-center py-16">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-mistral-surface flex items-center justify-center">
                            <i class="fas fa-users text-mistral-muted text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-mistral-ink mb-1">{{ t('shifts.no_assignments') }}</h3>
                        <p class="text-sm text-mistral-steel">{{ t('shifts.no_assignments_description') }}</p>
                    </div>

                    <div v-else class="overflow-auto border-t border-mistral-hairline-soft" ref="scrollContainer" style="max-height: 650px;">
                        <table class="border-collapse min-w-max w-full">
                            <thead class="sticky top-0 z-20 bg-mistral-surface">
                                <!-- Month Header Row -->
                                <tr>
                                    <th
                                        class="sticky right-0 z-30 bg-mistral-surface border-b border-l border-mistral-hairline px-4 py-2 text-right text-sm font-bold min-w-[200px]"
                                        colspan="1"
                                        rowspan="2"
                                    >
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-user text-mistral-primary text-xs"></i>
                                            {{ t('shifts.employee') }}
                                        </div>
                                    </th>
                                    <th
                                        class="sticky right-[200px] z-30 bg-mistral-surface border-b border-l border-mistral-hairline px-3 py-2 text-center text-sm font-bold min-w-[70px]"
                                        rowspan="2"
                                    >
                                        <div class="flex items-center justify-center gap-1">
                                            <i class="fas fa-layer-group text-mistral-primary text-xs"></i>
                                            {{ t('shifts.group') }}
                                        </div>
                                    </th>
                                    <th
                                        v-for="mg in monthGroups"
                                        :key="mg.month + mg.startIdx"
                                        :colspan="mg.count"
                                        class="border-b border-l border-mistral-hairline px-2 py-1.5 text-center text-xs font-bold text-mistral-ink bg-mistral-cream/30"
                                    >
                                        {{ mg.month }}
                                    </th>
                                </tr>
                                <!-- Day Number Row -->
                                <tr>
                                    <th
                                        v-for="day in dayHeaders"
                                        :key="day.date"
                                        class="border-b border-l border-mistral-hairline px-0 py-1.5 text-center text-xs font-medium min-w-[40px]"
                                        :class="[
                                            day.isWeekend ? 'bg-gray-100/80' : '',
                                            day.isToday ? 'bg-mistral-primary/10 border-mistral-primary/30' : '',
                                        ]"
                                    >
                                        <div class="flex flex-col items-center leading-tight">
                                            <span class="text-[10px]" :class="day.isToday ? 'text-mistral-primary font-bold' : 'text-mistral-stone'">
                                                {{ day.dayNum }}
                                            </span>
                                            <span
                                                class="text-[9px] font-semibold"
                                                :class="[
                                                    day.isToday ? 'text-mistral-primary' : '',
                                                    day.dayOfWeek === 5 ? 'text-amber-600' : '',
                                                    day.dayOfWeek === 6 ? 'text-amber-600' : '',
                                                    !day.isToday && day.dayOfWeek !== 5 && day.dayOfWeek !== 6 ? 'text-mistral-stone' : '',
                                                ]"
                                            >
                                                {{ ['أحد', 'إثن', 'ثلا', 'أرب', 'خمي', 'جمع', 'سبت'][day.dayOfWeek] }}
                                            </span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(emp, rowIdx) in timeline"
                                    :key="emp.employee_id"
                                    class="group transition-colors"
                                    :class="rowIdx % 2 === 0 ? 'bg-white' : 'bg-mistral-surface/30'"
                                >
                                    <!-- Employee Name -->
                                    <td class="sticky right-0 z-10 border-b border-l border-mistral-hairline px-4 py-2.5 min-w-[200px]"
                                        :class="rowIdx % 2 === 0 ? 'bg-white' : 'bg-mistral-surface/30'"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-mistral-primary/10 flex items-center justify-center shrink-0">
                                                <span class="text-sm font-bold text-mistral-primary">
                                                    {{ emp.employee_name?.charAt(0) }}
                                                </span>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-mistral-ink truncate">{{ emp.employee_name }}</div>
                                                <div class="text-xs text-mistral-steel">{{ emp.employee_code }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Group Badge -->
                                    <td class="sticky right-[200px] z-10 border-b border-l border-mistral-hairline px-3 py-2.5 text-center min-w-[70px]"
                                        :class="rowIdx % 2 === 0 ? 'bg-white' : 'bg-mistral-surface/30'"
                                    >
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-bold text-white shadow-sm"
                                            :style="{ backgroundColor: groupColors[emp.group_index % groupColors.length]?.dot || '#6b7280' }"
                                        >
                                            {{ emp.group_name }}
                                        </span>
                                    </td>

                                    <!-- Day Cells -->
                                    <td
                                        v-for="(day, idx) in emp.days"
                                        :key="day.date"
                                        class="border-b border-l border-mistral-hairline/50 px-0 py-0 text-center transition-colors"
                                        :class="[
                                            getCellBg(day),
                                            dayHeaders[idx]?.isToday ? 'bg-mistral-primary/5 border-mistral-primary/20' : '',
                                        ]"
                                    >
                                        <div class="w-full h-10 flex items-center justify-center">
                                            <template v-if="getCellContent(emp.days, idx).type === 'work'">
                                                <div class="w-6 h-6 rounded-md bg-emerald-200 flex items-center justify-center">
                                                    <i class="fas fa-check text-emerald-700 text-[9px]"></i>
                                                </div>
                                            </template>
                                            <template v-else-if="getCellContent(emp.days, idx).type === 'rest'">
                                                <span class="text-gray-300 text-xs">—</span>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
