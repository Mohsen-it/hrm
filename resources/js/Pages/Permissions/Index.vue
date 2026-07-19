<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { PageHeader, Button, Card, FormInput, FormSelect, EmptyState, DataTable } from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    grouped: { type: Array, required: true },
    roles: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
})

const attachForm = useForm({
    role: '',
    permission: '',
})

const detachForm = useForm({
    role: '',
    permission: '',
})

const selectedDetachRole = ref({})

const roleOptions = props.roles.map(r => ({ value: r.name, label: r.name }))

const attach = () => {
    if (!attachForm.role || !attachForm.permission) return
    attachForm.post(route('permissions.attach'), {
        onSuccess: () => attachForm.reset(),
    })
}

const detach = (role, permission) => {
    detachForm.role = role
    detachForm.permission = permission
    detachForm.post(route('permissions.detach'))
}

function onDetachChange(event, permName) {
    const role = event.target.value
    if (role) detach(role, permName)
}
</script>

<template>
    <AppLayout :title="t('permissions.title')">
        <Head :title="t('permissions.title')" />

        <PageHeader
            :title="t('permissions.title')"
            :description="t('permissions.subtitle')"
        />

        <!-- Attach form -->
        <Card variant="base" class="mb-4 rounded-xl">
            <div class="p-5 sm:p-6">
                <h3 class="font-semibold mb-3 text-mistral-ink">
                    {{ t('permissions.attach') }}
                </h3>
                <div class="flex flex-wrap gap-2">
                    <div class="flex-1 min-w-[200px]">
                        <FormSelect
                            v-model="attachForm.role"
                            :options="roleOptions"
                            :placeholder="t('permissions.role')"
                        />
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <FormInput
                            v-model="attachForm.permission"
                            :placeholder="t('permissions.module') + ' permission (e.g. view-companies)'"
                            :error="attachForm.errors.permission"
                        />
                    </div>
                    <Button
                        variant="primary"
                        icon="fas fa-link"
                        :loading="attachForm.processing"
                        @click="attach"
                    >
                        {{ t('permissions.attach') }}
                    </Button>
                </div>
            </div>
        </Card>

        <!-- Permission groups -->
        <div v-for="group in grouped" :key="group.module" class="mb-4">
            <Card variant="base" padding="none" class="rounded-xl">
                <div class="px-5 py-4 border-b border-mistral-hairline-soft">
                    <h3 class="font-semibold text-lg text-mistral-ink">
                        {{ group.module }}
                    </h3>
                </div>

                <div v-if="group.permissions && group.permissions.length > 0">
                    <DataTable
                        :columns="[
                            { key: 'name', label: 'Permission' },
                            { key: 'guard_name', label: 'Guard' },
                            { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
                        ]"
                        :data="{ data: group.permissions, links: [] }"
                        :selectable="false"
                        :enable-search="false"
                        :enable-filters="false"
                        :enable-pagination="false"
                        :enable-export="false"
                        :enable-density="false"
                        :enable-column-visibility="false"
                        :storage-key="`permissions-${group.module}`"
                    >
                        <template #cell-name="{ row }">
                            <code class="text-xs bg-mistral-surface px-2 py-1 rounded-lg text-mistral-ink">
                                {{ row.name }}
                            </code>
                        </template>

                        <template #cell-actions="{ row }">
                            <select
                                :value="selectedDetachRole[row.id] || ''"
                                class="text-xs bg-mistral-canvas border border-mistral-hairline-strong rounded-lg px-2 py-1 text-mistral-ink cursor-pointer"
                                @change="onDetachChange($event, row.name)"
                            >
                                <option value="">{{ t('permissions.detach') }}</option>
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.name"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                        </template>
                    </DataTable>
                </div>

                <EmptyState
                    v-else
                    icon="fas fa-lock"
                    :title="t('common.no_data')"
                />
            </Card>
        </div>
    </AppLayout>
</template>
