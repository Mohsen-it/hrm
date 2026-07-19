<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSelect, Badge, DataTable, EmptyState } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    rotations: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    preselected_rotation_id: { type: Number, default: null },
});

const selectedRotationId = ref(props.preselected_rotation_id || '');
const selectedGroupId = ref('');
const departmentFilter = ref('');
const searchQuery = ref('');
const employees = ref([]);
const selectedEmployees = ref([]);
const loading = ref(false);
const saving = ref(false);
const errors = ref({});

const rotationOptions = computed(() => {
    const items = Array.isArray(props.rotations) ? props.rotations : (props.rotations?.data || []);
    return items.map(r => ({ value: r.id, label: r.name }));
});

const selectedRotation = computed(() => {
    const items = Array.isArray(props.rotations) ? props.rotations : (props.rotations?.data || []);
    const rid = Number(selectedRotationId.value);
    return items.find(r => Number(r.id) === rid);
});

const groupOptions = computed(() => {
    if (!selectedRotation.value || !selectedRotation.value.groups) return [];
    return selectedRotation.value.groups.map(g => ({
        value: g.id,
        label: `${g.name} (${t('shifts.offset')}: ${g.group_index})`,
    }));
});

const departmentOptions = computed(() =>
    (props.departments || []).map(d => ({ value: d.id, label: d.department_name })),
);

const filteredEmployees = computed(() => {
    let result = employees.value;

    if (selectedGroupId.value) {
        result = result.filter(e => e.rotation_group_id === Number(selectedGroupId.value));
    }

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(e =>
            e.name?.toLowerCase().includes(q) ||
            e.employee_code?.toLowerCase().includes(q) ||
            e.first_name?.toLowerCase().includes(q) ||
            e.last_name?.toLowerCase().includes(q)
        );
    }

    return result;
});

const selectedCount = computed(() => selectedEmployees.value.length);

const allSelected = computed(() => {
    return filteredEmployees.value.length > 0 &&
        filteredEmployees.value.every(e => selectedEmployees.value.includes(e.id));
});

const selectAll = () => {
    if (allSelected.value) {
        selectedEmployees.value = [];
    } else {
        selectedEmployees.value = filteredEmployees.value.map(e => e.id);
    }
};

const toggleSelect = (empId) => {
    const idx = selectedEmployees.value.indexOf(empId);
    if (idx === -1) {
        selectedEmployees.value.push(empId);
    } else {
        selectedEmployees.value.splice(idx, 1);
    }
};

const isSelected = (empId) => selectedEmployees.value.includes(empId);

const employeeColumns = computed(() => [
    { key: 'employee_code', label: t('shifts.employee_code') },
    { key: 'name', label: t('shifts.employee_name') },
    { key: 'rotation_group_name', label: t('shifts.rotation_group'), headerClass: 'text-center' },
    { key: 'start_date', label: t('shifts.start_date'), headerClass: 'text-center' },
    { key: 'status', label: t('common.status'), headerClass: 'text-center' },
]);

const employeesData = computed(() => ({
    data: filteredEmployees.value.map(e => ({ ...e, id: e.id })),
    links: [],
    total: filteredEmployees.value.length,
    current_page: 1,
    last_page: 1,
    per_page: 1000,
    from: 1,
    to: filteredEmployees.value.length,
}));

function onSelectionChange(ids) {
    selectedEmployees.value = ids;
}

const groupColorClass = (groupName) => {
    const colors = {
        'A': 'bg-green-100 text-green-700 border-green-200',
        'B': 'bg-blue-100 text-blue-700 border-blue-200',
        'C': 'bg-amber-100 text-amber-700 border-amber-200',
        'D': 'bg-red-100 text-red-700 border-red-200',
        'E': 'bg-purple-100 text-purple-700 border-purple-200',
        'F': 'bg-cyan-100 text-cyan-700 border-cyan-200',
    };
    return colors[groupName] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const fetchEmployees = async () => {
    if (!selectedRotationId.value) {
        employees.value = [];
        return;
    }

    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (departmentFilter.value) params.set('department_id', departmentFilter.value);

        const response = await fetch(
            route('rotations.employees', selectedRotationId.value) + '?' + params.toString(),
            { headers: { Accept: 'application/json' } }
        );
        const data = await response.json();
        employees.value = data.employees || [];
    } catch (e) {
        employees.value = [];
    } finally {
        loading.value = false;
    }
};

