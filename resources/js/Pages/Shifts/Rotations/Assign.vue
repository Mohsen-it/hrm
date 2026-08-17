<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { ref, reactive, computed, watch, onBeforeUnmount } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import { PageHeader, Button, Card, FormInput, FormSelect, Badge, DataTable, ErrorSummary, FormSection, FormActions, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const props = defineProps({
    rotations: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    preselected_rotation_id: { type: Number, default: null },
    preselected_group_id: { type: Number, default: null },
});

const form = reactive({
    employee_ids: [],
    rotation_id: props.preselected_rotation_id || '',
    rotation_group_id: props.preselected_group_id || '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    department_id: '',
});

const errors = ref({});
const processing = ref(false);
const generalError = ref('');
const loadingEmployees = ref(false);
const employees = ref([]);
const selectedEmployees = ref([]);
const searchQuery = ref('');
let searchTimeout;
let employeeSearchController;

const errorFor = (key) => errors.value[key] || '';

const rotationOptions = computed(() => {
    const items = Array.isArray(props.rotations) ? props.rotations : (props.rotations?.data || []);
    return items.map(r => ({ value: r.id, label: r.name }));
});

const selectedRotation = computed(() => {
    const items = Array.isArray(props.rotations) ? props.rotations : (props.rotations?.data || []);
    const rid = Number(form.rotation_id);
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

const filteredEmployees = computed(() => employees.value);

const selectedIds = computed(() => selectedEmployees.value.map(e => e.id));

const allFilteredSelected = computed(() => {
    return filteredEmployees.value.length > 0 &&
        filteredEmployees.value.every(e => selectedIds.value.includes(e.id));
});

const selectedCount = computed(() => selectedEmployees.value.length);

const employeeColumns = computed(() => [
    { key: 'employee_code', label: t('shifts.employee_code') },
    { key: 'name', label: t('shifts.employee_name') },
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

function removeEmployee(empId) {
    selectedEmployees.value = selectedEmployees.value.filter(e => e.id !== empId);
    form.employee_ids = selectedEmployees.value.map(e => e.id);
}

function clearAll() {
    selectedEmployees.value = [];
    form.employee_ids = [];
}

function onSelectionChange(ids) {
    const visibleEmployeeIds = new Set(filteredEmployees.value.map((employee) => employee.id));
    const retainedEmployees = selectedEmployees.value.filter(
        (employee) => !visibleEmployeeIds.has(employee.id),
    );
    const selectedVisibleEmployees = employees.value.filter(
        (employee) => visibleEmployeeIds.has(employee.id) && ids.includes(employee.id),
    );

    selectedEmployees.value = [...retainedEmployees, ...selectedVisibleEmployees];
    form.employee_ids = selectedEmployees.value.map((employee) => employee.id);
}

function fetchEmployees() {
    const search = searchQuery.value.trim();

    if (!form.rotation_id || search.length < 2) {
        employeeSearchController?.abort();
        employees.value = [];
        loadingEmployees.value = false;
        return;
    }

    employeeSearchController?.abort();
    const controller = new AbortController();
    employeeSearchController = controller;
    loadingEmployees.value = true;
    const params = new URLSearchParams({ search });
    if (form.department_id) params.set('department_id', form.department_id);

    fetch(route('rotations.search-employees') + '?' + params.toString(), {
        headers: { Accept: 'application/json' },
        signal: controller.signal,
    })
        .then(r => r.json())
        .then(data => {
            employees.value = data.employees || [];
        })
        .catch((error) => {
            if (error.name !== 'AbortError') {
                employees.value = [];
            }
        })
        .finally(() => {
            if (employeeSearchController === controller && !controller.signal.aborted) {
                loadingEmployees.value = false;
            }
        });
}

function scheduleEmployeeSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(fetchEmployees, 300);
}

function submit() {
    processing.value = true;
    errors.value = {};
    generalError.value = '';

    router.post(route('rotations.assign.bulk'), {
        employee_ids: form.employee_ids,
        rotation_id: form.rotation_id ? Number(form.rotation_id) : form.rotation_id,
        rotation_group_id: form.rotation_group_id ? Number(form.rotation_group_id) : form.rotation_group_id,
        start_date: form.start_date,
        end_date: form.end_date || '',
        department_id: form.department_id,
    }, {
        preserveScroll: true,
        onError: (err) => {
            errors.value = err;
            const firstKey = Object.keys(err)[0];
            if (firstKey) {
                generalError.value = Array.isArray(err[firstKey]) ? err[firstKey][0] : err[firstKey];
            }
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

watch(() => form.rotation_id, (val) => {
    form.rotation_group_id = '';
    selectedEmployees.value = [];
    form.employee_ids = [];
    if (val) {
        scheduleEmployeeSearch();
    } else {
        employees.value = [];
    }
}, { immediate: true });

watch(() => form.department_id, () => {
    scheduleEmployeeSearch();
});

watch(searchQuery, scheduleEmployeeSearch);

onBeforeUnmount(() => {
    clearTimeout(searchTimeout);
    employeeSearchController?.abort();
});


usePageTitle(t('shifts.assign_rotation'));
</script>

<template>
    <Head :title="t('shifts.assign_rotation')" />
    
        <PageHeader
            :title="t('shifts.assign_rotation')"
            :description="t('shifts.rotation_assign_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.assign.manage')">{{ t('shifts.manage_assignments') }}</Button>
                <Button variant="secondary" :href="route('rotations.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" dismissible class="mb-4" />

        <div class="max-w-5xl">
            <form class="space-y-6" @submit.prevent="submit">
                <ErrorSummary :errors="errors" />

                <div v-if="generalError" class="p-3 bg-mistral-danger/10 border border-mistral-danger/20 rounded-md text-[13px] text-mistral-danger">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    {{ generalError }}
                </div>

                <FormSection :title="t('shifts.assignment_info')" :description="t('shifts.rotation_assign_description')">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSelect
                            v-model="form.rotation_id"
                            name="rotation_id"
                            :label="t('shifts.rotation')"
                            :options="rotationOptions"
                            :placeholder="t('shifts.select_rotation')"
                            :error="errorFor('rotation_id')"
                            required
                        />

                        <FormSelect
                            v-model="form.rotation_group_id"
                            name="rotation_group_id"
                            :label="t('shifts.rotation_group')"
                            :options="groupOptions"
                            :placeholder="t('shifts.select_rotation_group')"
                            :error="errorFor('rotation_group_id')"
                            required
                        />

                        <FormSelect
                            v-model="form.department_id"
                            name="department_id"
                            :label="t('shifts.filter_by_department')"
                            :options="[{ value: '', label: t('shifts.all_departments') }, ...departmentOptions]"
                            :placeholder="t('shifts.select_department')"
                        />

                        <FormInput
                            v-model="form.start_date"
                            name="start_date"
                            :label="t('shifts.start_date')"
                            type="date"
                            :error="errorFor('start_date')"
                            required
                        />
                    </div>

                    <div v-if="selectedRotation" class="mt-4 p-4 bg-mistral-surface rounded-md">
                        <div class="grid grid-cols-3 gap-3 text-[12px]">
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
                        </div>
                    </div>
                </FormSection>

                <FormSection :title="t('shifts.select_employees_from_table')">
                    <div class="flex items-center gap-2 mb-4">
                        <Badge v-if="selectedCount > 0" :text="`${selectedCount} ${t('shifts.selected')}`" variant="active" />
                        <Button v-if="selectedCount > 0" type="button" variant="ghost" size="sm" icon="fas fa-trash" @click="clearAll">
                            {{ t('shifts.unselect_all') }}
                        </Button>
                        <div class="w-64 me-auto">
                            <FormInput
                                v-model="searchQuery"
                                :placeholder="t('shifts.search_employee_placeholder')"
                                icon="fas fa-search"
                            />
                        </div>
                    </div>

                    <div v-if="loadingEmployees" class="p-8 text-center">
                        <i class="fas fa-spinner fa-spin text-mistral-primary text-xl"></i>
                        <p class="text-[13px] text-mistral-muted mt-2">{{ t('common.loading') }}...</p>
                    </div>

                    <div v-else-if="searchQuery.trim().length < 2 && form.rotation_id" class="p-8 text-center text-[13px] text-mistral-muted">
                        {{ t('shifts.search_employee_to_begin') }}
                    </div>

                    <div v-else-if="filteredEmployees.length === 0 && form.rotation_id" class="p-8 text-center text-[13px] text-mistral-muted">
                        {{ t('shifts.no_employees_found') }}
                    </div>

                    <div v-else-if="!form.rotation_id" class="p-8 text-center text-[13px] text-mistral-muted">
                        {{ t('shifts.select_rotation_first') }}
                    </div>

                    <div v-else class="border border-mistral-hairline rounded-md max-h-[400px] overflow-y-auto">
                        <DataTable
                            :columns="employeeColumns"
                            :data="employeesData"
                            :selectable="true"
                            :selected-ids="selectedIds"
                            :enable-search="false"
                            :enable-filters="false"
                            :enable-pagination="false"
                            :enable-export="false"
                            :enable-density="false"
                            :enable-column-visibility="false"
                            storage-key="rotation-assign"
                            @selection-change="onSelectionChange"
                        />
                    </div>
                </FormSection>

                <FormSection v-if="selectedCount > 0" :title="t('shifts.selected_employees')" :count="selectedCount">
                    <div class="flex flex-wrap gap-2 p-3 bg-mistral-surface rounded-md min-h-[48px]">
                        <span
                            v-for="emp in selectedEmployees"
                            :key="emp.id"
                            class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-mistral-primary text-white text-[13px]"
                        >
                            <i class="fas fa-user text-[10px]"></i>
                            {{ emp.name }}
                            <button
                                type="button"
                                class="inline-flex items-center justify-center w-4 h-4 rounded-full text-white/70 hover:text-white hover:bg-white/20 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-1"
                                :aria-label="t('common.remove')"
                                @click="removeEmployee(emp.id)"
                            >
                                <i class="fas fa-times text-[9px]" aria-hidden="true"></i>
                            </button>
                        </span>
                    </div>
                </FormSection>

                <p v-if="errorFor('employee_ids')" class="text-[12px] text-mistral-danger">
                    {{ errors.employee_ids }}
                </p>

                <FormActions
                    :save-label="`${t('shifts.assign_employee')} (${selectedCount})`"
                    :cancel-label="t('common.cancel')"
                    :cancel-href="route('rotations.index')"
                    :saving="processing"
                />
            </form>
        </div>
    </template>
