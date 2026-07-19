<script setup>
import { reactive, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    companies: { type: Array, default: () => [] },
});

const form = reactive({
    company_id: '',
    code: '',
    name_ar: '',
    name_en: '',
    zone_type: 'geographic',
    city: '',
    region: '',
    country: '',
    latitude: '',
    longitude: '',
    radius_meters: '',
    description: '',
    is_active: true,
});

const errors = ref({});
const processing = ref(false);

const companyOptions = computed(() => [{ value: '', label: '—' }, ...(props.companies || []).map((c) => ({ value: c.id, label: c.company_name }))]);

const zoneTypeOptions = [
    { value: 'geographic', label: t('zones.zone_type_geographic') },
    { value: 'operational', label: t('zones.zone_type_operational') },
    { value: 'security', label: t('zones.zone_type_security') },
    { value: 'sales', label: t('zones.zone_type_sales') },
    { value: 'logistics', label: t('zones.zone_type_logistics') },
];

const statusOptions = [
    { value: true, label: t('common.active') },
    { value: false, label: t('common.inactive') },
];

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('zones.store'), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('zones.add_zone')">
        <PageHeader :title="t('zones.add_zone')" :description="t('zones.create_description')">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('zones.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('common.basic_info')" icon="fas fa-info-circle" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect v-model="form.company_id" :label="t('companies.company_name')" name="company_id" :options="companyOptions" :error="errorFor('company_id')" autofocus />
                    <FormInput v-model="form.code" :label="t('zones.code')" name="code" required :error="errorFor('code')" />
                    <FormInput v-model="form.name_ar" :label="t('zones.name_ar')" name="name_ar" required :error="errorFor('name_ar')" />
                    <FormInput v-model="form.name_en" :label="t('zones.name_en')" name="name_en" :error="errorFor('name_en')" />
                    <FormSelect v-model="form.zone_type" :label="t('zones.zone_type')" name="zone_type" :options="zoneTypeOptions" :error="errorFor('zone_type')" />
                </div>
            </FormSection>

            <FormSection :title="t('zones.location')" icon="fas fa-map-marker-alt" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput v-model="form.country" :label="t('zones.country')" name="country" :error="errorFor('country')" />
                    <FormInput v-model="form.region" :label="t('zones.region')" name="region" :error="errorFor('region')" />
                    <FormInput v-model="form.city" :label="t('zones.city')" name="city" :error="errorFor('city')" />
                    <FormInput v-model="form.latitude" :label="t('zones.latitude')" name="latitude" type="number" step="any" :error="errorFor('latitude')" />
                    <FormInput v-model="form.longitude" :label="t('zones.longitude')" name="longitude" type="number" step="any" :error="errorFor('longitude')" />
                    <FormInput v-model="form.radius_meters" :label="t('zones.radius_meters')" name="radius_meters" type="number" min="0" :error="errorFor('radius_meters')" />
                </div>
            </FormSection>

            <FormSection :title="t('common.settings')" icon="fas fa-cog" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect v-model="form.is_active" :label="t('common.status')" name="is_active" :options="statusOptions" :error="errorFor('is_active')" />
                </div>
            </FormSection>

            <FormSection :title="t('common.additional')" icon="fas fa-plus-circle" :collapsible="true" :default-open="true">
                <FormTextarea v-model="form.description" :label="t('zones.description')" name="description" :rows="3" :error="errorFor('description')" />
            </FormSection>

            <FormActions :save-label="t('common.save')" :cancel-label="t('common.cancel')" :cancel-href="route('zones.index')" :saving="processing" />
        </form>
    </AppLayout>
</template>
