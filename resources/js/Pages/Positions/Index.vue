<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    positions: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedPosition = ref(null);

const columns = computed(() => [
    { key: 'position_code', label: t('positions.code'), sortable: true },
    { key: 'position_name', label: t('positions.name'), sortable: true },
    { key: 'company', label: t('positions.company'), filterable: true, filterType: 'select', filterOptions: props.companies.map((c) => ({ value: c.id, label: c.company_name })) },
    { key: 'branch', label: t('positions.branch'), filterable: true, filterType: 'select', filterOptions: props.branches.map((b) => ({ value: b.id, label: b.branch_name })) },
    { key: 'department', label: t('positions.department'), filterable: true, filterType: 'select', filterOptions: props.departments.map((d) => ({ value: d.id, label: d.department_name })) },
    { key: 'salary_range', label: t('positions.salary_range') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: [{ value: '1', label: t('common.active') }, { value: '0', label: t('common.inactive') }] },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(route('positions.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true });
}

function onFilterChange(filters) {
    router.get(route('positions.index'), { ...props.filters, ...filters }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPageChange(page) {
    router.get(route('positions.index'), { ...props.filters, page }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPerPageChange(perPage) {
    router.get(route('positions.index'), { ...props.filters, per_page: perPage }, { preserveState: true, preserveScroll: true, replace: true });
}

function confirmDelete(position) {
    selectedPosition.value = position;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedPosition.value) return;
    router.delete(route('positions.destroy', selectedPosition.value.id), { preserveScroll: true });
}

function formatSalary(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString();
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('positions.title')">
        <PageHeader :title="t('positions.title')" :description="t('positions.index_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('positions.create')">
                    {{ t('positions.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="positions"
            storage-key="positions"
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

            <template #cell-department="{ row }">
                <span>{{ row.department?.department_name || t('positions.no_department') }}</span>
            </template>

            <template #cell-salary_range="{ row }">
                <span>{{ formatSalary(row.min_salary) }} - {{ formatSalary(row.max_salary) }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge v-if="row.status === 1" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('positions.show', row.id)" />
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('positions.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('positions.delete_confirm_title')"
            :message="t('positions.delete_confirm_message', { name: selectedPosition?.position_name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
