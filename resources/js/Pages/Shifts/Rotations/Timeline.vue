<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    rotation: { type: Object, required: true },
    groups: { type: Array, required: true },
    timeline: { type: Array, required: true },
    from: { type: String, required: true },
    to: { type: String, required: true },
});

const fromDate = ref(props.from);
const toDate = ref(props.to);

const groupColors = ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'];

const groupColorMap = computed(() => {
    const map = {};
    props.groups.forEach((group, idx) => {
        map[group.id] = {
            name: group.name,
            color: groupColors[idx % groupColors.length],
        };
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
        return {
            date: d.date,
            dayNum: date.getDate(),
            dayOfWeek: date.getDay(),
            monthShort: date.toLocaleDateString('ar', { month: 'short' }),
            isWeekend: date.getDay() === 5 || date.getDay() === 6,
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
        scrollContainer.value.scrollLeft = idx * 36 - 200;
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
    }, { preserveState: true });
}

function getDayCellClass(day) {
    if (day.isWeekend) return 'bg-gray-100';
    return '';
}

function getCellBg(employeeDays, idx) {
    const day = employeeDays[idx];
    if (!day) return 'bg-gray-50';
    return day.is_work_day ? 'bg-emerald-100 border-emerald-200' : 'bg-gray-50 border-gray-100';
}
</script>

<template>
    <AppLayout>
        <Head :title="t('shifts.rotation_timeline')" />

        <PageHeader :title="t('shifts.rotation_timeline')">
            <template #actions>
                <Link :href="route('rotations.show', rotation.id)">
                    <Button variant="secondary">
                        {{ t('common.back') }}
                    </Button>
                </Link>
            </template>
        </PageHeader>

        <div class="space-y-4">
            <Card>
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <h2 class="text-lg font-semibold">{{ rotation.name }}</h2>
                            <Badge variant="info">{{ t('shifts.cycle_length') }}: {{ rotation.cycle_length }}</Badge>
                            <Badge variant="info">{{ t('shifts.pattern') }}: {{ rotation.pattern?.join('') }}</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="secondary" size="sm" @click="navigateDate(-1)">
                                {{ t('common.previous') }}
                            </Button>
                            <Button variant="secondary" size="sm" @click="scrollToToday">
                                {{ t('shifts.today') }}
                            </Button>
                            <Button variant="secondary" size="sm" @click="navigateDate(1)">
                                {{ t('common.next') }}
                            </Button>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mb-4 text-sm text-gray-600">
                        <div class="flex items-center gap-1">
                            <div class="w-3 h-3 bg-emerald-100 border border-emerald-200 rounded"></div>
                            <span>{{ t('shifts.work_day') }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-3 h-3 bg-gray-50 border border-gray-100 rounded"></div>
                            <span>{{ t('shifts.rest_day') }}</span>
                        </div>
                        <div class="flex items-center gap-1" v-for="(group, idx) in groups" :key="group.id">
                            <div class="w-3 h-3 rounded" :style="{ backgroundColor: groupColors[idx % groupColors.length] }"></div>
                            <span>{{ group.name }}</span>
                        </div>
                    </div>

                    <div v-if="timeline.length === 0" class="text-center py-12 text-gray-500">
                        {{ t('shifts.no_assignments') }}
                    </div>

                    <div v-else class="overflow-auto border border-gray-200 rounded-lg" ref="scrollContainer" style="max-height: 600px;">
                        <table class="border-collapse min-w-max">
                            <thead class="sticky top-0 z-20 bg-white">
                                <tr>
                                    <th class="sticky left-0 z-30 bg-white border-b border-r border-gray-200 px-3 py-2 text-right text-sm font-semibold min-w-[180px]">
                                        {{ t('common.employee') }}
                                    </th>
                                    <th class="sticky left-[180px] z-30 bg-white border-b border-r border-gray-200 px-2 py-2 text-center text-xs font-medium min-w-[60px]">
                                        {{ t('shifts.group') }}
                                    </th>
                                    <th
                                        v-for="day in dayHeaders"
                                        :key="day.date"
                                        class="border-b border-r border-gray-200 px-0 py-1 text-center text-xs font-medium min-w-[36px]"
                                        :class="getDayCellClass(day)"
                                    >
                                        <div class="flex flex-col items-center">
                                            <span class="text-[10px] text-gray-400">{{ day.monthShort }}</span>
                                            <span class="font-semibold">{{ day.dayNum }}</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="emp in timeline" :key="emp.employee_id" class="hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white border-b border-r border-gray-200 px-3 py-1.5 text-sm">
                                        <div class="font-medium">{{ emp.employee_name }}</div>
                                        <div class="text-xs text-gray-500">{{ emp.employee_code }}</div>
                                    </td>
                                    <td class="sticky left-[180px] z-10 bg-white border-b border-r border-gray-200 px-2 py-1.5 text-center">
                                        <span
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white"
                                            :style="{ backgroundColor: groupColorMap[emp.group_id]?.color || '#6b7280' }"
                                        >
                                            {{ emp.group_name }}
                                        </span>
                                    </td>
                                    <td
                                        v-for="(day, idx) in emp.days"
                                        :key="day.date"
                                        class="border-b border-r border-gray-100 px-0 py-0 text-center text-[10px]"
                                        :class="getCellBg(emp.days, idx)"
                                    >
                                        <div class="w-full h-6 flex items-center justify-center">
                                            <span v-if="day.is_work_day" class="text-emerald-700 font-bold">W</span>
                                            <span v-else class="text-gray-400">-</span>
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
