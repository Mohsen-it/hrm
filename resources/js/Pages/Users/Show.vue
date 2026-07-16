<script setup>

import { computed } from 'vue';

import { Link, usePage } from '@inertiajs/vue3';

import AppLayout from '@/Layouts/AppLayout.vue';

import PageHeader from '@/Components/ui/PageHeader.vue';

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import Badge from '@/Components/ui/Badge.vue';

import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const page = usePage();

const props = defineProps({

    user: { type: Object, required: true },

});

const personalFields = computed(() => [

    { label: t('users.employee_code'), value: props.user.employee_code || '���' },

    { label: t('users.name'), value: props.user.name || '���' },

    { label: t('users.first_name'), value: props.user.first_name || '���' },

    { label: t('users.last_name'), value: props.user.last_name || '���' },

    { label: t('users.email'), value: props.user.email || '���' },

    { label: t('users.national_id'), value: props.user.national_id || '���' },

    { label: t('users.phone'), value: props.user.phone || '���' },

    { label: t('users.phone2'), value: props.user.phone2 || '���' },

    { label: t('users.date_of_birth'), value: props.user.date_of_birth || '���' },

    { label: t('users.gender'), value: props.user.gender ? t(`users.gender_${props.user.gender}`) : '���' },

    { label: t('users.marital_status'), value: props.user.marital_status ? t(`users.marital_${props.user.marital_status}`) : '���' },

    { label: t('users.nationality'), value: props.user.nationality || '���' },

]);

const employmentFields = computed(() => [

    { label: t('users.hire_date'), value: props.user.hire_date || '���' },

    { label: t('users.termination_date'), value: props.user.termination_date || '���' },

    { label: t('users.employment_type'), value: props.user.employment_type ? t(`users.employment_${props.user.employment_type}`) : '���' },

    { label: t('users.job_title'), value: props.user.job_title || '���' },

    { label: t('users.work_location'), value: props.user.work_location || '���' },

    { label: t('users.last_login_at'), value: props.user.last_login_at || '���' },

]);

const orgFields = computed(() => [

    { label: t('users.company'), value: props.user.company?.company_name || '���' },

    { label: t('users.branch'), value: props.user.branch?.branch_name || '���' },

    { label: t('users.department'), value: props.user.department?.department_name || '���' },

    { label: t('users.position'), value: props.user.position?.position_name || '���' },

    { label: t('users.grade'), value: props.user.grade?.grade_name || '���' },

    { label: t('users.shift'), value: props.user.shift?.shift_name || '���' },

    { label: t('users.manager'), value: props.user.manager?.name || '���' },

]);

const contactFields = computed(() => [

    { label: t('users.address'), value: props.user.address || '���' },

    { label: t('users.city'), value: props.user.city || '���' },

    { label: t('users.state'), value: props.user.state || '���' },

    { label: t('users.country'), value: props.user.country || '���' },

    { label: t('users.postal_code'), value: props.user.postal_code || '���' },

]);

const emergencyFields = computed(() => [

    { label: t('users.emergency_contact_name'), value: props.user.emergency_contact_name || '���' },

    { label: t('users.emergency_contact_phone'), value: props.user.emergency_contact_phone || '���' },

    { label: t('users.emergency_contact_relation'), value: props.user.emergency_contact_relation || '���' },

]);

const bankingFields = computed(() => [

    { label: t('users.bank_name'), value: props.user.bank_name || '���' },

    { label: t('users.bank_account_number'), value: props.user.bank_account_number || '���' },

    { label: t('users.iban'), value: props.user.iban || '���' },

]);

const flashSuccess = computed(() => page.props.flash?.success);

</script>

<template>

    <AppLayout :title="t('users.view_user')">

        <PageHeader

            :title="t('users.view_user')"

            :description="user.name"

        >

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('users.index')">{{ t('common.back') }}</Button>

                <Button variant="primary" icon="fas fa-edit" :href="route('users.edit', user.id)">{{ t('common.edit') }}</Button>

                <Button variant="secondary" icon="fas fa-clock" :href="route('users.shifts', user.id)">
                    {{ t('users.manage_shifts') }}
                </Button>

                <Button variant="secondary" icon="fas fa-fingerprint" :href="route('users.fingerprints', user.id)">
                    {{ t('users.manage_fingerprints') }}
                </Button>

            
