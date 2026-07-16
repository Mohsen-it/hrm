<script setup>

import { reactive, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormTextarea from '@/Components/ui/FormTextarea.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormCheckbox from '@/Components/ui/FormCheckbox.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = reactive({
    company_code: '',
    company_name: '',
    email: '',
    phone: '',
    address: '',
    address2: '',
    city: '',
    country: '',
    state: '',
    postal_code: '',
    website: '',
    description: '',
    established_date: '',
    tax_number: '',
    commercial_number: '',
    is_default: false,
    status: 1,
    logo: null,
});

const errors = ref({});
const processing = ref(false);

const statusOptions = [
    { value: 1, label: t('common.active') },
    { value: 0, label: t('common.inactive') },
];

const errorFor = (key) => errors.value[key] || '';

function onFileChange(e) {
    form.logo = e.target.files[0] || null;
}

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('companies.store'), form, {
        forceFormData: true,
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

    <AppLayout :title="t('companies.add_new')">
        <PageHeader
            :title="t('companies.add_new')"
            :description="t('companies.create_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('companies.index')">
                    {{ t('common.back') }}
                </Button>

            
</template>

        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                    v-model="form.company_code"
                    :label="t('companies.code')"
                    name="company_code"
                    required
                    :error="errorFor('company_code')"
                />
                <FormInput
                    v-model="form.company_name"
                    :label="t('companies.name')"
                    name="company_name"
                    required
                    :error="errorFor('company_name')"
                />
                <FormInput
                    v-model="form.email"
                    :label="t('companies.email')"
                    name="email"
                    type="email"
                    :error="errorFor('email')"
                />
                <FormInput
                    v-model="form.phone"
                    :label="t('companies.phone')"
                    name="phone"
                    :error="errorFor('phone')"
                />
                <FormInput
                    v-model="form.website"
                    :label="t('companies.website')"
                    name="website"
                    type="url"
                    :error="errorFor('website')"
                />
                <FormInput
                    v-model="form.established_date"
                    :label="t('companies.established_date')"
                    name="established_date"
                    type="date"
                    :error="errorFor('established_date')"
                />
                <FormInput
                    v-model="form.tax_number"
                    :label="t('companies.tax_number')"
                    name="tax_number"
                    :error="errorFor('tax_number')"
                />
                <FormInput
                    v-model="form.commercial_number"
                    :label="t('companies.commercial_number')"
                    name="commercial_number"
                    :error="errorFor('commercial_number')"
                />
                <FormInput
                    v-model="form.address"
                    :label="t('companies.address')"
                    name="address"
                    :error="errorFor('address')"
                />
                <FormInput
                    v-model="form.address2"
                    :label="t('companies.address2')"
                    name="address2"
                    :error="errorFor('address2')"
                />
                <FormInput
                    v-model="form.city"
                    :label="t('companies.city')"
                    name="city"
                    :error="errorFor('city')"
                />
                <FormInput
                    v-model="form.state"
                    :label="t('companies.state')"
                    name="state"
                    :error="errorFor('state')"
                />
                <FormInput
                    v-model="form.postal_code"
                    :label="t('companies.postal_code')"
                    name="postal_code"
                    :error="errorFor('postal_code')"
                />
                <FormInput
                    v-model="form.country"
                    :label="t('companies.country')"
                    name="country"
                    :error="errorFor('country')"
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

            <div class="mt-4">
                <FormTextarea
                    v-model="form.description"
                    :label="t('companies.description')"
                    name="description"
                    :rows="3"
                    :error="errorFor('description')"
                />
            </div>

            <div class="mt-4">
                <label class="block text-[13px] font-semibold text-mistral-steel mb-1">
                    {{ t('companies.logo') }}
                </label>

                <input
                    type="file"
                    accept="image/*"
                    class="form-input py-2"
                    @change="onFileChange"
                />
                <p v-if="errorFor('logo')" class="text-[11px] text-mistral-danger mt-1">
                    {{ errorFor('logo') }}
                </p>

            </div>

            <div class="mt-4">
                <FormCheckbox
                    v-model="form.is_default"
                    :label="t('companies.set_as_default')"
                />
            </div>

            <div class="mt-6 flex items-center justify-start gap-2">
                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                    {{ t('common.save') }}
                </Button>

                <Button variant="secondary" :href="route('companies.index')">
                    {{ t('common.cancel') }}
                </Button>

            </div>

        </Card>

    </AppLayout>

</template>
