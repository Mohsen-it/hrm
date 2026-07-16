<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormDatepicker from '@/Components/ui/FormDatepicker.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    categories: { type: Object, default: () => ({ data: [] }) },
    preselected_category_id: { type: Number, default: null },
});

const form = reactive({
    employee_id: '',
    shift_category_id: props.preselected_category_id || '',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
});

const errors = ref({});
const processing = ref(false);

const errorFor = (key) => errors.value[key] || '';

const employeeSearch = ref('');
const employees = ref([]);
const selectedEmployee = ref(null);
const searching = ref(false);
let searchTimer = null;

const categoryOptions = computed(() =>
    (props.categories?.data || props.categories || []).map((c) => ({ value: c.id, label: c.name })),
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
            const response = await fetch(
                route('shift-assignments.search-employees') + '?search=' + encodeURIComponent(employeeSearch.value),
                { headers: { 'Accept': 'application/json' } },
            );
            const data = await response.json();
            employees.value = data.employees || [];
        } catch (e) {
            employees.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
}

function selectEmployee(emp) {
    selectedEmployee.value = emp;
    form.employee_id = emp.id;
    employeeSearch.value = '';
    employees.value = [];
}

function clearSelectedEmployee() {
    selectedEmployee.value = null;
    form.employee_id = '';
}

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('shift-assignments.assign'), form, {
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
    <Head :title="t('shifts.assign_employee')" />
    <AppLayout :title="t('shifts.assign_employee')">
        <PageHeader
            :title="t('shifts.assign_employee')"
            :description="t('shifts.assignments_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('shift-assignments.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit" class="max-w-2xl">
            <section>
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.assignment_info') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] text-mistral-ink mb-1">
                            {{ t('shifts.employee') }} <span class="text-mistral-danger">*</span>
                        </label>

                        <div v-if="selectedEmployee" class="flex items-center justify-between p-2 bg-mistral-surface rounded-md border border-mistral-hairline">
                            <div class="flex flex-col">
                                <span class="text-[14px] font-medium text-mistral-ink">
                                    {{ selectedEmployee.first_name }} {{ selectedEmployee.last_name }}
                                </span>
                                <span class="text-[12px] text-mistral-muted">{{ selectedEmployee.employee_code }}</span>
                            </div>
                            <Button type="button" variant="ghost" size="sm" icon="fas fa-times" @click="clearSelectedEmployee" />
                        </div>

                        <div v-else class="relative">
                            <FormInput
                                v-model="employeeSearch"
                                :placeholder="t('shifts.search_employee_placeholder')"
                                :error="errorFor('employee_id')"
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
                                    @click="selectEmployee(emp)"
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

                    <FormDatepicker
                        v-model="form.end_date"
                        name="end_date"
                        :label="t('shifts.end_date_optional')"
                        :error="errorFor('end_date')"
                    />
                </div>
            </section>

            <div class="mt-6 flex items-center justify-start gap-2">
                <Button type="submit" variant="primary" :loading="processing" :disabled="!form.employee_id || !form.shift_category_id" icon="fas fa-user-check">
                    {{ t('common.save') }}
                </Button>
                <Button variant="secondary" :href="route('shift-assignments.index')">
                    {{ t('common.cancel') }}
                </Button>
            </div>
        </Card>
    </AppLayout>
</template>
