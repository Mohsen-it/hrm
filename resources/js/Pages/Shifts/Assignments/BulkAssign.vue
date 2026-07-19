<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSelect, FormDatepicker, EmptyState, ErrorSummary, FormSection, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    categories: { type: Object, default: () => ({ data: [] }) },
    departments: { type: Array, default: () => [] },
    preselected_category_id: { type: Number, default: null },
});

const form = reactive({
    employee_ids: [],
    shift_category_id: props.preselected_category_id || '',
    start_date: new Date().toISOString().slice(0, 10),
    department_id: '',
});

const errors = ref({});
const processing = ref(false);

const errorFor = (key) => errors.value[key] || '';

const employeeSearch = ref('');
const employees = ref([]);
const selectedEmployees = ref([]);
const searching = ref(false);
let searchTimer = null;

const categoryOptions = computed(() =>
    (props.categories?.data || props.categories || []).map((c) => ({ value: c.id, label: c.name })),
);

const departmentOptions = computed(() =>
    (props.departments || []).map((d) => ({ value: d.id, label: d.department_name })),
);

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
                route('shift-assignments.search-employees') + '?' + params.toString(),
                { headers: { 'Accept': 'application/json' } },
            );
            const data = await response.json();
            employees.value = (data.employees || []).filter(
                (emp) => !selectedEmployees.value.find((s) => s.id === emp.id),
            );
        } catch (e) {
            employees.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
}

function addEmployee(emp) {
    if (!selectedEmployees.value.find((e) => e.id === emp.id)) {
        selectedEmployees.value.push(emp);
        form.employee_ids = selectedEmployees.value.map((e) => e.id);
    }
    employeeSearch.value = '';
    employees.value = [];
}

function removeEmployee(empId) {
    selectedEmployees.value = selectedEmployees.value.filter((e) => e.id !== empId);
    form.employee_ids = selectedEmployees.value.map((e) => e.id);
}

function addAllFromSearch() {
    employees.value.forEach((emp) => {
        if (!selectedEmployees.value.find((s) => s.id === emp.id)) {
            selectedEmployees.value.push(emp);
        }
    });
    form.employee_ids = selectedEmployees.value.map((e) => e.id);
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
    router.post(route('shift-assignments.bulk-assign'), form, {
        preserveScroll: true,
        onError: (err) => {
            errors.value = err;
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
            :description="t('shifts.bulk_assign_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('shift-assignments.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('shifts.assignment_info')" :description="t('shifts.bulk_assign_description')">
                <div class="max-w-3xl">
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
                            v-model="form.shift_category_id"
                            name="shift_category_id"
                            :label="t('shifts.shift_category')"
                            :options="categoryOptions"
                            :placeholder="t('shifts.select_category')"
                            :error="errorFor('shift_category_id')"
                            required
                        />
                        <FormDatepicker
                            v-model="form.start_date"
                            name="start_date"
                            :label="t('shifts.start_date')"
                            :error="errorFor('start_date')"
                            required
                        />
                    </div>
                </div>
            </FormSection>

            <FormSection :title="t('shifts.search_employees')">
                <template #actions>
                    <Button v-if="employees.length > 0" type="button" variant="ghost" size="sm" icon="fas fa-check-double" @click="addAllFromSearch">
                        {{ t('shifts.select_all') }} ({{ employees.length }})
                    </Button>
                </template>

                <div class="max-w-3xl">
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
                                class="w-full text-end flex items-center justify-between p-2 hover:bg-mistral-surface border-b border-mistral-hairline-soft last:border-b-0"
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
                </div>
            </FormSection>

            <FormSection :title="t('shifts.selected_employees')" :count="selectedEmployees.length">
                <template #actions>
                    <Button v-if="selectedEmployees.length > 0" type="button" variant="ghost" size="sm" icon="fas fa-trash" @click="clearAll">
                        {{ t('shifts.unselect_all') }}
                    </Button>
                </template>

                <div class="max-w-3xl">
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
                                class="inline-flex items-center justify-center w-4 h-4 rounded-full text-white/70 hover:text-white hover:bg-white/20 transition-colors cursor-pointer focus-visible:outline-2 focus-visible:outline-white focus-visible:outline-offset-1"
                                :aria-label="t('common.remove')"
                                @click="removeEmployee(emp.id)"
                            >
                                <i class="fas fa-times text-[9px]" aria-hidden="true"></i>
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
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('shift-assignments.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
