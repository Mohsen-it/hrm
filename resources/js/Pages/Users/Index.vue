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
import axios from 'axios';
import { PageHeader, DataTable, SearchInput, ConfirmDialog, Badge, Button, Card, IconButton, FormSelect, Alert, Avatar, FormModal } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    users: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    subordinations: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedUser = ref(null);
const selectedIds = ref([]);
const showBulkDelete = ref(false);

const showFingerprintHistory = ref(false);
const fingerprintUser = ref(null);
const fingerprintLogs = ref([]);
const fingerprintLoading = ref(false);
const fingerprintPage = ref(1);
const fingerprintPagination = ref({ total: 0, per_page: 50, current_page: 1, last_page: 1 });

const columns = computed(() => [
    { key: 'employee_code', label: t('users.employee_code'), sortable: true },
    { key: 'name', label: t('users.name'), sortable: true },
    { key: 'email', label: t('users.email') },
    { key: 'company', label: t('users.company') },
    { key: 'branch', label: t('users.branch') },
    { key: 'subordination', label: t('users.subordination') },
    { key: 'department', label: t('users.department') },
    { key: 'shift', label: t('users.shift') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[240px]' },
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

const employmentTypeOptions = [
    { value: 'full_time', label: t('users.employment_full_time') },
    { value: 'part_time', label: t('users.employment_part_time') },
    { value: 'contract', label: t('users.employment_contract') },
    { value: 'temporary', label: t('users.employment_temporary') },
    { value: 'intern', label: t('users.employment_intern') },
];

function onSearch(value) {
    router.get(
        route('users.index'),
        { ...props.filters, search: value, page: 1 },
        { preserveState: true, preserveScroll: true, replace: true, only: ['users'] },
    );
}

function applyFilter(key, value) {
    const newFilters = { ...props.filters };
    if (value === '' || value === null || value === undefined) {
        delete newFilters[key];
    } else {
        newFilters[key] = value;
    }
    router.get(route('users.index'), newFilters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users'],
    });
}

function onExport() {
    window.location.href = route('users.export', props.filters);
}

function onExportSelected() {
    if (selectedIds.value.length === 0) return;
    window.location.href = route('users.export', { ...props.filters, ids: selectedIds.value });
}

function confirmDelete(user) {
    selectedUser.value = user;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedUser.value) return;
    router.delete(route('users.destroy', selectedUser.value.id), {
        preserveScroll: true,
    });
}

function confirmBulkDelete(ids) {
    if (Array.isArray(ids) && ids.length > 0) {
        selectedIds.value = ids;
    }
    if (selectedIds.value.length === 0) return;
    showBulkDelete.value = true;
}

function performBulkDelete() {
    if (selectedIds.value.length === 0) return;
    router.post(route('users.bulk-delete'), { ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
        },
    });
}

function openFingerprintHistory(user) {
    fingerprintUser.value = user;
    fingerprintLogs.value = [];
    fingerprintPage.value = 1;
    showFingerprintHistory.value = true;
    fetchFingerprintLogs();
}

async function fetchFingerprintLogs(page = 1) {
    fingerprintLoading.value = true;
    try {
        const { data } = await axios.get(route('users.fingerprint-history', fingerprintUser.value.id), {
            params: { page, per_page: 20 },
        });
        fingerprintLogs.value = data.data;
        fingerprintPagination.value = data.pagination;
        fingerprintPage.value = page;
    } catch {
        fingerprintLogs.value = [];
    } finally {
        fingerprintLoading.value = false;
    }
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);


usePageTitle(t('users.title'));
</script>

