<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, DataTable, ConfirmDialog, Badge, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    deviceTypes: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDelete = ref(false);
const selectedType = ref(null);

const columns = computed(() => [
    { key: 'name', label: t('fingerprint_devices.type_name'), sortable: true },
    { key: 'manufacturer', label: t('fingerprint_devices.manufacturer'), sortable: true },
    { key: 'protocol', label: t('fingerprint_devices.protocol') },
    { key: 'default_port', label: t('fingerprint_devices.default_port'), cellClass: 'text-center' },
    { key: 'devices_count', label: t('fingerprint_devices.devices_count'), cellClass: 'text-center' },
    { key: 'is_active', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[120px]' },
]);

function onSearch(value) {
    router.get(
        route('fingerprint-device-types.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true, only: ['deviceTypes'] },
    );
}

function confirmDelete(type) {
    selectedType.value = type;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedType.value) return;
    router.delete(route('fingerprint-device-types.destroy', selectedType.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('fingerprint_devices.device_types')">
        <PageHeader
            :title="t('fingerprint_devices.device_types')"
            :description="t('fingerprint_devices.types_description')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('fingerprint-device-types.create')">
                    {{ t('fingerprint_devices.add_type') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="deviceTypes"
            :filters="filters"
            :route-name="'fingerprint-device-types.index'"
            :only="['deviceTypes']"
            :empty-title="t('fingerprint_devices.no_types_title')"
            :empty-description="t('fingerprint_devices.no_types_description')"
            storage-key="fingerprint-device-types"
            @search="onSearch"
        >
            <template #cell-is_active="{ row }">
                <Badge
                    v-if="row.is_active"
                    :text="t('common.active')"
                    variant="active"
                />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('fingerprint-device-types.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click.stop="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('fingerprint_devices.delete_type_confirm_title')"
            :message="t('fingerprint_devices.delete_type_confirm_message', { name: selectedType?.name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
