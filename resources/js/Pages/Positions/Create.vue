<script setup>
import { reactive, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    companies: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const form = reactive({
    company_id: '',
    branch_id: '',
    department_id: '',
    position_code: '',
    position_name: '',
    description: '',
    min_salary: '',
    max_salary: '',
    requirements: '',
    status: 1,
});

const errors = ref({});
const processing = ref(false);

const statusOptions = [
    { value: 1, label: t('common.active') },
    { value: 0, label: t('common.inactive') },
];

const companyOptions = computed(() =>
    props.companies.map((c) => ({ value: c.id, label: c.company_name })),
);

const branchOptions = computed(() =>
    props.branches.map((b) => ({ value: b.id, label: b.branch_name })),
);

const departmentOptions = computed(() => [
    { value: '', label: t('positions.select_department') },
    ...props.departments.map((d) => ({ value: d.id, label: d.department_name })),
]);

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    const payload = { ...form };
    if (payload.department_id === '' || payload.department_id === null) delete payload.department_id;
    if (payload.min_salary === '' || payload.min_salary === null) delete payload.min_salary;
    if (payload.max_salary === '' || payload.max_salary === null) delete payload.max_salary;
    router.post(route('positions.store'), payload, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('positions.add_new')">
        <PageHeader
            :title="t('positions.add_new')"
            :description="t('positions.create_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('positions.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <ErrorSummary :errors="errors" />

        <form class="space-y-6" @submit.prevent="submit">
            <FormSection
                :title="t('positions.basic_info')"
                icon="fas fa-briefcase"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        v-model="form.company_id"
                        :label="t('positions.company')"
                        name="company_id"
                        :options="companyOptions"
                        :placeholder="t('positions.select_company')"
                        required
                        autofocus
                        :error="errorFor('company_id')"
                    />
                    <FormSelect
                        v-model="form.branch_id"
                        :label="t('positions.branch')"
                        name="branch_id"
                        :options="branchOptions"
                        :placeholder="t('positions.select_branch')"
                        required
                        :error="errorFor('branch_id')"
                    />
                    <FormSelect
                        v-model="form.department_id"
                        :label="t('positions.department')"
                        name="department_id"
                        :options="departmentOptions"
                        :error="errorFor('department_id')"
                    />
                    <FormInput
                        v-model="form.position_code"
                        :label="t('positions.code')"
                        name="position_code"
                        required
                        :error="errorFor('position_code')"
                    />
                    <FormInput
                        v-model="form.position_name"
                        :label="t('positions.name')"
                        name="position_name"
                        required
                        :error="errorFor('position_name')"
                    />
                    <FormSelect
                        v-model="form.status"
                        :label="t('common.status')"
                        name="status"
                        :options="statusOptions"
                        required
                        :error="errorFor('status')"
                    />
                </div>
            </FormSection>

            <FormSection
                :title="t('positions.salary_range')"
                icon="fas fa-coins"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.min_salary"
                        :label="t('positions.min_salary')"
                        name="min_salary"
                        type="number"
                        step="0.01"
                        :error="errorFor('min_salary')"
                    />
                    <FormInput
                        v-model="form.max_salary"
                        :label="t('positions.max_salary')"
                        name="max_salary"
                        type="number"
                        step="0.01"
                        :error="errorFor('max_salary')"
                    />
                </div>
            </FormSection>

            <FormSection
                :title="t('positions.details')"
                icon="fas fa-align-left"
                :collapsible="true"
                :default-open="false"
            >
                <div class="space-y-4">
                    <FormTextarea
                        v-model="form.description"
                        :label="t('positions.description')"
                        name="description"
                        :rows="3"
                        :error="errorFor('description')"
                    />
                    <FormTextarea
                        v-model="form.requirements"
                        :label="t('positions.requirements')"
                        name="requirements"
                        :rows="3"
                        :error="errorFor('requirements')"
                    />
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('positions.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
