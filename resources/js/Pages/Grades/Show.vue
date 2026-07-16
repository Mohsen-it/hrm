<script setup>

import { computed } from 'vue';

import { Link } from '@inertiajs/vue3';

import AppLayout from '@/Layouts/AppLayout.vue';

import PageHeader from '@/Components/ui/PageHeader.vue';

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import Badge from '@/Components/ui/Badge.vue';

import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({

    grade: { type: Object, required: true },

});

const fields = computed(() => [

    { label: t('grades.code'), value: props.grade.grade_code },

    { label: t('grades.name'), value: props.grade.grade_name },

    { label: t('grades.level'), value: props.grade.level },

    { label: t('grades.company'), value: props.grade.company?.company_name || '���' },

    { label: t('grades.min_salary'), value: props.grade.min_salary ?? '���' },

    { label: t('grades.max_salary'), value: props.grade.max_salary ?? '���' },

    { label: t('grades.description'), value: props.grade.description || '���' },

]);

const employeesCount = computed(() => {

    if (Array.isArray(props.grade.users)) {

       
return props.grade.users.length;

    }

   
return 0;

});

</script>

<template>

    <AppLayout :title="t('grades.view_grade')">

        <PageHeader

            :title="t('grades.view_grade')"

            :description="grade.grade_name"

        >

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('grades.index')">{{ t('common.back') }}</Button>

                <Button variant="primary" icon="fas fa-edit" :href="route('grades.edit', grade.id)">{{ t('common.edit') }}</Button>

            
</template>

        </PageHeader>

        <div class="card p-6">

            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">

                <div

                    class="w-16 h-16 rounded-md bg-mistral-surface flex items-center justify-center border border-mistral-hairline-soft"

                >

                    <i class="fas fa-layer-group text-[24px] text-mistral-stone"></i>

                </div>

                <div class="flex-1">

                    <h2 class="text-[20px] font-semibold text-mistral-ink">

                        {{ grade.grade_name }}

                    </h2>

                    <p class="text-[13px] text-mistral-steel mt-1">

                        {{ grade.grade_code }}

                    </p>

                    <div class="mt-2 flex items-center gap-2 flex-wrap">

                        <Badge v-if="grade.status === 1" :text="t('common.active')" variant="active" />

                        <Badge v-else :text="t('common.inactive')" variant="inactive" />

                        <Badge :text="`${t('grades.employees_count')}: ${employeesCount}`" variant="info" />

                    </div>

                </div>

            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-right">

                    <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">

                        {{ field.label }}

                    </dt>

                    <dd class="text-[14px] text-mistral-ink mt-1 break-words whitespace-pre-line">

                        {{ field.value }}

                    </dd>

                </div>

            </dl>

        </div>

    </AppLayout>

</template>
