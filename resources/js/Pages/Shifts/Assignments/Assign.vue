<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { ref, reactive, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { PageHeader, Button, Card, FormInput, FormSelect, FormSearchableSelect, FormDatepicker, ErrorSummary, FormSection, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    categories: { type: Object, default: () => ({ data: [] }) },
    employees: { type: Array, default: () => [] },
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

// Unified searchable employee select (same component as Vacations requests).
const employeeOptions = computed(() =>
    (props.employees || []).map((emp) => ({
        value: emp.id,
        label: emp.employee_code ? `${emp.employee_code} - ${emp.first_name || ''} ${emp.last_name || ''}`.trim() : (emp.name || ''),
    })),
);

const categoryOptions = computed(() =>
    (props.categories?.data || props.categories || []).map((c) => ({ value: c.id, label: c.name })),
);

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


usePageTitle(t('shifts.assign_employee'));
</script>

<template>
    <Head :title="t('shifts.assign_employee')" />
    
        <PageHeader
            :title="t('shifts.assign_employee')"
            :description="t('shifts.assignments_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('shift-assignments.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('shifts.assignment_info')" :description="t('shifts.assignments_description')">
                <div class="max-w-2xl">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSearchableSelect
                            v-model="form.employee_id"
                            :label="t('shifts.employee')"
                            name="employee_id"
                            :options="employeeOptions"
                            :placeholder="t('shifts.search_employee_placeholder')"
                            :search-placeholder="t('shifts.search_employee_placeholder')"
                            required
                            :error="errorFor('employee_id')"
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

                        <FormDatepicker
                            v-model="form.end_date"
                            name="end_date"
                            :label="t('shifts.end_date_optional')"
                            :error="errorFor('end_date')"
                        />
                    </div>
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('shift-assignments.index')"
                :saving="processing"
            />
        </form>
    </template>
