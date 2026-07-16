<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    rotation: { type: Object, required: true },
    preview: { type: Array, required: true },
    preview_from: { type: String, required: true },
    preview_to: { type: String, required: true },
});

const currentMonth = ref(new Date(props.preview_from).getMonth());
const currentYear = ref(new Date(props.preview_from).getFullYear());

const monthNames = computed(() => [
    t('shifts.january'), t('shifts.february'), t('shifts.march'),
    t('shifts.april'), t('shifts.may'), t('shifts.june'),
    t('shifts.july'), t('shifts.august'), t('shifts.september'),
    t('shifts.october'), t('shifts.november'), t('shifts.december')
]);

const shortDayNames = computed(() => [
    t('shifts.sun_short'), t('shifts.mon_short'), t('shifts.tue_short'),
    t('shifts.wed_short'), t('shifts.thu_short'), t('shifts.fri_short'),
    t('shifts.sat_short')
]);

const groupColors = ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'];

const stats = computed(() => {
    const groups = props.rotation.groups || [];
    return groups.map((group, idx) => ({
        ...group,
        color: groupColors[idx % groupColors.length],
    }));
});

const monthSchedule = computed(() => {
    const days = [];
    const firstDay = new Date(currentYear.value, currentMonth.value, 1);
    const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0);
    const startDay = firstDay.getDay();

    for (let i = 0; i < startDay; i++) {
        days.push({ date: null, groups: {}, isEmpty: true });
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const previewDay = props.preview.find(p => p.date === dateStr);

        days.push({
            date: dateStr,
            day: d,
            groups: previewDay?.groups || {},
            isEmpty: false,
            isToday: dateStr === new Date().toISOString().split('T')[0],
        });
    }

    return days;
});

const chunkedDays = computed(() => {
    const chunks = [];
    for (let i = 0; i < monthSchedule.value.length; i += 7) {
        chunks.push(monthSchedule.value.slice(i, i + 7));
    }
    return chunks;
});

const prevMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
};
</script>

