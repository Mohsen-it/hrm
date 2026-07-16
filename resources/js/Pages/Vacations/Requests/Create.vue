<script setup>

import { computed, reactive, ref } from 'vue';

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

const props = defineProps({

    users: { type: Array, default: () => [] },

    types: { type: Array, default: () => [] },

});

const form = reactive({

    user_id: '',

    vacation_type_id: '',

    start_date: '',

    end_date: '',

    reason: '',

});

const errors = ref({});

const processing = ref(false);

const userOptions = computed(() =>

    (props.users || []).map((u) => ({

        value: u.id,

        label: u.employee_code ? `${u.employee_code} - ${u.name}` : u.name,

    })),

);

const typeOptions = computed(() =>

    (props.types || []).map((type) => ({

        value: type.id,

        label: type.code ? `${type.code} - ${type.name_ar}` : type.name_ar,

    })),

);

const errorFor = (key) => errors.value[key] || '';

function submit() {

    processing.value = true;

    errors.value = {};

    router.post(route('vacations.requests.store'), form, {

        preserveScroll: true,

        onError: (err) => { errors.value = err;

 },

        onFinish: () => { processing.value = false;

 },

    });

}

</script>

<template>

    <AppLayout :title="t('vacations.new_request')">

        <PageHeader :title="t('vacations.new_request')" :description="t('vacations.requests_description')">

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('vacations.requests.index')">{{ t('common.back') }}</Button>

            
</template>

        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <FormSelect

                    v-model="form.user_id"

                    :label="t('vacations.employee')"

                    name="user_id"

                    :options="userOptions"

                    required

                    :error="errorFor('user_id')"

                />

                <FormSelect

                    v-model="form.vacation_type_id"

                    :label="t('vacations.vacation_type')"

                    name="vacation_type_id"

                    :options="typeOptions"

                    required

                    :error="errorFor('vacation_type_id')"

                />

                <FormInput

                    v-model="form.start_date"

                    :label="t('vacations.start_date')"

                    name="start_date"

                    type="date"

                    required

                    :error="errorFor('start_date')"

                />

                <FormInput

                    v-model="form.end_date"

                    :label="t('vacations.end_date')"

                    name="end_date"

                    type="date"

                    required

                    :error="errorFor('end_date')"

                />

            </div>

            <div class="mt-4">

                <FormTextarea

                    v-model="form.reason"

                    :label="t('vacations.reason')"

                    name="reason"

                    :rows="4"

                    :error="errorFor('reason')"

                />

            </div>

            <div class="mt-6 flex items-center justify-start gap-2">

                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">{{ t('common.save') }}</Button>

                <Button variant="secondary" :href="route('vacations.requests.index')">{{ t('common.cancel') }}</Button>

            </div>

        </Card>

    </AppLayout>

</template>
