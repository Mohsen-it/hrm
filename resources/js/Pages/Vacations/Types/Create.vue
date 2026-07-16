<script setup>
import { reactive, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormTextarea from '@/Components/ui/FormTextarea.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = reactive({
    code: '',
    name_ar: '',
    name_en: '',
    default_days_per_year: 21,
    max_days_per_request: 30,
    advance_notice_days: 7,
    is_paid: true,
    requires_approval: true,
    carry_over: false,
    max_carry_over_days: 0,
    color: '#2563eb',
    description: '',
    is_active: true,
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
    router.post(route('vacations.types.store'), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('vacations.add_type')">
        <PageHeader :title="t('vacations.add_type')" :description="t('vacations.create_type_description')">
            <template #actions>
                <Button variant="secondary" :href="route('vacations.types.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="card p-6" @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput v-model="form.code" :label="t('vacations.type_code')" name="code" required :error="errorFor('code')" />
                <FormInput v-model="form.name_ar" :label="t('vacations.type_name_ar')" name="name_ar" required :error="errorFor('name_ar')" />
                <FormInput v-model="form.name_en" :label="t('vacations.type_name_en')" name="name_en" :error="errorFor('name_en')" />
                <FormInput v-model="form.default_days_per_year" :label="t('vacations.days_per_year')" name="default_days_per_year" type="number" required :error="errorFor('default_days_per_year')" />
                <FormInput v-model="form.max_days_per_request" :label="t('vacations.max_days_per_request')" name="max_days_per_request" type="number" :error="errorFor('max_days_per_request')" />
                <FormInput v-model="form.advance_notice_days" :label="t('vacations.advance_notice_days')" name="advance_notice_days" type="number" :error="errorFor('advance_notice_days')" />
                <FormInput v-model="form.color" :label="t('vacations.color')" name="color" type="color" :error="errorFor('color')" />
                <FormSelect v-model="form.is_paid" :label="t('vacations.is_paid')" name="is_paid" :options="yesNoOptions" :error="errorFor('is_paid')" />
                <FormSelect v-model="form.requires_approval" :label="t('vacations.requires_approval')" name="requires_approval" :options="yesNoOptions" :error="errorFor('requires_approval')" />
                <FormSelect v-model="form.is_active" :label="t('common.status')" name="is_active" :options="yesNoOptions" :error="errorFor('is_active')" />
            </div>

            <div class="mt-4">
                <FormTextarea v-model="form.description" :label="t('vacations.description')" name="description" :rows="3" :error="errorFor('description')" />
            </div>

            <div class="mt-6 flex items-center justify-start gap-2">
                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                    {{ t('common.save') }}
                </Button>
                <Button variant="secondary" :href="route('vacations.types.index')">{{ t('common.cancel') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
