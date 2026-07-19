<script setup>
import { reactive, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormCheckbox, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    companies: { type: Array, default: () => [] },
});

const form = reactive({
    company_id: '',
    branch_code: '',
    branch_name: '',
    email: '',
    phone: '',
    address: '',
    address2: '',
    city: '',
    country: '',
    state: '',
    postal_code: '',
    manager_name: '',
    manager_phone: '',
    description: '',
    is_main: false,
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

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('branches.store'), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('branches.add_new')">
        <PageHeader
            :title="t('branches.add_new')"
            :description="t('branches.create_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('branches.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <ErrorSummary :errors="errors" />

        <form class="space-y-6" @submit.prevent="submit">
            <FormSection
                :title="t('branches.basic_info')"
                icon="fas fa-code-branch"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        v-model="form.company_id"
                        :label="t('branches.company')"
                        name="company_id"
                        :options="companyOptions"
                        :placeholder="t('branches.select_company')"
                        required
                        autofocus
                        :error="errorFor('company_id')"
                    />
                    <FormInput
                        v-model="form.branch_code"
                        :label="t('branches.code')"
                        name="branch_code"
                        required
                        :error="errorFor('branch_code')"
                    />
                    <FormInput
                        v-model="form.branch_name"
                        :label="t('branches.name')"
                        name="branch_name"
                        required
                        :error="errorFor('branch_name')"
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
                :title="t('branches.contact_info')"
                icon="fas fa-address-card"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.email"
                        :label="t('branches.email')"
                        name="email"
                        type="email"
                        :error="errorFor('email')"
                    />
                    <FormInput
                        v-model="form.phone"
                        :label="t('branches.phone')"
                        name="phone"
                        :error="errorFor('phone')"
                    />
                    <FormInput
                        v-model="form.manager_name"
                        :label="t('branches.manager_name')"
                        name="manager_name"
                        :error="errorFor('manager_name')"
                    />
                    <FormInput
                        v-model="form.manager_phone"
                        :label="t('branches.manager_phone')"
                        name="manager_phone"
                        :error="errorFor('manager_phone')"
                    />
                </div>
            </FormSection>

            <FormSection
                :title="t('branches.location')"
                icon="fas fa-location-dot"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.address"
                        :label="t('branches.address')"
                        name="address"
                        :error="errorFor('address')"
                    />
                    <FormInput
                        v-model="form.address2"
                        :label="t('branches.address2')"
                        name="address2"
                        :error="errorFor('address2')"
                    />
                    <FormInput
                        v-model="form.city"
                        :label="t('branches.city')"
                        name="city"
                        :error="errorFor('city')"
                    />
                    <FormInput
                        v-model="form.state"
                        :label="t('branches.state')"
                        name="state"
                        :error="errorFor('state')"
                    />
                    <FormInput
                        v-model="form.postal_code"
                        :label="t('branches.postal_code')"
                        name="postal_code"
                        :error="errorFor('postal_code')"
                    />
                    <FormInput
                        v-model="form.country"
                        :label="t('branches.country')"
                        name="country"
                        :error="errorFor('country')"
                    />
                </div>
            </FormSection>

            <FormSection
                :title="t('branches.additional')"
                icon="fas fa-ellipsis"
                :collapsible="true"
                :default-open="false"
            >
                <div class="space-y-4">
                    <FormTextarea
                        v-model="form.description"
                        :label="t('branches.description')"
                        name="description"
                        :rows="3"
                        :error="errorFor('description')"
                    />
                    <FormCheckbox v-model="form.is_main" :label="t('branches.is_main')" />
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('branches.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
