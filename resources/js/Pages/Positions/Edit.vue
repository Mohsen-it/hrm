<script setup>

import { reactive, ref, computed } from 'vue'

;

import { router, Link } from '@inertiajs/vue3'

;

import AppLayout from '@/Layouts/AppLayout.vue'

;

import PageHeader from '@/Components/ui/PageHeader.vue'

;

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import FormInput from '@/Components/ui/FormInput.vue'

;

import FormSelect from '@/Components/ui/FormSelect.vue'

;

import FormTextarea from '@/Components/ui/FormTextarea.vue'

;

import { useTranslations } from '@/composables/useTranslations'

;

const { t } = useTranslations()

;

const props = defineProps({

    position: { type: Object, required: true },

    companies: { type: Array, default: () => [] },

    branches: { type: Array, default: () => [] },

    departments: { type: Array, default: () => [] },

})

;

const form = reactive({

    _method: 'PUT',

    company_id: props.position.company_id || '',

    branch_id: props.position.branch_id || '',

    department_id: props.position.department_id || '',

    position_code: props.position.position_code || '',

    position_name: props.position.position_name || '',

    description: props.position.description || '',

    min_salary: props.position.min_salary ?? '',

    max_salary: props.position.max_salary ?? '',

    requirements: props.position.requirements || '',

    status: Number(props.position.status ?? 1),

})

;

const errors = ref({})

;

const processing = ref(false)

;

const statusOptions = [

    { value: 1, label: t('common.active') },

    { value: 0, label: t('common.inactive') },

]

;

const companyOptions = computed(() =>

    props.companies.map((c) => ({ value: c.id, label: c.company_name })),

)

;

const branchOptions = computed(() =>

    props.branches.map((b) => ({ value: b.id, label: b.branch_name })),

)

;

const departmentOptions = computed(() => [

    { value: '', label: t('positions.select_department') },

    ...props.departments.map((d) => ({ value: d.id, label: d.department_name })),

])

;

const errorFor = (key) => errors.value[key] || ''

;

function submit() {

    processing.value = true

;

    errors.value = {}

;

   
const payload = { ...form }

;

    if (payload.department_id === '' || payload.department_id === null) {

        delete payload.department_id

;

    }

    if (payload.min_salary === '' || payload.min_salary === null) {

        delete payload.min_salary

;

    }

    if (payload.max_salary === '' || payload.max_salary === null) {

        delete payload.max_salary

;

    }

    router.post(route('positions.update', props.position.id), payload, {

        preserveScroll: true,

        onError: (err) => {

            errors.value = err

;

        },

        onFinish: () => {

            processing.value = false

;

        },

    })

;

}

</script>

<template>

    <AppLayout :title="t('positions.edit_position')">

        <PageHeader

            :title="t('positions.edit_position')"

            :description="position.position_name"

        >

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('positions.index')">{{ t('common.back') }}</Button>

            
</template>

        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <FormSelect

                    v-model="form.company_id"

                    :label="t('positions.company')"

                    name="company_id"

                    :options="companyOptions"

                    :placeholder="t('positions.select_company')"

                    required

                    :error="errorFor('company_id')"

                />

                <FormSelect

                    v-model="form.branch_id"

                    :label="t('positions.branch')"

                    name="branch_id"

                    :options="branchOptions"

                    :placeholder="t('positions.select_branch')"

                    required

                    :error="errorFor('branch_id')"

                />

                <FormSelect

                    v-model="form.department_id"

                    :label="t('positions.department')"

                    name="department_id"

                    :options="departmentOptions"

                    :error="errorFor('department_id')"

                />

                <FormInput

                    v-model="form.position_code"

                    :label="t('positions.code')"

                    name="position_code"

                    required

                    :error="errorFor('position_code')"

                />

                <FormInput

                    v-model="form.position_name"

                    :label="t('positions.name')"

                    name="position_name"

                    required

                    :error="errorFor('position_name')"

                />

                <FormInput

                    v-model="form.min_salary"

                    :label="t('positions.min_salary')"

                    name="min_salary"

                    type="number"

                    step="0.01"

                    :error="errorFor('min_salary')"

                />

                <FormInput

                    v-model="form.max_salary"

                    :label="t('positions.max_salary')"

                    name="max_salary"

                    type="number"

                    step="0.01"

                    :error="errorFor('max_salary')"

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

                    :label="t('positions.description')"

                    name="description"

                    :rows="3"

                    :error="errorFor('description')"

                />

            </div>

            <div class="mt-4">

                <FormTextarea

                    v-model="form.requirements"

                    :label="t('positions.requirements')"

                    name="requirements"

                    :rows="3"

                    :error="errorFor('requirements')"

                />

            </div>

            <div class="mt-6 flex items-center justify-start gap-2">

                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">{{ t('common.update') }}</Button>

                <Button variant="secondary" :href="route('positions.index')">{{ t('common.cancel') }}</Button>

            </div>

        </Card>

    </AppLayout>

</template>