</template>

        </PageHeader>

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">

            <i class="fas fa-check-circle"></i>

            <span>{{ flashSuccess }}</span>

        </div>

        <!-- Header card -->

        <div class="card p-6 mb-4">

            <div class="flex items-center gap-4 pb-6 border-b border-mistral-hairline-soft">

                <div

                    class="w-20 h-20 rounded-full bg-mistral-surface flex items-center justify-center overflow-hidden border border-mistral-hairline-soft"

                >

                    <img

                        v-if="user.avatar_url"

                        :src="user.avatar_url"

                        :alt="user.name"

                        class="w-full h-full object-cover"

                    />

                    <i v-else class="fas fa-user text-[32px] text-mistral-stone"></i>

                </div>

                <div class="flex-1">

                    <h2 class="text-[20px] font-semibold text-mistral-ink">

                        {{ user.name }}

                    </h2>

                    <p v-if="user.employee_code" class="text-[13px] text-mistral-steel mt-1">

                        {{ user.employee_code }}

                    </p>

                    <p v-if="user.email" class="text-[13px] text-mistral-steel mt-1">

                        {{ user.email }}

                    </p>

                    <div class="mt-2 flex items-center gap-2 flex-wrap">

                        <Badge

                            v-if="user.status === 1"

                            :text="t('common.active')"

                            variant="active"

                        />

                        <Badge v-else :text="t('common.inactive')" variant="inactive" />

                        <Badge

                            v-if="user.is_super_admin"

                            :text="t('users.is_super_admin')"

                            variant="info"

                        />

                        <Badge

                            v-if="user.is_locked"

                            :text="t('users.is_locked')"

                            variant="warning"

                        />

                    </div>

                </div>

            </div>

            <!-- Roles & Permissions -->

            <div class="pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>

                    <h3 class="text-[14px] font-semibold text-mistral-steel mb-2">

                        {{ t('users.roles_section') }}

                    </h3>

                    <div v-if="user.roles && user.roles.length" class="flex flex-wrap gap-2">

                        <span

                            v-for="r in user.roles"

                            :key="r.id"

                            class="px-2 py-1 rounded-md bg-[var(--color-primary-soft)] text-mistral-primary text-[12px]"

                        >

                            {{ r.name }}

                        </span>

                    </div>

                    <p v-else class="text-[13px] text-mistral-stone">

                        {{ t('users.no_roles') }}

                    </p>

                </div>

                <div>

                    <h3 class="text-[14px] font-semibold text-mistral-steel mb-2">

                        {{ t('users.all_permissions') }}

                    </h3>

                    <p

                        v-if="user.all_permissions && user.all_permissions.length"

                        class="text-[12px] text-mistral-ink break-words"

                    >

                        {{ user.all_permissions.join(' ��� ') }}

                    </p>

                    <p v-else class="text-[13px] text-mistral-stone">

                        {{ t('users.no_permissions') }}

                    </p>

                </div>

            </div>

        </div>

        <!-- Personal Information -->

        <div class="card p-6 mb-4">

            <h3 class="text-[16px] font-semibold text-mistral-ink mb-4">

                {{ t('users.personal_info') }}

            </h3>

            <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in personalFields" :key="idx" class="flex flex-col text-right">

                    <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">

                        {{ field.label }}

                    </dt>

                    <dd class="text-[14px] text-mistral-ink mt-1 break-words">

                        {{ field.value }}

                    </dd>

                </div>

            </dl>

        </div>

        <!-- Employment -->

        <div class="card p-6 mb-4">

            <h3 class="text-[16px] font-semibold text-mistral-ink mb-4">

                {{ t('users.employment_info') }}

            </h3>

            <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in employmentFields" :key="idx" class="flex flex-col text-right">

                    <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">

                        {{ field.label }}

                    </dt>

                    <dd class="text-[14px] text-mistral-ink mt-1 break-words">

                        {{ field.value }}

                    </dd>

                </div>

            </dl>

        </div>

        <!-- Organizational -->

        <div class="card p-6 mb-4">

            <h3 class="text-[16px] font-semibold text-mistral-ink mb-4">

                {{ t('users.organizational_info') }}

            </h3>

            <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in orgFields" :key="idx" class="flex flex-col text-right">

                    <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">

                        {{ field.label }}

                    </dt>

                    <dd class="text-[14px] text-mistral-ink mt-1 break-words">

                        {{ field.value }}

                    </dd>

                </div>

            </dl>

        </div>

        <!-- Contact -->

        <div class="card p-6 mb-4">

            <h3 class="text-[16px] font-semibold text-mistral-ink mb-4">

                {{ t('users.contact_info') }}

            </h3>

            <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in contactFields" :key="idx" class="flex flex-col text-right">

                    <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">

                        {{ field.label }}

                    </dt>

                    <dd class="text-[14px] text-mistral-ink mt-1 break-words">

                        {{ field.value }}

                    </dd>

                </div>

            </dl>

        </div>

        <!-- Emergency -->

        <div class="card p-6 mb-4">

            <h3 class="text-[16px] font-semibold text-mistral-ink mb-4">

                {{ t('users.emergency_info') }}

            </h3>

            <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in emergencyFields" :key="idx" class="flex flex-col text-right">

                    <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">

                        {{ field.label }}

                    </dt>

                    <dd class="text-[14px] text-mistral-ink mt-1 break-words">

                        {{ field.value }}

                    </dd>

                </div>

            </dl>

        </div>

        <!-- Banking -->

        <div class="card p-6">

            <h3 class="text-[16px] font-semibold text-mistral-ink mb-4">

                {{ t('users.banking_info') }}

            </h3>

            <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in bankingFields" :key="idx" class="flex flex-col text-right">

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
