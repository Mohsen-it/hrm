<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge, DataTable, LoadingSpinner, FormInput, FormSelect, FormModal } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    rotation: { type: Object, required: true },
    preview: { type: Array, required: true },
    preview_from: { type: String, required: true },
    preview_to: { type: String, required: true },
    time_schedules: { type: Array, default: () => [] },
});

const previewData = ref(props.preview);
const loadingPreview = ref(false);

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
        const previewDay = previewData.value.find(p => p.date === dateStr);

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

async function fetchPreview(from, to) {
    loadingPreview.value = true;
    try {
        const response = await fetch(route('rotations.preview', props.rotation.id) + `?from=${from}&to=${to}`);
        const data = await response.json();
        previewData.value = data.preview;
    } catch (e) {
        console.error('Failed to fetch preview:', e);
    } finally {
        loadingPreview.value = false;
    }
}

const prevMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
    prefetchIfNeeded();
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
    prefetchIfNeeded();
};

function prefetchIfNeeded() {
    const monthStart = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-01`;
    const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
    const monthEnd = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

    const hasData = previewData.value.some(p => p.date >= monthStart && p.date <= monthEnd);
    if (!hasData) {
        const from = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-01`;
        const to = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
        fetchPreview(from, to);
    }
}

const groupColumns = computed(() => [
    { key: 'name', label: t('shifts.group_name') },
    { key: 'group_index', label: t('shifts.group_index'), headerClass: 'text-center' },
    { key: 'time_schedule', label: t('shifts.time_schedule'), headerClass: 'text-center' },
    { key: 'active_employees_count', label: t('shifts.employees_count'), headerClass: 'text-center' },
    { key: 'actions', label: '', headerClass: 'text-center', sortable: false },
]);

const groupsData = computed(() => ({
    data: (props.rotation.groups || []).map((g, idx) => ({ ...g, id: g.id || idx })),
    links: [],
    total: (props.rotation.groups || []).length,
    current_page: 1,
    last_page: 1,
    per_page: 1000,
    from: 1,
    to: (props.rotation.groups || []).length,
}));

const showGroupModal = ref(false);
const editingGroup = ref(null);
const groupForm = ref({ name: '', time_schedule_id: null, start_date: '' });
const groupErrors = ref({});
const processingGroup = ref(false);

const timeSchedules = computed(() => props.time_schedules || []);

function openAddGroup() {
    editingGroup.value = null;
    groupForm.value = { name: '', time_schedule_id: null, start_date: props.rotation.anchor_start_date || '' };
    groupErrors.value = {};
    showGroupModal.value = true;
}

function openEditGroup(group) {
    editingGroup.value = group;
    groupForm.value = {
        name: group.name || '',
        time_schedule_id: group.time_schedule?.id || null,
        start_date: group.start_date || '',
    };
    groupErrors.value = {};
    showGroupModal.value = true;
}

function submitGroup() {
    processingGroup.value = true;
    groupErrors.value = {};

    if (editingGroup.value) {
        router.put(route('rotations.groups.update', editingGroup.value.id), {
            name: groupForm.value.name,
            time_schedule_id: groupForm.value.time_schedule_id,
            start_date: groupForm.value.start_date,
        }, {
            preserveScroll: true,
            onError: (err) => { groupErrors.value = err; },
            onFinish: () => { processingGroup.value = false; showGroupModal.value = false; },
        });
    } else {
        router.post(route('rotations.groups.add', props.rotation.id), {
            name: groupForm.value.name,
            time_schedule_id: groupForm.value.time_schedule_id,
            start_date: groupForm.value.start_date,
        }, {
            preserveScroll: true,
            onError: (err) => { groupErrors.value = err; },
            onFinish: () => { processingGroup.value = false; showGroupModal.value = false; },
        });
    }
}

