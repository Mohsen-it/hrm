<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    zone: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
});

const form = reactive({
    _method: 'PUT',
    company_id: props.zone.company_id ?? '',
    code: props.zone.code || '',
    name_ar: props.zone.name_ar || '',
    name_en: props.zone.name_en || '',
    zone_type: props.zone.zone_type || 'geographic',
    city: props.zone.city || '',
    region: props.zone.region || '',
    country: props.zone.country || '',
    latitude: props.zone.latitude ?? '',
    longitude: props.zone.longitude ?? '',
    radius_meters: props.zone.radius_meters ?? '',
    description: props.zone.description || '',
    is_active: !!props.zone.is_active,
});

const errors = ref({});
const processing = ref(false);

const companyOptions = [{ value: '', label: '—' }, ...(props.companies || []).map((c) => ({ value: c.id, label: c.company_name }))];

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
    router.post(route('zones.update', props.zone.id), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('zones.edit_zone')">
        <PageHeader :title="t('zones.edit_zone')" :description="zone.name_ar">
            <template #actions>
                <Button variant="secondary" :href="route('zones.show', zone.id)">{{ t('common.back') }}</Button>
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

            <FormActions :save-label="t('common.update')" :cancel-label="t('common.cancel')" :cancel-href="route('zones.show', zone.id)" :saving="processing" />
        </form>
    </AppLayout>
</template>
