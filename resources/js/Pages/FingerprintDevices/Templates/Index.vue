<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, DataTable, ConfirmDialog, Badge, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    templates: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDelete = ref(false);
const selectedTemplate = ref(null);

const columns = computed(() => [
    { key: 'id', label: t('fingerprint_devices.id'), sortable: true, cellClass: 'text-center w-[80px]' },
    { key: 'user', label: t('fingerprint_devices.user') },
    { key: 'device', label: t('fingerprint_devices.device_name') },
    { key: 'finger_id', label: t('fingerprint_devices.finger_id'), cellClass: 'text-center' },
    { key: 'quality', label: t('fingerprint_devices.template_quality'), cellClass: 'text-center' },
    { key: 'is_master', label: t('fingerprint_devices.is_master'), cellClass: 'text-center' },
    { key: 'synced_at', label: t('fingerprint_devices.synced_at'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[120px]' },
]);

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { dateStyle: 'short' });
}

function onSearch(value) {
    router.get(
        route('fingerprint-templates.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true, only: ['templates'] },
    );
}

function confirmDelete(template) {
    selectedTemplate.value = template;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedTemplate.value) return;
    router.delete(route('fingerprint-templates.destroy', selectedTemplate.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('fingerprint_devices.templates')">
        <PageHeader
            :title="t('fingerprint_devices.templates')"
            :description="t('fingerprint_devices.templates_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="templates"
            :filters="filters"
            :route-name="'fingerprint-templates.index'"
            :only="['templates']"
            :empty-title="t('fingerprint_devices.no_templates_title')"
            :empty-description="t('fingerprint_devices.no_templates_description')"
            storage-key="fingerprint-device-templates"
            @search="onSearch"
        >
            <template #cell-user="{ row }">
                <span v-if="row.user">{{ row.user.name }}</span>
                <span v-else class="text-mistral-stone">—</span>
            </template>

            <template #cell-device="{ row }">
                <span v-if="row.device">{{ row.device.name }}</span>
                <span v-else class="text-mistral-stone">—</span>
            </template>

            <template #cell-is_master="{ row }">
                <Badge
                    v-if="row.is_master"
                    :text="t('fingerprint_devices.is_master')"
                    variant="active"
                />
                <span v-else class="text-mistral-stone">—</span>
            </template>

            <template #cell-synced_at="{ row }">
                <span class="text-[12px] text-mistral-steel">{{ formatDate(row.synced_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('fingerprint-templates.show', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click.stop="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('fingerprint_devices.delete_template_confirm_title')"
            :message="t('fingerprint_devices.delete_template_confirm_message')"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
