<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from '@/Components/ui/DataTable.vue'
import FormInput from '@/Components/ui/FormInput.vue'
import FormSelect from '@/Components/ui/FormSelect.vue'
import FormModal from '@/Components/ui/FormModal.vue'
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Button from '@/Components/ui/Button.vue'
import SearchInput from '@/Components/ui/SearchInput.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t, isRtl } = useTranslations()

const props = defineProps({
    roles: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search || '')
const showForm = ref(false)
const showConfirm = ref(false)
const editing = ref(null)
const deleting = ref(null)

const form = useForm({
    name: '',
    guard_name: 'web',
    permissions: [],
})

const columns = computed(() => [
    { key: 'name', label: t('roles.name'), sortable: true },
    { key: 'guard_name', label: t('roles.guard_name') },
    { key: 'permissions_count', label: t('roles.permissions_count') },
    { key: 'users_count', label: t('roles.users_count') },
    { key: 'actions', label: t('common.actions') },
])

const applySearch = () => {
    router.get(route('roles.index'), { search: search.value }, { preserveState: true, preserveScroll: true })
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
    <AppLayout>
        <Head :title="t('roles.title')" />

        <PageHeader :title="t('roles.title')" :subtitle="t('roles.subtitle')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" @click="openCreate">
                    {{ t('roles.create') }}
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4 max-w-md">
            <SearchInput v-model="search" :placeholder="t('common.search')" @search="applySearch" />
        </div>

        <DataTable :columns="columns" :data="roles.data">
            <template #cell(actions)="{ row }">
                <button @click="openEdit(row)" class="text-blue-600 hover:underline mx-2">
                    {{ t('common.edit') }}
                </Button>
                <button v-if="row.name !== 'super-admin'" @click="confirmDelete(row)" class="text-red-600 hover:underline">
                    {{ t('common.delete') }}
                </Button>
            </template>
        </DataTable>

        <FormModal v-model="showForm" :title="editing ? t('roles.edit') : t('roles.create')">
            <FormInput v-model="form.name" :label="t('roles.name')" :error="form.errors.name" required />
            <FormSelect v-model="form.guard_name" :label="t('roles.guard_name')" :options="['web', 'api']" :error="form.errors.guard_name" />

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('roles.permissions') }}</label>
                <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto p-2 border rounded">
                    <label v-for="(group, key) in permissions" :key="key" class="flex items-center text-sm">
                        <input type="checkbox" :value="key + '-' + name" v-model="form.permissions" class="mx-2" />
                        {{ name }}
                    </label>
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showForm = false">{{ t('common.cancel') }}</Button>
                <Button variant="primary" @click="submit" :loading="form.processing" icon="fas fa-save">
                    {{ t('common.save') }}
                </Button>
            </template>
        </FormModal>

        <ConfirmDialog v-model="showConfirm" :title="t('common.confirm_delete')" :message="t('roles.confirm_delete')" @confirm="executeDelete" />
    </AppLayout>
</template>
