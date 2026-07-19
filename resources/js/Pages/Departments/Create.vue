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
    parents: { type: Array, default: () => [] },
});

const form = reactive({
    company_id: '',
    branch_id: '',
    parent_id: '',
    manager_id: '',
    department_code: '',
    department_name: '',
    description: '',
    phone: '',
    email: '',
    location: '',
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

const parentOptions = computed(() => [
    { value: '', label: t('departments.select_parent') },
    ...props.parents.map((p) => ({ value: p.id, label: p.department_name })),
]);

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    const payload = { ...form };
    if (payload.parent_id === '' || payload.parent_id === null) delete payload.parent_id;
    if (payload.manager_id === '' || payload.manager_id === null) delete payload.manager_id;
    router.post(route('departments.store'), payload, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('departments.add_new')">
        <PageHeader
            :title="t('departments.add_new')"
            :description="t('departments.create_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('departments.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <ErrorSummary :errors="errors" />

        <form class="space-y-6" @submit.prevent="submit">
            <FormSection
                :title="t('departments.basic_info')"
                icon="fas fa-sitemap"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        v-model="form.company_id"
                        :label="t('departments.company')"
                        name="company_id"
                        :options="companyOptions"
                        :placeholder="t('departments.select_company')"
                        required
                        autofocus
                        :error="errorFor('company_id')"
                    />
                    <FormSelect
                        v-model="form.branch_id"
                        :label="t('departments.branch')"
                        name="branch_id"
                        :options="branchOptions"
                        :placeholder="t('departments.select_branch')"
                        required
                        :error="errorFor('branch_id')"
                    />
                    <FormInput
                        v-model="form.department_code"
                        :label="t('departments.code')"
                        name="department_code"
                        required
                        :error="errorFor('department_code')"
                    />
                    <FormInput
                        v-model="form.department_name"
                        :label="t('departments.name')"
                        name="department_name"
                        required
                        :error="errorFor('department_name')"
                    />
                    <FormSelect
                        v-model="form.parent_id"
                        :label="t('departments.parent')"
                        name="parent_id"
                        :options="parentOptions"
                        :error="errorFor('parent_id')"
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
                :title="t('departments.contact_info')"
                icon="fas fa-address-card"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.email"
                        :label="t('departments.email')"
                        name="email"
                        type="email"
                        :error="errorFor('email')"
                    />
                    <FormInput
                        v-model="form.phone"
                        :label="t('departments.phone')"
                        name="phone"
                        :error="errorFor('phone')"
                    />
                    <FormInput
                        v-model="form.location"
                        :label="t('departments.location')"
                        name="location"
                        :error="errorFor('location')"
                    />
                    <FormInput
                        v-model="form.manager_id"
                        :label="t('departments.manager')"
                        name="manager_id"
                        type="number"
                        :error="errorFor('manager_id')"
                    />
                </div>
            </FormSection>

            <FormSection
                :title="t('departments.description')"
                icon="fas fa-align-left"
                :collapsible="true"
                :default-open="false"
            >
                <FormTextarea
                    v-model="form.description"
                    :label="t('departments.description')"
                    name="description"
                    :rows="3"
                    :error="errorFor('description')"
                />
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('departments.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