<template>
    
        <PageHeader
            :title="t('users.title')"
            :description="t('users.index_description')"
        >
            <template #actions>
                <Button
                    v-if="selectedIds.length > 0"
                    variant="danger"
                    icon="fas fa-trash"
                    @click="confirmBulkDelete"
                >
                    {{ t('common.delete') }} ({{ selectedIds.length }})
                </Button>
                <Button variant="primary" icon="fas fa-plus" :href="route('users.create')">
                    {{ t('users.add_new') }}
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
                            :model-value="filters.employment_type ?? ''"
                            :options="[
                                { value: '', label: t('users.select_employment_type') },
                                ...employmentTypeOptions,
                            ]"
                            class="max-w-[180px]"
                            @update:model-value="(v) => applyFilter('employment_type', v)"
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
                </div>
            </div>
        </Card>

        <DataTable
            :columns="columns"
            :data="users"
            :filters="filters"
            :route-name="'users.index'"
            :only="['users']"
            enable-bulk-delete
            enable-bulk-export
            @search="onSearch"
            @export="onExport"
            @selection-change="(ids) => (selectedIds = ids)"
            @bulk-delete="(ids) => confirmBulkDelete(ids)"
            @bulk-export="onExportSelected"
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
            <template #cell-subordination="{ row }">
                <span>{{ row.subordination?.display_name || row.subordination?.name_ar || row.subordination?.name_en || row.subordination?.code || '—' }}</span>
            </template>
            <template #cell-department="{ row }">
                <span>{{ row.department?.department_name || '—' }}</span>
            </template>
            <template #cell-shift="{ row }">
                <span>{{ row.shift?.shift_name || '—' }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge
                    v-if="row.status === 1"
                    :text="t('common.active')"
                    variant="active"
                />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1.5">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" variant="info" :href="route('users.show', row.id)" />
                    <IconButton icon="fas fa-pen" :aria-label="t('common.edit')" variant="primary" :href="route('users.edit', row.id)" />
                    <IconButton icon="fas fa-fingerprint" :aria-label="t('users.fingerprint_history')" variant="success" @click="openFingerprintHistory(row)" />
                    <IconButton icon="fas fa-clock" :aria-label="t('users.manage_shifts')" variant="secondary" :href="route('users.shifts', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('users.delete_confirm_title')"
            :message="t('users.delete_confirm_message', { name: selectedUser?.name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />

        <ConfirmDialog
            v-model="showBulkDelete"
            :title="t('users.bulk_delete_confirm_title')"
            :message="t('users.bulk_delete_confirm_message', { count: selectedIds.length })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performBulkDelete"
        />

        <FormModal v-model="showFingerprintHistory" :title="t('users.fingerprint_history') + ' — ' + (fingerprintUser?.name || '')" size="lg">
            <div v-if="fingerprintLoading" class="flex items-center justify-center py-12">
                <i class="fas fa-spinner fa-spin text-2xl text-mistral-primary"></i>
            </div>
            <div v-else-if="fingerprintLogs.length === 0" class="text-center py-12 text-mistral-steel">
                <i class="fas fa-fingerprint text-4xl mb-3 block text-mistral-hairline"></i>
                <p>{{ t('common.no_data') }}</p>
            </div>
            <div v-else class="max-h-[60vh] overflow-y-auto">
                <table class="w-full text-[13px]">
                    <thead class="sticky top-0 bg-white z-10">
                        <tr class="border-b border-mistral-hairline-soft">
                            <th class="text-start py-2 px-2.5 text-mistral-steel font-medium">{{ t('attendance.fields.punch_time') }}</th>
                            <th class="text-start py-2 px-2.5 text-mistral-steel font-medium">{{ t('attendance.fields.punch_type') }}</th>
                            <th class="text-start py-2 px-2.5 text-mistral-steel font-medium">{{ t('attendance.fields.verify_type') }}</th>
                            <th class="text-start py-2 px-2.5 text-mistral-steel font-medium">{{ t('fingerprint_devices.device_name') }}</th>
                            <th class="text-start py-2 px-2.5 text-mistral-steel font-medium">{{ t('common.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in fingerprintLogs" :key="log.id" class="border-b border-mistral-hairline-soft/50 hover:bg-mistral-surface/40 transition-colors">
                            <td class="py-2 px-2.5 text-mistral-ink font-medium whitespace-nowrap">{{ log.punch_time }}</td>
                            <td class="py-2 px-2.5">
                                <Badge
                                    :text="log.punch_type === 'check_in' ? t('attendance.punch_type.check_in') : log.punch_type === 'check_out' ? t('attendance.punch_type.check_out') : log.punch_type"
                                    :variant="log.punch_type === 'check_in' ? 'active' : log.punch_type === 'check_out' ? 'warning' : 'inactive'"
                                />
                            </td>
                            <td class="py-2 px-2.5 text-mistral-steel capitalize">{{ log.verify_type }}</td>
                            <td class="py-2 px-2.5">
                                <div v-if="log.device" class="text-mistral-ink">
                                    <div class="font-medium">{{ log.device.name }}</div>
                                    <div v-if="log.device.serial_number" class="text-[11px] text-mistral-steel">{{ log.device.serial_number }}</div>
                                </div>
                                <span v-else class="text-mistral-hairline">—</span>
                            </td>
                            <td class="py-2 px-2.5">
                                <Badge
                                    :text="log.processed ? t('attendance.fields.processed') : t('common.pending')"
                                    :variant="log.processed ? 'active' : 'warning'"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <template v-if="fingerprintPagination.last_page > 1" #footer>
                <div class="flex items-center justify-between w-full">
                    <span class="text-[13px] text-mistral-steel">
                        {{ t('common.total') }}: {{ fingerprintPagination.total }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="secondary"
                            size="sm"
                            :disabled="fingerprintPage <= 1"
                            @click="fetchFingerprintLogs(fingerprintPage - 1)"
                        >
                            <i class="fas fa-chevron-right text-[11px]"></i>
                        </Button>
                        <span class="text-[13px] text-mistral-steel px-2">
                            {{ fingerprintPage }} / {{ fingerprintPagination.last_page }}
                        </span>
                        <Button
                            variant="secondary"
                            size="sm"
                            :disabled="fingerprintPage >= fingerprintPagination.last_page"
                            @click="fetchFingerprintLogs(fingerprintPage + 1)"
                        >
                            <i class="fas fa-chevron-left text-[11px]"></i>
                        </Button>
                    </div>
                </div>
            </template>
        </FormModal>
    </template>
