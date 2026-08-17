<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { PageHeader, DataTable, Badge, Button, Card, SearchInput, FormSelect, Alert, Avatar } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    employees: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    total: { type: Number, default: 0 },
    companies: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    subordinations: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');

const columns = computed(() => [
    { key: 'employee_code', label: t('users.employee_code'), sortable: true },
    { key: 'name', label: t('users.name'), sortable: true },
    { key: 'email', label: t('users.email') },
    { key: 'company', label: t('users.company') },
    { key: 'branch', label: t('users.branch') },
    { key: 'department', label: t('users.department') },
    { key: 'subordination', label: t('users.subordination') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center' },
]);

const companyOptions = computed(() => [
    { value: '', label: t('users.select_company') },
    ...props.companies.map((c) => ({ value: c.id, label: c.company_name })),
]);

const branchOptions = computed(() => [
    { value: '', label: t('users.select_branch') },
    ...props.branches.map((b) => ({ value: b.id, label: b.branch_name })),
]);

const departmentOptions = computed(() => [
    { value: '', label: t('users.select_department') },
    ...props.departments.map((d) => ({ value: d.id, label: d.department_name })),
]);

const subordinationOptions = computed(() => [
    { value: '', label: t('users.select_subordination') },
    ...props.subordinations.map((s) => ({ value: s.id, label: s.display_name })),
]);

function onSearch(value) {
    router.get(
        route('rotations.unassigned-employees'),
        { ...props.filters, search: value, page: 1 },
        { preserveState: true, preserveScroll: true, replace: true, only: ['employees', 'total'] },
    );
}

function applyFilter(key, value) {
    const newFilters = { ...props.filters };
    if (value === '' || value === null || value === undefined) {
        delete newFilters[key];
    } else {
        newFilters[key] = value;
    }
    router.get(route('rotations.unassigned-employees'), newFilters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['employees', 'total'],
    });
}

function onExport() {
    window.location.href = route('rotations.unassigned-employees.export', props.filters);
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);


usePageTitle(t('shifts.unassigned_rotation_employees'));
</script>

<template>
    
        <PageHeader
            :title="t('shifts.unassigned_rotation_employees')"
            :description="t('shifts.unassigned_rotation_employees_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-file-excel" @click="onExport">
                    {{ t('common.export') }}
                </Button>
                <Button variant="secondary" icon="fas fa-people-arrows" :href="route('rotations.assign')">
                    {{ t('shifts.assign_rotation') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <Card variant="base" padding="none" class="mb-4">
            <div class="p-5 sm:p-6">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3 flex-wrap">
                        <SearchInput
                            v-model="search"
                            :placeholder="t('common.search')"
                            @search="onSearch"
                        />
                        <FormSelect
                            :model-value="filters.company_id ?? ''"
                            :options="companyOptions"
                            class="max-w-[180px]"
                            @update:model-value="(v) => applyFilter('company_id', v)"
                        />
                        <FormSelect
                            :model-value="filters.branch_id ?? ''"
                            :options="branchOptions"
                            class="max-w-[180px]"
                            @update:model-value="(v) => applyFilter('branch_id', v)"
                        />
                        <FormSelect
                            :model-value="filters.department_id ?? ''"
                            :options="departmentOptions"
                            class="max-w-[180px]"
                            @update:model-value="(v) => applyFilter('department_id', v)"
                        />
                        <FormSelect
                            :model-value="filters.subordination_id ?? ''"
                            :options="subordinationOptions"
                            class="max-w-[180px]"
                            @update:model-value="(v) => applyFilter('subordination_id', v)"
                        />
                        <FormSelect
                            :model-value="filters.status ?? ''"
                            :options="[
                                { value: '', label: t('common.all_statuses') },
                                { value: '1', label: t('common.active') },
                                { value: '0', label: t('common.inactive') },
                            ]"
                            class="max-w-[180px]"
                            @update:model-value="(v) => applyFilter('status', v)"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1.5 px-3 py-1.5 bg-mistral-danger-bg rounded-lg">
                            <i class="fas fa-exclamation-triangle text-mistral-danger text-sm"></i>
                            <span class="text-sm font-semibold text-mistral-danger">
                                {{ total }} {{ t('shifts.unassigned_count_label') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <DataTable
            :columns="columns"
            :data="employees"
            :filters="filters"
            :route-name="'rotations.unassigned-employees'"
            :only="['employees']"
            :empty-title="t('shifts.no_unassigned_title')"
            :empty-description="t('shifts.no_unassigned_description')"
            storage-key="unassigned-rotation-employees"
            @search="onSearch"
        >
            <template #cell-name="{ row }">
                <div class="flex items-center gap-2">
                    <Avatar :name="row.name" :src="row.avatar_url" size="sm" />
                    <div>
                        <div class="font-semibold text-mistral-ink">
                            {{ row.name }}
                        </div>
                        <div v-if="row.employee_code" class="text-[11px] text-mistral-stone">
                            {{ row.employee_code }}
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-company="{ row }">
                <span>{{ row.company?.company_name || '—' }}</span>
            </template>

            <template #cell-branch="{ row }">
                <span>{{ row.branch?.branch_name || '—' }}</span>
            </template>

            <template #cell-department="{ row }">
                <span>{{ row.department?.department_name || '—' }}</span>
            </template>

            <template #cell-subordination="{ row }">
                <span>{{ row.subordination?.display_name || row.subordination?.name_ar || row.subordination?.code || '—' }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge
                    v-if="row.status === 1"
                    :text="t('common.active')"
                    variant="active"
                />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>
        </DataTable>
    </template>
