<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    deviceTypes: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search || '');
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
        { preserveState: true, preserveScroll: true, replace: true },
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
                <Link :href="route('fingerprint-device-types.create')" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    <span>{{ t('fingerprint_devices.add_type') }}</span>
                </Link>
            </template>
        </PageHeader>

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>

        <div class="card p-4 mb-4">
            <SearchInput
                v-model="search"
                :placeholder="t('common.search')"
                @search="onSearch"
            />
        </div>

        <DataTable
            :columns="columns"
            :data="deviceTypes"
            :empty-title="t('fingerprint_devices.no_types_title')"
            :empty-description="t('fingerprint_devices.no_types_description')"
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
                    <Link
                        :href="route('fingerprint-device-types.edit', row.id)"
                        class="btn-icon text-[var(--color-primary)]"
                        :title="t('common.edit')"
                    >
                        <i class="fas fa-edit"></i>
                    </Link>
                    <button
                        type="button"
                        class="btn-icon text-[var(--color-danger)]"
                        :title="t('common.delete')"
                        @click.stop="confirmDelete(row)"
                    >
                        <i class="fas fa-trash"></i>
                    </Button>
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
