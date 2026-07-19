<script setup>
import { reactive, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    grade: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
});

const form = reactive({
    _method: 'PUT',
    company_id: props.grade.company_id || '',
    grade_code: props.grade.grade_code || '',
    grade_name: props.grade.grade_name || '',
    level: props.grade.level ?? 1,
    min_salary: props.grade.min_salary ?? '',
    max_salary: props.grade.max_salary ?? '',
    description: props.grade.description || '',
    status: Number(props.grade.status ?? 1),
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

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};

    const payload = { ...form };

    if (payload.min_salary === '' || payload.min_salary === null) {
        delete payload.min_salary;
    }

    if (payload.max_salary === '' || payload.max_salary === null) {
        delete payload.max_salary;
    }

    router.post(route('grades.update', props.grade.id), payload, {
        preserveScroll: true,
        onError: (err) => {
            errors.value = err;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :title="t('grades.edit_grade')">
        <PageHeader
            :title="t('grades.edit_grade')"
            :description="grade.grade_name"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('grades.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <ErrorSummary :errors="errors" />

        <form class="space-y-6" @submit.prevent="submit">
            <FormSection
                :title="t('grades.basic_info')"
                icon="fas fa-layer-group"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        v-model="form.company_id"
                        :label="t('grades.company')"
                        name="company_id"
                        :options="companyOptions"
                        :placeholder="t('grades.select_company')"
                        required
                        :error="errorFor('company_id')"
                    />
                    <FormInput
                        v-model="form.grade_code"
                        :label="t('grades.code')"
                        name="grade_code"
                        required
                        :error="errorFor('grade_code')"
                    />
                    <FormInput
                        v-model="form.grade_name"
                        :label="t('grades.name')"
                        name="grade_name"
                        required
                        autofocus
                        :error="errorFor('grade_name')"
                    />
                    <FormInput
                        v-model="form.level"
                        :label="t('grades.level')"
                        name="level"
                        type="number"
                        min="1"
                        required
                        :error="errorFor('level')"
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
                :title="t('grades.salary_range')"
                icon="fas fa-coins"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.min_salary"
                        :label="t('grades.min_salary')"
                        name="min_salary"
                        type="number"
                        step="0.01"
                        :error="errorFor('min_salary')"
                    />
                    <FormInput
                        v-model="form.max_salary"
                        :label="t('grades.max_salary')"
                        name="max_salary"
                        type="number"
                        step="0.01"
                        :error="errorFor('max_salary')"
                    />
                </div>
            </FormSection>

            <FormSection
                :title="t('grades.description')"
                icon="fas fa-align-left"
                :collapsible="true"
                :default-open="false"
            >
                <FormTextarea
                    v-model="form.description"
                    :label="t('grades.description')"
                    name="description"
                    :rows="3"
                    :error="errorFor('description')"
                />
            </FormSection>

            <FormActions
                :save-label="t('common.update')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('grades.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
