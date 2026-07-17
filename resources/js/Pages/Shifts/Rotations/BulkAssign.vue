<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

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
    department_id: '',
});

const errors = ref({});
const processing = ref(false);
const generalError = ref('');

const errorFor = (key) => errors.value[key] || '';

const employeeSearch = ref('');
const employees = ref([]);
const selectedEmployees = ref([]);
const searching = ref(false);
let searchTimer = null;

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

watch(() => form.rotation_id, () => {
    form.rotation_group_id = '';
});

function searchEmployees() {
    if (searchTimer) clearTimeout(searchTimer);
    if (employeeSearch.value.length < 2) {
        employees.value = [];
        return;
    }
    searchTimer = setTimeout(async () => {
        searching.value = true;
        try {
            const params = new URLSearchParams({ search: employeeSearch.value });
            if (form.department_id) params.set('department_id', form.department_id);
            const response = await fetch(
                route('rotations.search-employees') + '?' + params.toString(),
                { headers: { Accept: 'application/json' } },
            );
            const data = await response.json();
            employees.value = (data.employees || []).filter(
                emp => !selectedEmployees.value.find(s => s.id === emp.id),
            );
        } catch (e) {
            employees.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
}

function addEmployee(emp) {
    if (!selectedEmployees.value.find(e => e.id === emp.id)) {
        selectedEmployees.value.push(emp);
        form.employee_ids = selectedEmployees.value.map(e => e.id);
    }
    employeeSearch.value = '';
    employees.value = [];
}

function removeEmployee(empId) {
    selectedEmployees.value = selectedEmployees.value.filter(e => e.id !== empId);
    form.employee_ids = selectedEmployees.value.map(e => e.id);
}

function addAllFromSearch() {
    employees.value.forEach(emp => {
        if (!selectedEmployees.value.find(s => s.id === emp.id)) {
            selectedEmployees.value.push(emp);
        }
    });
    form.employee_ids = selectedEmployees.value.map(e => e.id);
    employees.value = [];
    employeeSearch.value = '';
}

function clearAll() {
    selectedEmployees.value = [];
    form.employee_ids = [];
}

function submit() {
    processing.value = true;
    errors.value = {};
    generalError.value = '';

    router.post(route('rotations.assign.bulk'), form, {
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

watch(employeeSearch, searchEmployees);
</script>

<template>
    <Head :title="t('shifts.bulk_assign')" />
    <AppLayout :title="t('shifts.bulk_assign')">
        <PageHeader
            :title="t('shifts.bulk_assign')"
            :description="t('shifts.rotation_bulk_assign_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.assign')">{{ t('shifts.assign_employee') }}</Button>
                <Button variant="secondary" :href="route('rotations.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit" class="max-w-3xl">
            <div v-if="generalError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-[13px] text-red-700">
                <i class="fas fa-exclamation-circle mr-1"></i>
                {{ generalError }}
            </div>

            <section>
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.assignment_info') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        v-model="form.department_id"
                        name="department_id"
                        :label="t('shifts.filter_by_department')"
                        :options="departmentOptions"
                        :placeholder="t('shifts.select_department')"
                        :error="errorFor('department_id')"
                    />

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
                    <h4 class="text-[13px] font-medium text-mistral-ink mb-2">{{ t('shifts.rotation_preview') }}</h4>
                    <div class="grid grid-cols-3 gap-3 text-[12px]">
                        <div>
                            <span class="text-mistral-slate">{{ t('shifts.work_pattern') }}:</span>
                            <span class="font-mono font-medium ml-1">{{ selectedRotation.work_days_count }}+{{ selectedRotation.rest_days_count }}</span>
                        </div>
                        <div>
                            <span class="text-mistral-slate">{{ t('shifts.cycle_length') }}:</span>
                            <span class="font-medium ml-1">{{ selectedRotation.cycle_length }} {{ t('shifts.days') }}</span>
                        </div>
                        <div>
                            <span class="text-mistral-slate">{{ t('shifts.groups_count') }}:</span>
                            <span class="font-medium ml-1">{{ selectedRotation.number_of_groups }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[14px] text-mistral-ink font-medium">{{ t('shifts.search_employees') }}</h3>
                    <Button v-if="employees.length > 0" type="button" variant="ghost" size="sm" icon="fas fa-check-double" @click="addAllFromSearch">
                        {{ t('shifts.select_all') }} ({{ employees.length }})
                    </Button>
                </div>

                <div class="relative mb-4">
                    <FormInput
                        v-model="employeeSearch"
                        :placeholder="t('shifts.search_employee_placeholder')"
                    />
                    <div
                        v-if="searching"
                        class="absolute top-full left-0 right-0 mt-1 p-2 bg-mistral-canvas border border-mistral-hairline rounded-md text-[13px] text-mistral-muted z-10"
                    >
                        <i class="fas fa-spinner fa-spin"></i> {{ t('common.search') }}...
                    </div>
                    <div
                        v-else-if="employees.length > 0"
                        class="absolute top-full left-0 right-0 mt-1 bg-mistral-canvas border border-mistral-hairline rounded-md shadow-level-2 max-h-[240px] overflow-y-auto z-10"
                    >
                        <button
                            v-for="emp in employees"
                            :key="emp.id"
                            type="button"
                            class="w-full text-right flex items-center justify-between p-2 hover:bg-mistral-surface border-b border-mistral-hairline-soft last:border-b-0"
                            @click="addEmployee(emp)"
                        >
                            <div class="flex flex-col">
                                <span class="text-[14px] text-mistral-ink">{{ emp.first_name }} {{ emp.last_name }}</span>
                                <span class="text-[12px] text-mistral-muted">{{ emp.employee_code }}</span>
                            </div>
                            <i class="fas fa-plus text-mistral-primary text-[12px]"></i>
                        </button>
                    </div>
                    <div
                        v-else-if="employeeSearch.length >= 2 && !searching"
                        class="absolute top-full left-0 right-0 mt-1 p-2 bg-mistral-canvas border border-mistral-hairline rounded-md text-[13px] text-mistral-muted z-10"
                    >
                        {{ t('shifts.no_employees_found') }}
                    </div>
                </div>
            </section>

            <section class="mt-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[14px] text-mistral-ink font-medium">{{ t('shifts.selected_employees') }} ({{ selectedEmployees.length }})</h3>
                    <Button v-if="selectedEmployees.length > 0" type="button" variant="ghost" size="sm" icon="fas fa-trash" @click="clearAll">
                        {{ t('shifts.unselect_all') }}
                    </Button>
                </div>

                <div
                    v-if="selectedEmployees.length > 0"
                    class="flex flex-wrap gap-2 p-3 bg-mistral-surface rounded-md min-h-[60px]"
                >
                    <span
                        v-for="emp in selectedEmployees"
                        :key="emp.id"
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-mistral-primary text-white text-[13px]"
                    >
                        <i class="fas fa-user text-[10px]"></i>
                        {{ emp.first_name }} {{ emp.last_name }}
                        <button
                            type="button"
                            class="text-white hover:text-mistral-danger"
                            @click="removeEmployee(emp.id)"
                        >
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </span>
                </div>

                <EmptyState
                    v-else
                    icon="fas fa-users"
                    :title="t('shifts.no_employees_selected')"
                    :description="t('shifts.search_employee_placeholder')"
                />

                <p v-if="errorFor('employee_ids')" class="text-[12px] text-mistral-danger mt-2">
                    {{ errors.employee_ids }}
                </p>
            </section>

            <div class="mt-6 flex items-center justify-start gap-2">
                <Button
                    type="submit"
                    variant="primary"
                    :loading="processing"
                    :disabled="selectedEmployees.length === 0 || !form.rotation_id || !form.rotation_group_id"
                    icon="fas fa-users"
                >
                    {{ t('shifts.bulk_assign') }} ({{ selectedEmployees.length }})
                </Button>
                <Button variant="secondary" :href="route('rotations.index')">
                    {{ t('common.cancel') }}
                </Button>
            </div>
        </Card>
    </AppLayout>
</template>
