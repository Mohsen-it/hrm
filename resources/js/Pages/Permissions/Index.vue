<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Button from '@/Components/ui/Button.vue'
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

const attach = () => {
    if (! attachForm.role || ! attachForm.permission) return
    attachForm.post(route('permissions.attach'), {
        onSuccess: () => attachForm.reset(),
    })
}

const detach = (role, permission) => {
    detachForm.role = role
    detachForm.permission = permission
    detachForm.post(route('permissions.detach'))
}
</script>

<template>
    <AppLayout>
        <Head :title="t('permissions.title')" />

        <PageHeader :title="t('permissions.title')" :subtitle="t('permissions.subtitle')" />

        <div class="bg-white shadow rounded p-4 mb-4">
            <h3 class="font-semibold mb-2">{{ t('permissions.attach') }}</h3>
            <div class="flex gap-2">
                <select v-model="attachForm.role" class="form-select flex-1">
                    <option value="">{{ t('permissions.role') }}</option>
                    <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                </select>
                <input v-model="attachForm.permission" :placeholder="t('permissions.module') + ' permission (e.g. view-companies)'" class="form-input flex-1" />
                <Button variant="primary" icon="fas fa-link" @click="attach" :loading="attachForm.processing">
                    {{ t('permissions.attach') }}
                </Button>
            </div>
            <p v-if="attachForm.errors.permission" class="text-red-600 text-sm mt-1">{{ attachForm.errors.permission }}</p>
        </div>

        <div v-for="group in grouped" :key="group.module" class="bg-white shadow rounded p-4 mb-4">
            <h3 class="font-semibold text-lg mb-3">{{ group.module }}</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-start border-b">
                        <th class="py-2 text-start">Permission</th>
                        <th class="py-2 text-start">Guard</th>
                        <th class="py-2 text-start">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="perm in group.permissions" :key="perm.id" class="border-b">
                        <td class="py-2"><code class="text-xs">{{ perm.name }}</code></td>
                        <td class="py-2">{{ perm.guard_name }}</td>
                        <td class="py-2">
                            <select @change="detach($event.target.dataset.role || roles[0].name, perm.name)" class="text-xs">
                                <option value="">{{ t('permissions.detach') }}</option>
                                <option v-for="role in roles" :key="role.id" :value="role.name" :data-role="role.name">{{ role.name }}</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
