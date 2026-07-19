<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    shifts: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedShift = ref(null);

const dayLabels = computed(() => [
    { value: 0, label: t('shifts.sunday') },
    { value: 1, label: t('shifts.monday') },
    { value: 2, label: t('shifts.tuesday') },
    { value: 3, label: t('shifts.wednesday') },
    { value: 4, label: t('shifts.thursday') },
    { value: 5, label: t('shifts.friday') },
    { value: 6, label: t('shifts.saturday') },
]);

function formatDays(days) {
    if (!Array.isArray(days) || days.length === 0) return t('shifts.no_days_selected');
    const sorted = [...days].sort((a, b) => a - b);
    return sorted.map((d) => {
        const found = dayLabels.value.find((l) => l.value === Number(d));
        return found ? found.label : null;
    }).filter(Boolean).join(' - ');
}

const columns = computed(() => [
    { key: 'shift_code', label: t('shifts.code'), sortable: true },
    { key: 'shift_name', label: t('shifts.name'), sortable: true },
    { key: 'company', label: t('shifts.company'), filterable: true, filterType: 'select', filterOptions: [{ value: '', label: t('shifts.select_company') }, ...props.companies.map((c) => ({ value: c.id, label: c.company_name }))] },
    { key: 'branch', label: t('shifts.branch'), filterable: true, filterType: 'select', filterOptions: [{ value: '', label: t('shifts.select_branch') }, ...props.branches.map((b) => ({ value: b.id, label: b.branch_name }))] },
    { key: 'time_range', label: t('shifts.time_range') },
    { key: 'work_days', label: t('shifts.work_days') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: [{ value: '', label: t('common.all_statuses') }, { value: '1', label: t('common.active') }, { value: '0', label: t('common.inactive') }] },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(
        route('shifts.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onFilterChange(key, value) {
    const next = { ...props.filters };
    if (value === '' || value === null || value === undefined) {
        delete next[key];
    } else {
        next[key] = value;
    }
    router.get(
        route('shifts.index'),
        next,
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onPageChange(page) {
    router.get(
        route('shifts.index'),
        { ...props.filters, page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onPerPageChange(perPage) {
    router.get(
        route('shifts.index'),
        { ...props.filters, per_page: perPage },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(shift) {
    selectedShift.value = shift;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedShift.value) return;
    router.delete(route('shifts.destroy', selectedShift.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('shifts.title')">
        <PageHeader
            :title="t('shifts.title')"
            :description="t('shifts.index_description')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('shifts.create')">
                    {{ t('shifts.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="shifts"
            storage-key="shifts"
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

            <template #cell-time_range="{ row }">
                <span dir="ltr">{{ row.start_time }} - {{ row.end_time }}</span>
            </template>

            <template #cell-work_days="{ row }">
                <span class="text-[12px]">{{ formatDays(row.work_days) }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge v-if="row.status === 1" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('shifts.show', row.id)" />
                    <IconButton icon="fas fa-pen" :aria-label="t('common.edit')" :href="route('shifts.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('shifts.delete_confirm_title')"
            :message="t('shifts.delete_confirm_message', { name: selectedShift?.shift_name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