function deleteGroup(group) {
    if (confirm(t('shifts.confirm_delete_group') + ' ' + group.name + '?')) {
        router.delete(route('rotations.groups.delete', group.id), {
            preserveScroll: true,
        });
    }
}
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
                <Button variant="secondary" :href="route('rotations.assign.manage', { rotation: rotation.id })" icon="fas fa-users-cog">
                    {{ t('shifts.manage_assignments') }}
                </Button>
                <Button variant="secondary" :href="route('rotations.timeline', rotation.id)" icon="fas fa-project-diagram">
                    {{ t('shifts.timeline') }}
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
                        <p class="text-[28px] font-bold text-mistral-info mt-1">{{ rotation.number_of_groups }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-mistral-info/10 flex items-center justify-center">
                        <i class="fas fa-users text-mistral-info text-xl"></i>
                    </div>
                </div>
            </Card>

            <Card variant="stat" padding="lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                            {{ t('shifts.active_employees') }}
                        </p>
                        <p class="text-[28px] font-bold text-mistral-success mt-1">{{ rotation.active_employees_count || 0 }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-mistral-success/10 flex items-center justify-center">
                        <i class="fas fa-user-check text-mistral-success text-xl"></i>
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
                <div class="flex items-center justify-between">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ t('shifts.groups') }}
                    </h3>
                    <Button variant="primary" size="sm" @click="openAddGroup" icon="fas fa-plus">
                        {{ t('shifts.add_group') }}
                    </Button>
                </div>
            </template>
            <DataTable
                :columns="groupColumns"
                :data="groupsData"
                :selectable="false"
                :enable-search="false"
                :enable-filters="false"
                :enable-pagination="false"
                :enable-export="false"
                :enable-density="false"
                :enable-column-visibility="false"
                storage-key="rotation-groups"
            >
                <template #cell-name="{ row, value }">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: groupColors[groupsData.data.indexOf(row) % groupColors.length] }"></div>
                        <span class="font-medium">{{ value }}</span>
                    </div>
                </template>
                <template #cell-time_schedule="{ row }">
                    <span v-if="row.time_schedule">{{ row.time_schedule.name }}</span>
                    <span v-else class="text-mistral-muted">—</span>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            :href="route('rotations.assign.manage', { rotation: rotation.id, group: row.id })"
                            icon="fas fa-users"
                            :title="t('shifts.view_employees')"
                        />
                        <Button variant="ghost" size="sm" @click="openEditGroup(row)" icon="fas fa-edit" :title="t('common.edit')" />
                        <Button variant="ghost" size="sm" @click="deleteGroup(row)" icon="fas fa-trash" class="text-mistral-danger" :title="t('common.delete')" />
                    </div>
                </template>
            </DataTable>
        </Card>

        <FormModal
            v-model="showGroupModal"
            :title="editingGroup ? t('shifts.edit_group') : t('shifts.add_group')"
            @submit="submitGroup"
            :processing="processingGroup"
        >
            <FormInput
                v-model="groupForm.name"
                :label="t('shifts.group_name')"
                name="name"
                :error="groupErrors.name"
                required
            />
            <FormInput
                v-model="groupForm.start_date"
                :label="t('shifts.start_date')"
                name="start_date"
                type="date"
                :error="groupErrors.start_date"
            />
            <FormSelect
                v-model="groupForm.time_schedule_id"
                :label="t('shifts.time_schedule')"
                name="time_schedule_id"
                :options="timeSchedules.map(ts => ({ value: ts.id, label: ts.name }))"
                :error="groupErrors.time_schedule_id"
            />
        </FormModal>

        <Card variant="base" padding="lg">
            <template #header>
                <div class="flex items-center justify-between">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ monthNames[currentMonth] }} {{ currentYear }}
                    </h3>
                    <div class="flex items-center gap-2">
                        <Button variant="ghost" size="sm" @click="prevMonth" icon="fas fa-chevron-right" />
                        <Button variant="ghost" size="sm" @click="nextMonth" icon="fas fa-chevron-left" />
                        <LoadingSpinner v-if="loadingPreview" size="sm" />
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
                        <tr v-for="(week, weekIndex) in chunkedDays" :key="weekIndex" class="border-b border-mistral-hairline-soft last:border-0 even:bg-mistral-surface/30 hover:bg-mistral-cream-light/40 transition-colors">
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
                                                ? 'bg-mistral-success/10 text-mistral-success'
                                                : 'bg-mistral-surface text-mistral-steel'"
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
                    <span class="w-4 h-4 rounded bg-mistral-success/10"></span>
                    <span class="text-mistral-ink">{{ t('shifts.work_day') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-mistral-surface"></span>
                    <span class="text-mistral-ink">{{ t('shifts.rest_day') }}</span>
                </div>
            </div>
        </Card>
    </AppLayout>
</template>
