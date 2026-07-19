<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSelect, Badge, DataTable, Tabs, ErrorSummary, FormSection, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    rotations: { type: Array, default: () => [] },
    preselected_rotation_id: { type: Number, default: null },
    preselected_group_id: { type: Number, default: null },
});

const activeTab = ref('single');

const tabs = [
    { value: 'single', label: t('shifts.assign_employee') },
    { value: 'bulk', label: t('shifts.bulk_assign') },
];

function onTabChange(tab) {
    if (tab.value === 'bulk') {
        router.visit(route('rotations.assign.bulk-page'), { preserveState: false });
    }
}

const form = reactive({
    employee_id: '',
    rotation_id: props.preselected_rotation_id || '',
    rotation_group_id: props.preselected_group_id || '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
});

const errors = ref({});
const processing = ref(false);
const generalError = ref('');
const loadingEmployees = ref(false);
const employees = ref([]);
const selectedEmployeeId = ref(null);
const searchQuery = ref('');

const errorFor = (key) => errors.value[key || ''];

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

const filteredEmployees = computed(() => {
    if (!searchQuery.value) return employees.value;
    const q = searchQuery.value.toLowerCase();
    return employees.value.filter(e =>
        e.name?.toLowerCase().includes(q) ||
        e.employee_code?.toLowerCase().includes(q) ||
        e.first_name?.toLowerCase().includes(q) ||
        e.last_name?.toLowerCase().includes(q)
    );
});

const employeeColumns = computed(() => [
    { key: 'radio', label: '', width: '48px', headerClass: 'text-center' },
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

const selectedEmployee = computed(() => {
    if (!selectedEmployeeId.value) return null;
    return employees.value.find(e => e.id === selectedEmployeeId.value) || null;
});

function selectEmployee(emp) {
    selectedEmployeeId.value = emp.id;
    form.employee_id = emp.id;
}

function fetchEmployees() {
    if (!form.rotation_id) {
        employees.value = [];
        return;
    }
    loadingEmployees.value = true;
    fetch(route('rotations.search-employees', { search: '' }), {
        headers: { Accept: 'application/json' },
    })
        .then(r => r.json())
        .then(data => {
            employees.value = data.employees || [];
        })
        .catch(() => {
            employees.value = [];
        })
        .finally(() => {
            loadingEmployees.value = false;
        });
}

function submit() {
    processing.value = true;
    errors.value = {};
    generalError.value = '';

    router.post(route('rotations.assign.store'), {
        ...form,
        employee_id: form.employee_id ? Number(form.employee_id) : form.employee_id,
        rotation_id: form.rotation_id ? Number(form.rotation_id) : form.rotation_id,
        rotation_group_id: form.rotation_group_id ? Number(form.rotation_group_id) : form.rotation_group_id,
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

watch(() => form.rotation_id, (val, oldVal) => {
    form.rotation_group_id = '';
    selectedEmployeeId.value = null;
    form.employee_id = '';
    if (val) {
        fetchEmployees();
    } else {
        employees.value = [];
    }
}, { immediate: true });
</script>

<template>
    <Head :title="t('shifts.assign_rotation')" />
    <AppLayout :title="t('shifts.assign_rotation')">
        <PageHeader
            :title="t('shifts.assign_rotation')"
            :description="t('shifts.rotation_assign_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.assign.manage')">{{ t('shifts.manage_assignments') }}</Button>
                <Button variant="secondary" :href="route('rotations.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="max-w-5xl">
            <Tabs :tabs="tabs" v-model="activeTab" @change="onTabChange" />

            <form class="space-y-6 mt-4" @submit.prevent="submit">
                <ErrorSummary :errors="errors" />

                <div v-if="generalError" class="p-3 bg-mistral-danger/10 border border-mistral-danger/20 rounded-md text-[13px] text-mistral-danger">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    {{ generalError }}
                </div>

                <FormSection :title="t('shifts.assignment_info')" :description="t('shifts.rotation_assign_description')">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormSelect
                            v-model="form.rotation_id"
                            :label="t('shifts.rotation')"
                            name="rotation_id"
                            :options="rotationOptions"
                            :error="errorFor('rotation_id')"
                            required
                        />

                        <FormSelect
                            v-model="form.rotation_group_id"
                            :label="t('shifts.rotation_group')"
                            name="rotation_group_id"
                            :options="groupOptions"
                            :error="errorFor('rotation_group_id')"
                            required
                        />

                        <FormInput
                            v-model="form.start_date"
                            :label="t('shifts.start_date')"
                            name="start_date"
                            type="date"
                            :error="errorFor('start_date')"
                            required
                        />

                        <FormInput
                            v-model="form.end_date"
                            :label="t('shifts.end_date_optional')"
                            name="end_date"
                            type="date"
                            :error="errorFor('end_date')"
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

                <FormSection :title="t('shifts.select_employee_from_table')">
                    <template #actions>
                        <div class="w-64">
                            <FormInput
                                v-model="searchQuery"
                                :placeholder="t('shifts.search_employee_placeholder')"
                                icon="fas fa-search"
                            />
                        </div>
                    </template>

                    <div v-if="loadingEmployees" class="p-8 text-center">
                        <i class="fas fa-spinner fa-spin text-mistral-primary text-xl"></i>
                        <p class="text-[13px] text-mistral-muted mt-2">{{ t('common.loading') }}...</p>
                    </div>

                    <div v-else-if="filteredEmployees.length === 0" class="p-8 text-center text-[13px] text-mistral-muted">
                        {{ t('shifts.no_employees_found') }}
                    </div>

                    <DataTable
                        v-else
                        :columns="employeeColumns"
                        :data="employeesData"
                        :selectable="false"
                        row-clickable
                        :enable-search="false"
                        :enable-filters="false"
                        :enable-pagination="false"
                        :enable-export="false"
                        :enable-density="false"
                        :enable-column-visibility="false"
                        storage-key="rotation-assign"
                        @row-click="selectEmployee"
                    >
                        <template #cell-radio="{ row }">
                            <input
                                type="radio"
                                :checked="selectedEmployeeId === row.id"
                                @click.stop="selectEmployee(row)"
                                class="w-4 h-4 border-mistral-hairline text-mistral-primary focus:ring-mistral-primary"
                            />
                        </template>
                    </DataTable>

                    <div v-if="selectedEmployee" class="mt-3 p-3 bg-mistral-success/10 border border-mistral-success/20 rounded-md flex items-center gap-2">
                        <i class="fas fa-check-circle text-mistral-success"></i>
                        <span class="text-[13px] text-mistral-success font-medium">{{ selectedEmployee.name }}</span>
                        <span class="text-[12px] text-mistral-success">({{ selectedEmployee.employee_code }})</span>
                    </div>

                    <span v-if="errorFor('employee_id')" class="text-[12px] text-mistral-danger mt-1 block">{{ errorFor('employee_id') }}</span>
                </FormSection>

                <FormActions
                    :save-label="t('shifts.assign_employee')"
                    :cancel-label="t('common.cancel')"
                    :cancel-href="route('rotations.index')"
                    :saving="processing"
                />
            </form>
        </div>
    </AppLayout>
</template>
