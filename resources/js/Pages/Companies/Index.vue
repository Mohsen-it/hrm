<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    companies: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDelete = ref(false);
const selectedCompany = ref(null);

const columns = computed(() => [
    { key: 'company_code', label: t('companies.code'), sortable: true },
    { key: 'company_name', label: t('companies.name'), sortable: true },
    { key: 'email', label: t('companies.email'), filterable: true, filterType: 'text' },
    { key: 'phone', label: t('companies.phone') },
    { key: 'city', label: t('companies.city'), filterable: true, filterType: 'text' },
    { key: 'is_default', label: t('companies.default'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: [{ value: '1', label: t('companies.default_only') }] },
    { key: 'status', label: t('common.status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: [{ value: '1', label: t('common.active') }, { value: '0', label: t('common.inactive') }] },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[120px]' },
]);

function onSearch(value) {
    router.get(route('companies.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true, only: ['companies'] });
}

function onExport() {
    window.location.href = route('companies.export', props.filters);
}

function onFilterChange(filters) {
    router.get(route('companies.index'), { ...props.filters, ...filters }, { preserveState: true, preserveScroll: true, replace: true, only: ['companies'] });
}

function confirmDelete(company) {
    selectedCompany.value = company;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedCompany.value) return;
    router.delete(route('companies.destroy', selectedCompany.value.id), { preserveScroll: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('companies.title')">
        <PageHeader :title="t('companies.title')" :description="t('companies.index_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('companies.create')">
                    {{ t('companies.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="companies"
            :filters="filters"
            :route-name="'companies.index'"
            :only="['companies']"
            storage-key="companies"
            @search="onSearch"
            @filter-change="onFilterChange"
            @export="onExport"
        >
            <template #cell-is_default="{ row }">
                <Badge v-if="row.is_default" :text="t('common.yes')" variant="info" />
                <span v-else class="text-mistral-muted">—</span>
            </template>

            <template #cell-status="{ row }">
                <Badge v-if="row.status === 1" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('companies.show', row.id)" />
                    <IconButton icon="fas fa-pen" :aria-label="t('common.edit')" :href="route('companies.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('companies.delete_confirm_title')"
            :message="t('companies.delete_confirm_message', { name: selectedCompany?.company_name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
