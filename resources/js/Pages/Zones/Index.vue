<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();
const page = usePage();

const props = defineProps({
    zones: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedZone = ref(null);

const displayName = (z) => locale.value === 'en' && z.name_en ? z.name_en : z.name_ar;

const zoneTypeOptions = [
    { value: '', label: t('common.all') },
    { value: 'geographic', label: t('zones.zone_type_geographic') },
    { value: 'operational', label: t('zones.zone_type_operational') },
    { value: 'security', label: t('zones.zone_type_security') },
    { value: 'sales', label: t('zones.zone_type_sales') },
    { value: 'logistics', label: t('zones.zone_type_logistics') },
];

const statusOptions = [
    { value: '', label: t('common.all_statuses') },
    { value: '1', label: t('common.active') },
    { value: '0', label: t('common.inactive') },
];

const columns = computed(() => [
    { key: 'code', label: t('zones.code'), sortable: true },
    { key: 'name_ar', label: t('zones.name_ar') },
    { key: 'name_en', label: t('zones.name_en') },
    { key: 'zone_type', label: t('zones.zone_type'), filterable: true, filterType: 'select', filterOptions: zoneTypeOptions },
    { key: 'city', label: t('zones.city') },
    { key: 'branches_count', label: t('zones.branches'), cellClass: 'text-center' },
    { key: 'is_active', label: t('common.status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: statusOptions },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[200px]' },
]);

function onSearch(value) {
    router.get(route('zones.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true });
}

function onFilterChange(filters) {
    router.get(route('zones.index'), { ...props.filters, ...filters }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPageChange(page) {
    router.get(route('zones.index'), { ...props.filters, page }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPerPageChange(perPage) {
    router.get(route('zones.index'), { ...props.filters, per_page: perPage }, { preserveState: true, preserveScroll: true, replace: true });
}

function confirmDelete(zone) {
    selectedZone.value = zone;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedZone.value) return;
    router.delete(route('zones.destroy', selectedZone.value.id), { preserveScroll: true });
}

const flashSuccess = computed(() => page.props.flash?.success);

function zoneTypeLabel(value) {
    const match = zoneTypeOptions.find((o) => o.value === value);
    return match ? match.label : value;
}
</script>

<template>
    <AppLayout :title="t('zones.title')">
        <PageHeader :title="t('zones.title')" :description="t('zones.index_description')">
            <template #actions>
                <Button variant="secondary" icon="fas fa-chart-pie" :href="route('zones.dashboard')">
                    {{ t('zones.title') }} · Dashboard
                </Button>
                <Button variant="primary" icon="fas fa-plus" :href="route('zones.create')">
                    {{ t('zones.add_zone') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="zones"
            storage-key="zones"
            @search="onSearch"
            @filter-change="onFilterChange"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
        >
            <template #cell-name_ar="{ row }">
                <a :href="route('zones.show', row.id)" class="font-medium text-mistral-primary hover:underline">
                    {{ row.name_ar }}
                </a>
            </template>
            <template #cell-zone_type="{ row }">
                <Badge :text="zoneTypeLabel(row.zone_type)" variant="info" />
            </template>
            <template #cell-is_active="{ row }">
                <Badge v-if="row.is_active" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('zones.show', row.id)" />
                    <IconButton icon="fas fa-code-branch" :aria-label="t('zones.manage_branches')" :href="route('zones.branches', row.id)" />
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('zones.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('zones.delete_confirm_title')"
            :message="t('zones.delete_confirm_message', { name: selectedZone?.name_ar })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
