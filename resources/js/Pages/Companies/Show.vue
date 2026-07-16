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

    company: { type: Object, required: true },

})

;

const fields = computed(() => [

    { label: t('companies.code'), value: props.company.company_code },

    { label: t('companies.name'), value: props.company.company_name },

    { label: t('companies.email'), value: props.company.email || '���' },

    { label: t('companies.phone'), value: props.company.phone || '���' },

    { label: t('companies.website'), value: props.company.website || '���' },

    { label: t('companies.established_date'), value: props.company.established_date || '���' },

    { label: t('companies.tax_number'), value: props.company.tax_number || '���' },

    { label: t('companies.commercial_number'), value: props.company.commercial_number || '���' },

    { label: t('companies.city'), value: props.company.city || '���' },

    { label: t('companies.state'), value: props.company.state || '���' },

    { label: t('companies.country'), value: props.company.country || '���' },

    { label: t('companies.postal_code'), value: props.company.postal_code || '���' },

    { label: t('companies.address'), value: props.company.address || '���' },

    { label: t('companies.address2'), value: props.company.address2 || '���' },

    { label: t('companies.description'), value: props.company.description || '���' },

])

;

</script>

<template>

    <AppLayout :title="t('companies.view_company')">

        <PageHeader

            :title="t('companies.view_company')"

            :description="company.company_name"

        >

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('companies.index')">{{ t('common.back') }}</Button>

                <Button variant="primary" icon="fas fa-edit" :href="route('companies.edit', company.id)">{{ t('common.edit') }}</Button>

            
</template>

        </PageHeader>

        <div class="card p-6">

            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">

                <div

                    class="w-20 h-20 rounded-md bg-mistral-surface flex items-center justify-center overflow-hidden border border-mistral-hairline-soft"

                >

                    <img

                        v-if="company.logo_url"

                        :src="company.logo_url"

                        :alt="company.company_name"

                        class="w-full h-full object-cover"

                    />

                    <i v-else class="fas fa-building text-[32px] text-mistral-stone"></i>

                </div>

                <div>

                    <h2 class="text-[20px] font-semibold text-mistral-ink">

                        {{ company.company_name }}

                    </h2>

                    <p class="text-[13px] text-mistral-steel mt-1">

                        {{ company.company_code }}

                    </p>

                    <div class="mt-2 flex items-center gap-2">

                        <Badge

                            v-if="company.is_default"

                            :text="t('companies.default')"

                            variant="info"

                        />

                        <Badge

                            v-if="company.status === 1"

                            :text="t('common.active')"

                            variant="active"

                        />

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
