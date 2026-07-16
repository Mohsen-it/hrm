<script setup>

import { computed } from 'vue'

;

import { Link } from '@inertiajs/vue3'

;

import AppLayout from '@/Layouts/AppLayout.vue'

;

import PageHeader from '@/Components/ui/PageHeader.vue'

;

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import Badge from '@/Components/ui/Badge.vue'

;

import { useTranslations } from '@/composables/useTranslations'

;

const { t } = useTranslations()

;

const props = defineProps({

    branch: { type: Object, required: true },

})

;

const fields = computed(() => [

    { label: t('branches.code'), value: props.branch.branch_code },

    { label: t('branches.name'), value: props.branch.branch_name },

    { label: t('branches.company'), value: props.branch.company?.company_name || '���' },

    { label: t('branches.email'), value: props.branch.email || '���' },

    { label: t('branches.phone'), value: props.branch.phone || '���' },

    { label: t('branches.manager_name'), value: props.branch.manager_name || '���' },

    { label: t('branches.manager_phone'), value: props.branch.manager_phone || '���' },

    { label: t('branches.city'), value: props.branch.city || '���' },

    { label: t('branches.state'), value: props.branch.state || '���' },

    { label: t('branches.country'), value: props.branch.country || '���' },

    { label: t('branches.postal_code'), value: props.branch.postal_code || '���' },

    { label: t('branches.address'), value: props.branch.address || '���' },

    { label: t('branches.address2'), value: props.branch.address2 || '���' },

    { label: t('branches.description'), value: props.branch.description || '���' },

])

;

</script>

<template>

    <AppLayout :title="t('branches.view_branch')">

        <PageHeader

            :title="t('branches.view_branch')"

            :description="branch.branch_name"

        >

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('branches.index')">{{ t('common.back') }}</Button>

                <Button variant="primary" icon="fas fa-edit" :href="route('branches.edit', branch.id)">{{ t('common.edit') }}</Button>

            
</template>

        </PageHeader>

        <div class="card p-6">

            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">

                <div

                    class="w-16 h-16 rounded-md bg-mistral-surface flex items-center justify-center border border-mistral-hairline-soft"

                >

                    <i class="fas fa-code-branch text-[24px] text-mistral-stone"></i>

                </div>

                <div>

                    <h2 class="text-[20px] font-semibold text-mistral-ink">

                        {{ branch.branch_name }}

                    </h2>

                    <p class="text-[13px] text-mistral-steel mt-1">

                        {{ branch.branch_code }}

                    </p>

                    <div class="mt-2 flex items-center gap-2">

                        <Badge v-if="branch.is_main" :text="t('branches.main')" variant="info" />

                        <Badge v-if="branch.status === 1" :text="t('common.active')" variant="active" />

                        <Badge v-else :text="t('common.inactive')" variant="inactive" />

                    </div>

                </div>

            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-right">

                    <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">

                        {{ field.label }}

                    </dt>

                    <dd class="text-[14px] text-mistral-ink mt-1 break-words">

                        {{ field.value }}

                    </dd>

                </div>

            </dl>

        </div>

    </AppLayout>

</template>
