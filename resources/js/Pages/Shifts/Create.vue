<script setup>

import { reactive, ref, computed, watch } from 'vue';

import { router, Link } from '@inertiajs/vue3';

import AppLayout from '@/Layouts/AppLayout.vue';

import PageHeader from '@/Components/ui/PageHeader.vue';

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import FormInput from '@/Components/ui/FormInput.vue';

import FormSelect from '@/Components/ui/FormSelect.vue';

import FormTextarea from '@/Components/ui/FormTextarea.vue';

import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({

    companies: { type: Array, default: () => [] },

    branches: { type: Array, default: () => [] },

});

const form = reactive({

    company_id: '',

    branch_id: '',

    shift_code: '',

    shift_name: '',

    start_time: '',

    end_time: '',

    break_minutes: 0,

    grace_minutes: 0,

    working_hours: '',

    work_days: [],

    description: '',

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

    props.branches

        .filter((b) => !form.company_id || Number(b.company_id) === Number(form.company_id))

        .map((b) => ({ value: b.id, label: b.branch_name })),

);

const dayOptions = computed(() => [

    { value: 0, label: t('shifts.sunday') },

    { value: 1, label: t('shifts.monday') },

    { value: 2, label: t('shifts.tuesday') },

    { value: 3, label: t('shifts.wednesday') },

    { value: 4, label: t('shifts.thursday') },

    { value: 5, label: t('shifts.friday') },

    { value: 6, label: t('shifts.saturday') },

]);

const workDays = computed({

    get: () => Array.isArray(form.work_days) ? form.work_days : [],

    set: () => {},

});

watch(

    () => form.company_id,

    () => {

        if (form.branch_id) {

           
const stillValid = props.branches.some(

                (b) => Number(b.id) === Number(form.branch_id)

                    && (!form.company_id || Number(b.company_id) === Number(form.company_id)),

            );

            if (!stillValid) {

                form.branch_id = '';

            }

        }

    },

);

function toggleDay(day) {

   
const current = Array.isArray(form.work_days) ? [...form.work_days] : [];

   
const idx = current.indexOf(day);

    if (idx === -1) {

        current.push(day);

    } else {

        current.splice(idx, 1);

    }

    current.sort((a, b) => a - b);

    form.work_days = current;

}

function isDaySelected(day) {

   
return Array.isArray(form.work_days) && form.work_days.includes(day);

}

const errorFor = (key) => errors.value[key] || '';

function submit() {

    processing.value = true;

    errors.value = {};

   
const payload = { ...form };

    if (payload.break_minutes === '' || payload.break_minutes === null) {

        delete payload.break_minutes;

    }

    if (payload.grace_minutes === '' || payload.grace_minutes === null) {

        delete payload.grace_minutes;

    }

    if (payload.working_hours === '' || payload.working_hours === null) {

        delete payload.working_hours;

    }

    if (Array.isArray(payload.work_days) && payload.work_days.length === 0) {

        delete payload.work_days;

    }

    router.post(route('shifts.store'), payload, {

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

    <AppLayout :title="t('shifts.add_new')">

        <PageHeader

            :title="t('shifts.add_new')"

            :description="t('shifts.create_description')"

        >

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('shifts.index')">{{ t('common.back') }}</Button>

            
</template>

        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <FormSelect

                    v-model="form.company_id"

                    :label="t('shifts.company')"

                    name="company_id"

                    :options="companyOptions"

                    :placeholder="t('shifts.select_company')"

                    required

                    :error="errorFor('company_id')"

                />

                <FormSelect

                    v-model="form.branch_id"

                    :label="t('shifts.branch')"

                    name="branch_id"

                    :options="branchOptions"

                    :placeholder="t('shifts.select_branch')"

                    required

                    :error="errorFor('branch_id')"

                />

                <FormInput

                    v-model="form.shift_code"

                    :label="t('shifts.code')"

                    name="shift_code"

                    required

                    :error="errorFor('shift_code')"

                />

                <FormInput

                    v-model="form.shift_name"

                    :label="t('shifts.name')"

                    name="shift_name"

                    required

                    :error="errorFor('shift_name')"

                />

                <FormInput

                    v-model="form.start_time"

                    :label="t('shifts.start_time')"

                    name="start_time"

                    type="time"

                    required

                    :error="errorFor('start_time')"

                />

                <FormInput

                    v-model="form.end_time"

                    :label="t('shifts.end_time')"

                    name="end_time"

                    type="time"

                    required

                    :error="errorFor('end_time')"

                />

                <FormInput

                    v-model="form.break_minutes"

                    :label="t('shifts.break_minutes')"

                    name="break_minutes"

                    type="number"

                    min="0"

                    :error="errorFor('break_minutes')"

                />

                <FormInput

                    v-model="form.grace_minutes"

                    :label="t('shifts.grace_minutes')"

                    name="grace_minutes"

                    type="number"

                    min="0"

                    :error="errorFor('grace_minutes')"

                />

                <FormInput

                    v-model="form.working_hours"

                    :label="t('shifts.working_hours')"

                    name="working_hours"

                    type="number"

                    step="0.01"

                    :error="errorFor('working_hours')"

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

                <label class="block text-[13px] font-semibold text-mistral-steel mb-2 text-right">

                    {{ t('shifts.work_days') }}

                </label>

                <div class="flex items-center gap-2 flex-wrap">

                    <button

                        v-for="day in dayOptions"

                        :key="day.value"

                        type="button"

                        class="px-3 py-1.5 rounded-md border text-[13px] font-medium transition-colors"

                        :class="isDaySelected(day.value)

                            ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]'

                            : 'bg-mistral-surface text-mistral-steel border-mistral-hairline-soft hover:border-[var(--color-primary)]'"

                        @click="toggleDay(day.value)"

                    >

                        {{ day.label }}

                    </Button>

                </div>

                <p v-if="errorFor('work_days')" class="text-[11px] text-mistral-danger mt-1">

                    {{ errorFor('work_days') }}

                </p>

            </div>

            <div class="mt-4">

                <FormTextarea

                    v-model="form.description"

                    :label="t('shifts.description')"

                    name="description"

                    :rows="3"

                    :error="errorFor('description')"

                />

            </div>

            <div class="mt-6 flex items-center justify-start gap-2">

                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">{{ t('common.save') }}</Button>

                <Button variant="secondary" :href="route('shifts.index')">{{ t('common.cancel') }}</Button>

            </div>

        </Card>

    </AppLayout>

</template>
