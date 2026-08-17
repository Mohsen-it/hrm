<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Badge, Button, DataTable, FormCheckbox, FormModal, PageHeader } from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    users: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const selectedUser = ref(null)
const showForm = ref(false)
const form = useForm({ roles: [] })

const columns = computed(() => [
    { key: 'name', label: t('users.name') },
    { key: 'employee_code', label: t('users.employee_code') },
    { key: 'email', label: t('users.email') },
    { key: 'roles', label: t('users.roles') },
    { key: 'actions', label: t('common.actions'), cellClass: 'w-[130px] text-center' },
])

function onSearch(search) {
    router.get(route('users.role-assignments'), { ...props.filters, search, page: 1 }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function openAssignment(user) {
    selectedUser.value = user
    form.roles = [...user.roles]
    form.clearErrors()
    showForm.value = true
}

function saveAssignment() {
    form.put(route('users.role-assignments.update', selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false },
    })
}


usePageTitle(t('menu.role_assignments'));
</script>

<template>
    
        <Head :title="t('menu.role_assignments')" />

        <PageHeader :title="t('menu.role_assignments')" :description="t('users.roles_section')" />

        <DataTable
            :columns="columns"
            :data="users"
            :filters="filters"
            route-name="users.role-assignments"
            :only="['users']"
            storage-key="user-role-assignments"
            @search="onSearch"
        >
            <template #cell-roles="{ row }">
                <div v-if="row.roles.length" class="flex flex-wrap gap-1">
                    <Badge v-for="role in row.roles" :key="role" :text="role" variant="info" />
                </div>
                <span v-else class="text-mistral-stone">{{ t('users.no_roles') }}</span>
            </template>

            <template #cell-actions="{ row }">
                <Button variant="primary" size="sm" icon="fas fa-user-shield" @click="openAssignment(row)">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </DataTable>

        <FormModal v-model="showForm" :title="t('menu.role_assignments')" size="sm">
            <div class="mb-5 rounded-xl border border-mistral-hairline-soft bg-mistral-surface/50 px-4 py-3">
                <p class="text-[12px] font-medium text-mistral-steel">{{ t('users.name') }}</p>
                <p class="mt-0.5 text-sm font-semibold text-mistral-ink">{{ selectedUser?.name }}</p>
            </div>

            <div>
                <p class="mb-2 text-sm font-semibold text-mistral-ink">{{ t('users.roles') }}</p>
                <div class="max-h-72 space-y-2 overflow-y-auto rounded-xl border border-mistral-hairline-soft bg-white p-3">
                    <FormCheckbox
                        v-for="role in roles"
                        :key="role.id"
                        v-model="form.roles"
                        :value="role.name"
                        :label="role.name"
                    />
                    <p v-if="roles.length === 0" class="py-3 text-center text-sm text-mistral-stone">
                        {{ t('users.no_roles') }}
                    </p>
                </div>
                <p v-if="form.errors.roles" class="mt-2 text-[12px] text-mistral-danger" role="alert">
                    {{ form.errors.roles }}
                </p>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showForm = false">{{ t('common.cancel') }}</Button>
                <Button variant="primary" icon="fas fa-save" :loading="form.processing" @click="saveAssignment">
                    {{ t('common.save') }}
                </Button>
            </template>
        </FormModal>
    </template>
