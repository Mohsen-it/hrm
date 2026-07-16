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

    name_ar: '',

    name_en: '',

    code: '',

    is_recurring: false,

    date: '',

    recurring_month: '',

    recurring_day: '',

    category: 'public',

    is_paid: true,

    is_active: true,

    duration_days: 1,

    applies_to_all: true,

    description: '',

});

const errors = ref({});

const processing = ref(false);

const categoryOptions = [

    { value: 'public', label: t('holidays.category_public') },

    { value: 'religious', label: t('holidays.category_religious') },

    { value: 'national', label: t('holidays.category_national') },

    { value: 'company', label: t('holidays.category_company') },

];

const monthOptions = Array.from({ length: 12 }, (_, i) => ({

    value: i + 1,

    label: t('holidays.month_' + (i + 1)),

}));

const dayOptions = Array.from({ length: 31 }, (_, i) => ({

    value: i + 1,

    label: String(i + 1),

}));

const yesNoOptions = [

    { value: true, label: t('common.yes') },

    { value: false, label: t('common.no') },

];

const errorFor = (key) => errors.value[key] || '';

function submit() {

    processing.value = true;

    errors.value = {};

    router.post(route('holidays.store'), form, {

        preserveScroll: true,

        onError: (err) => { errors.value = err;

 },

        onFinish: () => { processing.value = false;

 },

    });

}

</script>

<template>

    <AppLayout :title="t('holidays.add_holiday')">

        <PageHeader :title="t('holidays.add_holiday')" :description="t('holidays.create_description')">

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('holidays.index')">{{ t('common.back') }}</Button>

            
</template>

        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <FormInput v-model="form.name_ar" :label="t('holidays.name_ar')" name="name_ar" required :error="errorFor('name_ar')" />

                <FormInput v-model="form.name_en" :label="t('holidays.name_en')" name="name_en" :error="errorFor('name_en')" />

                <FormInput v-model="form.code" :label="t('holidays.code')" name="code" :error="errorFor('code')" />

                <FormSelect v-model="form.category" :label="t('holidays.category')" name="category" :options="categoryOptions" :error="errorFor('category')" />

                <FormSelect v-model="form.is_recurring" :label="t('holidays.recurring')" name="is_recurring" :options="yesNoOptions" :error="errorFor('is_recurring')" />

                <FormInput v-if="!form.is_recurring" v-model="form.date" :label="t('holidays.date')" name="date" type="date" required :error="errorFor('date')" />

                <FormSelect v-if="form.is_recurring" v-model="form.recurring_month" :label="t('holidays.recurring_month')" name="recurring_month" :options="monthOptions" required :error="errorFor('recurring_month')" />

                <FormSelect v-if="form.is_recurring" v-model="form.recurring_day" :label="t('holidays.recurring_day')" name="recurring_day" :options="dayOptions" required :error="errorFor('recurring_day')" />

                <FormInput v-model="form.duration_days" :label="t('holidays.duration_days')" name="duration_days" type="number" min="1" :error="errorFor('duration_days')" />

                <FormSelect v-model="form.is_paid" :label="t('holidays.is_paid')" name="is_paid" :options="yesNoOptions" :error="errorFor('is_paid')" />

                <FormSelect v-model="form.is_active" :label="t('common.status')" name="is_active" :options="yesNoOptions" :error="errorFor('is_active')" />

            </div>

            <div class="mt-4">

                <FormTextarea v-model="form.description" :label="t('holidays.description')" name="description" :rows="3" :error="errorFor('description')" />

            </div>

            <div class="mt-6 flex items-center justify-start gap-2">

                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">{{ t('common.save') }}</Button>

                <Button variant="secondary" :href="route('holidays.index')">{{ t('common.cancel') }}</Button>

            </div>

        </Card>

    </AppLayout>

</template>
