<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, FormInput, FormTextarea, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    type: { type: Object, required: true },
});

const form = reactive({
    _method: 'PUT',
    code: props.type.code || '',
    name_ar: props.type.name_ar || '',
    name_en: props.type.name_en || '',
    default_days_per_year: props.type.default_days_per_year || 21,
    max_days_per_request: props.type.max_days_per_request || 30,
    advance_notice_days: props.type.advance_notice_days || 7,
    is_paid: !!props.type.is_paid,
    requires_approval: !!props.type.requires_approval,
    carry_over: !!props.type.carry_over,
    max_carry_over_days: props.type.max_carry_over_days || 0,
    color: props.type.color || '#2563eb',
    description: props.type.description || '',
    is_active: !!props.type.is_active,
});

const errors = ref({});
const processing = ref(false);

const yesNoOptions = [
    { value: true, label: t('common.yes') },
    { value: false, label: t('common.no') },
];

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('vacations.types.update', props.type.id), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('vacations.edit_type')">
        <PageHeader :title="t('vacations.edit_type')" :description="type.name_ar">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('vacations.types.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('vacations.type_info')" icon="fas fa-tag" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput v-model="form.code" :label="t('vacations.type_code')" name="code" required :error="errorFor('code')" autofocus />
                    <FormInput v-model="form.name_ar" :label="t('vacations.type_name_ar')" name="name_ar" required :error="errorFor('name_ar')" />
                    <FormInput v-model="form.name_en" :label="t('vacations.type_name_en')" name="name_en" :error="errorFor('name_en')" />
                </div>
            </FormSection>

            <FormSection :title="t('vacations.quotas')" icon="fas fa-calendar-check" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput v-model="form.default_days_per_year" :label="t('vacations.days_per_year')" name="default_days_per_year" type="number" required :error="errorFor('default_days_per_year')" />
                    <FormInput v-model="form.max_days_per_request" :label="t('vacations.max_days_per_request')" name="max_days_per_request" type="number" :error="errorFor('max_days_per_request')" />
                    <FormInput v-model="form.advance_notice_days" :label="t('vacations.advance_notice_days')" name="advance_notice_days" type="number" :error="errorFor('advance_notice_days')" />
                </div>
            </FormSection>

            <FormSection :title="t('vacations.settings')" icon="fas fa-cog" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect v-model="form.is_paid" :label="t('vacations.is_paid')" name="is_paid" :options="yesNoOptions" :error="errorFor('is_paid')" />
                    <FormSelect v-model="form.requires_approval" :label="t('vacations.requires_approval')" name="requires_approval" :options="yesNoOptions" :error="errorFor('requires_approval')" />
                    <FormSelect v-model="form.carry_over" :label="t('vacations.carry_over')" name="carry_over" :options="yesNoOptions" :error="errorFor('carry_over')" />
                    <FormInput v-model="form.max_carry_over_days" :label="t('vacations.max_carry_over_days')" name="max_carry_over_days" type="number" :error="errorFor('max_carry_over_days')" />
                    <FormInput v-model="form.color" :label="t('vacations.color')" name="color" type="color" :error="errorFor('color')" />
                    <FormSelect v-model="form.is_active" :label="t('common.status')" name="is_active" :options="yesNoOptions" :error="errorFor('is_active')" />
                </div>
            </FormSection>

            <FormSection :title="t('vacations.additional')" icon="fas fa-align-left" :collapsible="true" :default-open="true">
                <FormTextarea v-model="form.description" :label="t('vacations.description')" name="description" :rows="3" :error="errorFor('description')" />
            </FormSection>

            <FormActions :save-label="t('common.update')" :cancel-label="t('common.cancel')" :cancel-href="route('vacations.types.index')" :saving="processing" />
        </form>
    </AppLayout>
</template>
