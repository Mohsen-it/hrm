<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    departments: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedDepartment = ref(null);

const columns = computed(() => [
    { key: 'department_code', label: t('departments.code'), sortable: true },
    { key: 'department_name', label: t('departments.name'), sortable: true },
    { key: 'company', label: t('departments.company'), filterable: true, filterType: 'select', filterOptions: props.companies.map((c) => ({ value: c.id, label: c.company_name })) },
    { key: 'branch', label: t('departments.branch'), filterable: true, filterType: 'select', filterOptions: props.branches.map((b) => ({ value: b.id, label: b.branch_name })) },
    { key: 'manager', label: t('departments.manager') },
    { key: 'phone', label: t('departments.phone') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: [{ value: '1', label: t('common.active') }, { value: '0', label: t('common.inactive') }] },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(route('departments.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true });
}

function onFilterChange(filters) {
    router.get(route('departments.index'), { ...props.filters, ...filters }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPageChange(page) {
    router.get(route('departments.index'), { ...props.filters, page }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPerPageChange(perPage) {
    router.get(route('departments.index'), { ...props.filters, per_page: perPage }, { preserveState: true, preserveScroll: true, replace: true });
}

function confirmDelete(department) {
    selectedDepartment.value = department;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedDepartment.value) return;
    router.delete(route('departments.destroy', selectedDepartment.value.id), { preserveScroll: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('departments.title')">
        <PageHeader :title="t('departments.title')" :description="t('departments.index_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('departments.create')">
                    {{ t('departments.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="departments"
            storage-key="departments"
            @search="onSearch"
            @filter-change="onFilterChange"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
        >
            <template #cell-company="{ row }">
                <span>{{ row.company?.company_name || '—' }}</span>
            </template>

            <template #cell-branch="{ row }">
                <span>{{ row.branch?.branch_name || '—' }}</span>
            </template>

            <template #cell-manager="{ row }">
                <span>{{ row.manager?.name || t('departments.no_manager') }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge v-if="row.status === 1" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('departments.show', row.id)" />
                    <IconButton icon="fas fa-pen" :aria-label="t('common.edit')" :href="route('departments.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('departments.delete_confirm_title')"
            :message="t('departments.delete_confirm_message', { name: selectedDepartment?.department_name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
