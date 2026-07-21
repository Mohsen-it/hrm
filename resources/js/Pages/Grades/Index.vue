<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    grades: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedGrade = ref(null);

const columns = computed(() => [
    { key: 'grade_code', label: t('grades.code'), sortable: true },
    { key: 'grade_name', label: t('grades.name'), sortable: true },
    { key: 'level', label: t('grades.level'), cellClass: 'text-center', sortable: true, filterable: true, filterType: 'text' },
    { key: 'company', label: t('grades.company'), filterable: true, filterType: 'select', filterOptions: props.companies.map((c) => ({ value: c.id, label: c.company_name })) },
    { key: 'salary_range', label: t('grades.salary_range') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: [{ value: '1', label: t('common.active') }, { value: '0', label: t('common.inactive') }] },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(route('grades.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true, only: ['grades'] });
}

function onFilterChange(filters) {
    router.get(route('grades.index'), { ...props.filters, ...filters }, { preserveState: true, preserveScroll: true, replace: true, only: ['grades'] });
}

function confirmDelete(grade) {
    selectedGrade.value = grade;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedGrade.value) return;
    router.delete(route('grades.destroy', selectedGrade.value.id), { preserveScroll: true });
}

function formatSalary(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString();
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('grades.title')">
        <PageHeader :title="t('grades.title')" :description="t('grades.index_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('grades.create')">
                    {{ t('grades.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="grades"
            :filters="filters"
            :route-name="'grades.index'"
            :only="['grades']"
            storage-key="grades"
            @search="onSearch"
            @filter-change="onFilterChange"
        >
            <template #cell-level="{ row }">
                <span>{{ row.level }}</span>
            </template>

            <template #cell-company="{ row }">
                <span>{{ row.company?.company_name || '—' }}</span>
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
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('grades.show', row.id)" />
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('grades.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('grades.delete_confirm_title')"
            :message="t('grades.delete_confirm_message', { name: selectedGrade?.grade_name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
