<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { PageHeader, Button, Badge, DataTable, FormInput, FormSelect, FormCheckbox, FormModal, ConfirmDialog, IconButton } from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    roles: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
})

const showForm = ref(false)
const showConfirm = ref(false)
const editing = ref(null)
const deleting = ref(null)

const form = useForm({
    name: '',
    guard_name: 'web',
    permissions: [],
})

const guardOptions = [
    { value: 'web', label: 'Web' },
    { value: 'api', label: 'API' },
]

const columns = computed(() => [
    { key: 'name', label: t('roles.name'), sortable: true },
    { key: 'guard_name', label: t('roles.guard_name') },
    { key: 'permissions_count', label: t('roles.permissions_count'), cellClass: 'text-center' },
    { key: 'users_count', label: t('roles.users_count'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[100px]' },
])

const permissionGroups = computed(() => {
    const groups = []
    for (const [key, perms] of Object.entries(props.permissions)) {
        groups.push({ key, name: key, permissions: perms })
    }
    return groups
})

function onSearch(value) {
    router.get(route('roles.index'), { ...props.filters, search: value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true })
}

const openCreate = () => {
    editing.value = null
    form.reset()
    form.permissions = []
    showForm.value = true
}

const openEdit = (role) => {
    editing.value = role
    form.name = role.name
    form.guard_name = role.guard_name
    form.permissions = []
    showForm.value = true
}

const submit = () => {
    if (editing.value) {
        form.put(route('roles.update', editing.value.id), { onSuccess: () => (showForm.value = false) })
    } else {
        form.post(route('roles.store'), { onSuccess: () => (showForm.value = false) })
    }
}

const confirmDelete = (role) => {
    deleting.value = role
    showConfirm.value = true
}

const executeDelete = () => {
    if (deleting.value) {
        router.delete(route('roles.destroy', deleting.value.id), {
            onSuccess: () => (showConfirm.value = false),
        })
    }
}
</script>

<template>
    <AppLayout :title="t('roles.title')">
        <Head :title="t('roles.title')" />

        <PageHeader
            :title="t('roles.title')"
            :description="t('roles.subtitle')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" @click="openCreate">
                    {{ t('roles.create') }}
                </Button>
            </template>
        </PageHeader>

        <DataTable
            :columns="columns"
            :data="roles"
            :filters="filters"
            :route-name="'roles.index'"
            :only="['roles']"
            storage-key="roles"
            @search="onSearch"
        >
            <template #cell-permissions_count="{ row }">
                <Badge :text="row.permissions_count" variant="info" />
            </template>

            <template #cell-users_count="{ row }">
                <Badge :text="row.users_count" variant="info" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton
                        icon="fas fa-pen"
                        :aria-label="t('common.edit')"
                        variant="ghost"
                        size="sm"
                        @click="openEdit(row)"
                    />
                    <IconButton
                        v-if="row.name !== 'super-admin'"
                        icon="fas fa-trash"
                        :aria-label="t('common.delete')"
                        variant="danger"
                        size="sm"
                        @click="confirmDelete(row)"
                    />
                </div>
            </template>
        </DataTable>

        <FormModal
            v-model="showForm"
            :title="editing ? t('roles.edit') : t('roles.create')"
        >
            <FormInput
                v-model="form.name"
                :label="t('roles.name')"
                :error="form.errors.name"
                required
            />
            <FormSelect
                v-model="form.guard_name"
                :label="t('roles.guard_name')"
                :options="guardOptions"
                :error="form.errors.guard_name"
            />

            <div class="mt-4">
                <label class="block text-sm font-medium text-mistral-ink mb-2">
                    {{ t('roles.permissions') }}
                </label>
                <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto p-3 border border-mistral-hairline-soft rounded-lg">
                    <FormCheckbox
                        v-for="perm in permissionGroups"
                        :key="perm.key"
                        :model-value="form.permissions.includes(perm.key)"
                        :label="perm.name"
                        @change="(val) => {
                            if (val) {
                                form.permissions.push(perm.key)
                            } else {
                                form.permissions = form.permissions.filter(p => p !== perm.key)
                            }
                        }"
                    />
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showForm = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button
                    variant="primary"
                    icon="fas fa-save"
                    :loading="form.processing"
                    @click="submit"
                >
                    {{ t('common.save') }}
                </Button>
            </template>
        </FormModal>

        <ConfirmDialog
            v-model="showConfirm"
            :title="t('common.confirm_delete')"
            :message="t('roles.confirm_delete')"
            @confirm="executeDelete"
        />
    </AppLayout>
</template>