watch(selectedRotationId, () => {
    selectedGroupId.value = '';
    selectedEmployees.value = [];
    fetchEmployees();
});

watch(departmentFilter, () => {
    fetchEmployees();
});

const assignToGroup = async () => {
    if (selectedEmployees.value.length === 0 || !selectedGroupId.value) return;

    saving.value = true;
    errors.value = {};

    try {
        const assignments = selectedEmployees.value.map(empId => ({
            employee_id: empId,
            rotation_group_id: Number(selectedGroupId.value),
        }));

        const response = await fetch(route('rotations.assign.bulk-transfer'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
            body: JSON.stringify({
                assignments,
                rotation_id: Number(selectedRotationId.value),
                effective_date: new Date().toISOString().split('T')[0],
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            if (data.errors) {
                errors.value = data.errors;
            }
            throw new Error(data.message || 'Failed');
        }

        selectedEmployees.value = [];
        await fetchEmployees();
    } catch (e) {
        console.error(e);
    } finally {
        saving.value = false;
    }
};

const unassignSelected = async () => {
    if (selectedEmployees.value.length === 0) return;

    saving.value = true;

    try {
        const response = await fetch(route('rotations.assign.bulk-unassign'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
            body: JSON.stringify({
                employee_ids: selectedEmployees.value,
            }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed');
        }

        selectedEmployees.value = [];
        await fetchEmployees();
    } catch (e) {
        console.error(e);
    } finally {
        saving.value = false;
    }
};

if (props.preselected_rotation_id) {
    fetchEmployees();
}
</script>

<template>
    <Head :title="t('shifts.manage_assignments')" />
    <AppLayout :title="t('shifts.manage_assignments')">
        <PageHeader
            :title="t('shifts.manage_assignments')"
            :description="t('shifts.manage_assignments_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.assign')">
                    {{ t('shifts.assign_employee') }}
                </Button>
                <Button variant="secondary" :href="route('rotations.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="md" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <FormSelect
                    v-model="selectedRotationId"
                    name="rotation_id"
                    :label="t('shifts.rotation')"
                    :options="rotationOptions"
                    :placeholder="t('shifts.select_rotation')"
                    required
                />

                <FormSelect
                    v-model="selectedGroupId"
                    name="group_id"
                    :label="t('shifts.filter_by_group')"
                    :options="[{ value: '', label: t('shifts.all_groups') }, ...groupOptions]"
                />

                <FormSelect
                    v-model="departmentFilter"
                    name="department_id"
                    :label="t('shifts.filter_by_department')"
                    :options="[{ value: '', label: t('shifts.all_departments') }, ...departmentOptions]"
                />

                <div class="flex items-end">
                    <Button
                        variant="ghost"
                        size="sm"
                        icon="fas fa-refresh"
                        @click="fetchEmployees"
                        :loading="loading"
                    >
                        {{ t('common.refresh') }}
                    </Button>
                </div>
            </div>

            <div v-if="selectedRotation" class="mt-4 p-4 bg-mistral-surface rounded-md">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-[13px]">
                    <div>
                        <span class="text-mistral-slate">{{ t('shifts.work_pattern') }}:</span>
                        <span class="font-mono font-medium ms-1">{{ selectedRotation.work_days_count }}+{{ selectedRotation.rest_days_count }}</span>
                    </div>
                    <div>
                        <span class="text-mistral-slate">{{ t('shifts.cycle_length') }}:</span>
                        <span class="font-medium ms-1">{{ selectedRotation.cycle_length }} {{ t('shifts.days') }}</span>
                    </div>
                    <div>
                        <span class="text-mistral-slate">{{ t('shifts.groups_count') }}:</span>
                        <span class="font-medium ms-1">{{ selectedRotation.number_of_groups }}</span>
                    </div>
                    <div>
                        <span class="text-mistral-slate">{{ t('shifts.active_employees') }}:</span>
                        <span class="font-medium ms-1">{{ employees.length }}</span>
                    </div>
                </div>

                <div v-if="selectedRotation.groups" class="mt-3 flex flex-wrap gap-2">
                    <span
                        v-for="group in selectedRotation.groups"
                        :key="group.id"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium border"
                        :class="groupColorClass(group.name)"
                    >
                        <span class="w-2 h-2 rounded-full" :class="{
                            'bg-green-500': group.name === 'A',
                            'bg-blue-500': group.name === 'B',
                            'bg-amber-500': group.name === 'C',
                            'bg-red-500': group.name === 'D',
                            'bg-purple-500': group.name === 'E',
                            'bg-cyan-500': group.name === 'F',
                        }"></span>
                        {{ group.name }}
                        <span class="text-mistral-muted">({{ group.active_employees_count || 0 }})</span>
                    </span>
                </div>
            </div>
        </Card>

        <Card v-if="selectedRotationId" variant="base" padding="0">
            <div class="p-4 border-b border-mistral-hairline">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <h3 class="text-[14px] font-semibold text-mistral-ink">
                            {{ t('shifts.all_employees') }} ({{ filteredEmployees.length }})
                        </h3>
                        <div v-if="selectedCount > 0" class="flex items-center gap-2">
                            <Badge :text="`${selectedCount} ${t('shifts.selected')}`" variant="active" />
                            <FormSelect
                                v-model="selectedGroupId"
                                :label="''"
                                name="target_group"
                                :options="groupOptions"
                                :placeholder="t('shifts.select_target_group')"
                                class="!mb-0 !w-auto"
                            />
                            <Button
                                variant="primary"
                                size="sm"
                                icon="fas fa-check"
                                :loading="saving"
                                :disabled="!selectedGroupId"
                                @click="assignToGroup"
                            >
                                {{ t('shifts.assign_to_group') }}
                            </Button>
                            <Button
                                variant="danger"
                                size="sm"
                                icon="fas fa-user-minus"
                                :loading="saving"
                                @click="unassignSelected"
                            >
                                {{ t('shifts.remove_from_rotation') }}
                            </Button>
                        </div>
                    </div>
                    <div class="w-64">
                        <FormInput
                            v-model="searchQuery"
                            :placeholder="t('shifts.search_employees_placeholder')"
                            icon="fas fa-search"
                        />
                    </div>
                </div>
            </div>

            <div v-if="loading" class="p-8 text-center">
                <i class="fas fa-spinner fa-spin text-mistral-primary text-xl"></i>
                <p class="text-[13px] text-mistral-muted mt-2">{{ t('common.loading') }}...</p>
            </div>

            <EmptyState
                v-else-if="filteredEmployees.length === 0"
                icon="fas fa-users"
                :title="t('shifts.no_employees')"
                :description="t('shifts.no_employees_description')"
            />

            <DataTable
                v-else
                :key="selectedRotationId"
                :columns="employeeColumns"
                :data="employeesData"
                :selectable="true"
                :enable-search="false"
                :enable-filters="false"
                :enable-pagination="false"
                :enable-export="false"
                :enable-density="false"
                :enable-column-visibility="false"
                storage-key="rotation-manage-assignments"
                @selection-change="onSelectionChange"
            >
                <template #cell-rotation_group_name="{ row }">
                    <span
                        v-if="row.rotation_group_name"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[12px] font-medium border"
                        :class="groupColorClass(row.rotation_group_name)"
                    >
                        <span class="w-2 h-2 rounded-full" :class="{
                            'bg-green-500': row.rotation_group_name === 'A',
                            'bg-blue-500': row.rotation_group_name === 'B',
                            'bg-amber-500': row.rotation_group_name === 'C',
                            'bg-red-500': row.rotation_group_name === 'D',
                            'bg-purple-500': row.rotation_group_name === 'E',
                            'bg-cyan-500': row.rotation_group_name === 'F',
                        }"></span>
                        {{ row.rotation_group_name }}
                    </span>
                    <span v-else class="text-mistral-muted text-[12px]">—</span>
                </template>
                <template #cell-start_date="{ row }">
                    {{ row.start_date || '—' }}
                </template>
                <template #cell-status="{ row }">
                    <Badge
                        v-if="row.rotation_group_id"
                        :text="t('shifts.assigned')"
                        variant="active"
                    />
                    <Badge
                        v-else
                        :text="t('shifts.unassigned')"
                        variant="inactive"
                    />
                </template>
            </DataTable>
        </Card>

        <Card v-else variant="base" padding="8">
            <EmptyState
                icon="fas fa-calendar-alt"
                :title="t('shifts.select_rotation_first')"
                :description="t('shifts.select_rotation_description')"
            />
        </Card>
    </AppLayout>
</template>