<template>
    <AppLayout :title="t('shifts.rotation_details') + ': ' + rotation.name">
        <PageHeader
            :title="t('shifts.rotation_details') + ': ' + rotation.name"
            :description="rotation.description || ''"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.index')" icon="fas fa-arrow-right">
                    {{ t('common.back') }}
                </Button>
                <Button variant="secondary" :href="route('rotations.edit', rotation.id)" icon="fas fa-edit">
                    {{ t('common.edit') }}
                </Button>
                <Button variant="primary" :href="route('rotations.assign', { rotation: rotation.id })" icon="fas fa-user-plus">
                    {{ t('shifts.assign_employee') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <Card variant="stat" padding="lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                            {{ t('shifts.cycle_length') }}
                        </p>
                        <p class="text-[28px] font-bold text-mistral-ink mt-1">{{ rotation.cycle_length }} {{ t('shifts.days') }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-mistral-primary/10 flex items-center justify-center">
                        <i class="fas fa-redo text-mistral-primary text-xl"></i>
                    </div>
                </div>
            </Card>

            <Card variant="stat" padding="lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                            {{ t('shifts.groups_count') }}
                        </p>
                        <p class="text-[28px] font-bold text-blue-600 mt-1">{{ rotation.number_of_groups }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                </div>
            </Card>

            <Card variant="stat" padding="lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                            {{ t('shifts.active_employees') }}
                        </p>
                        <p class="text-[28px] font-bold text-green-600 mt-1">{{ rotation.active_employees_count || 0 }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                        <i class="fas fa-user-check text-green-600 text-xl"></i>
                    </div>
                </div>
            </Card>
        </div>

        <Card variant="base" padding="lg" class="mb-6">
            <template #header>
                <h3 class="text-[16px] font-semibold text-mistral-ink">
                    {{ t('shifts.rotation_info') }}
                </h3>
            </template>
            <dl class="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-3">
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('shifts.work_pattern') }}</dt>
                    <dd class="text-[14px] text-mistral-ink mt-1 font-mono">{{ rotation.work_days_count }}+{{ rotation.rest_days_count }}</dd>
                </div>
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('shifts.anchor_start_date') }}</dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">{{ rotation.anchor_start_date }}</dd>
                </div>
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('shifts.overtime_enabled') }}</dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">
                        <Badge :text="rotation.overtime_enabled ? t('common.active') : t('common.inactive')" :variant="rotation.overtime_enabled ? 'active' : 'inactive'" />
                    </dd>
                </div>
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('shifts.grace_minutes') }}</dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">{{ rotation.grace_minutes }} {{ t('shifts.minutes') }}</dd>
                </div>
            </dl>
        </Card>

        <Card variant="base" padding="lg" class="mb-6">
            <template #header>
                <h3 class="text-[16px] font-semibold text-mistral-ink">
                    {{ t('shifts.groups') }}
                </h3>
            </template>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-mistral-hairline">
                            <th class="px-4 py-2 text-right text-mistral-slate">{{ t('shifts.group_name') }}</th>
                            <th class="px-4 py-2 text-center text-mistral-slate">{{ t('shifts.group_index') }}</th>
                            <th class="px-4 py-2 text-center text-mistral-slate">{{ t('shifts.time_schedule') }}</th>
                            <th class="px-4 py-2 text-center text-mistral-slate">{{ t('shifts.employees_count') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(group, idx) in rotation.groups" :key="group.id" class="border-b border-mistral-hairline last:border-0">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: groupColors[idx % groupColors.length] }"></div>
                                    <span class="font-medium">{{ group.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">{{ group.group_index }}</td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="group.time_schedule">{{ group.time_schedule.name }}</span>
                                <span v-else class="text-mistral-muted">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">{{ group.active_employees_count || 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Card variant="base" padding="lg">
            <template #header>
                <div class="flex items-center justify-between">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ monthNames[currentMonth] }} {{ currentYear }}
                    </h3>
                    <div class="flex items-center gap-2">
                        <Button variant="ghost" size="sm" @click="prevMonth" icon="fas fa-chevron-right" />
                        <Button variant="ghost" size="sm" @click="nextMonth" icon="fas fa-chevron-left" />
                    </div>
                </div>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full text-center text-[13px]">
                    <thead>
                        <tr class="border-b border-mistral-hairline">
                            <th v-for="(name, i) in shortDayNames" :key="i" class="px-3 py-2 text-mistral-slate">
                                {{ name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(week, weekIndex) in chunkedDays" :key="weekIndex" class="border-b border-mistral-hairline last:border-0">
                            <td v-for="(day, dayIndex) in week" :key="dayIndex" class="px-1 py-1.5 relative">
                                <div v-if="!day.isEmpty" class="min-h-[60px]">
                                    <div
                                        class="text-[11px] font-medium mb-1"
                                        :class="day.isToday ? 'text-mistral-primary font-bold' : 'text-mistral-ink'"
                                    >
                                        {{ day.day }}
                                    </div>
                                    <div class="space-y-0.5">
                                        <div
                                            v-for="(groupData, groupId) in day.groups"
                                            :key="groupId"
                                            class="text-[10px] px-1 py-0.5 rounded"
                                            :class="groupData.is_work_day
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-gray-100 text-gray-500'"
                                        >
                                            {{ groupData.name }}: {{ groupData.is_work_day ? 'W' : 'R' }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="min-h-[60px]"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex items-center justify-center gap-6 text-sm">
                <div v-for="(group, idx) in stats" :key="group.id" class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded" :style="{ backgroundColor: group.color }"></span>
                    <span class="text-mistral-ink">{{ group.name }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-green-100"></span>
                    <span class="text-mistral-ink">{{ t('shifts.work_day') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-gray-100"></span>
                    <span class="text-mistral-ink">{{ t('shifts.rest_day') }}</span>
                </div>
            </div>
        </Card>
    </AppLayout>
</template>
