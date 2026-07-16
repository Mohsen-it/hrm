<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormCheckbox from '@/Components/ui/FormCheckbox.vue';
import Alert from '@/Components/ui/Alert.vue';
import Avatar from '@/Components/ui/Avatar.vue';
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
    roles: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedUser = ref(null);
const selectedIds = ref([]);
const showBulkDelete = ref(false);

const columns = computed(() => [
    { key: 'select', label: '', cellClass: 'text-center w-[40px]' },
    { key: 'employee_code', label: t('users.employee_code'), sortable: true },
    { key: 'name', label: t('users.name'), sortable: true },
    { key: 'email', label: t('users.email') },
    { key: 'company', label: t('users.company') },
    { key: 'branch', label: t('users.branch') },
    { key: 'department', label: t('users.department') },
    { key: 'shift', label: t('users.shift') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[200px]' },
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

const employmentTypeOptions = [
    { value: 'full_time', label: t('users.employment_full_time') },
    { value: 'part_time', label: t('users.employment_part_time') },
    { value: 'contract', label: t('users.employment_contract') },
    { value: 'temporary', label: t('users.employment_temporary') },
    { value: 'intern', label: t('users.employment_intern') },
];

const allSelected = computed(() => {
    const items = props.users?.data || [];
    return items.length > 0 && selectedIds.value.length === items.length;
});

function onSearch(value) {
    router.get(
        route('users.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
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
    });
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

function toggleSelect(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
}

function toggleSelectAll() {
    const items = props.users?.data || [];
    if (selectedIds.value.length === items.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = items.map((u) => u.id);
    }
}

function confirmBulkDelete() {
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

watch(
    () => props.users,
    () => {
        const items = props.users?.data || [];
        selectedIds.value = selectedIds.value.filter((id) =>
            items.some((u) => u.id === id),
        );
    },
);

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('users.title')">
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

        <nav class="flex items-center gap-0 border-b border-mistral-hairline-soft overflow-x-auto mb-6" role="tablist">
            <Link
                :href="route('users.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-primary border-mistral-primary"
                role="tab"
                aria-selected="true"
            >
                {{ t('users.title') }}
            </Link>
            <Link
                :href="route('shift-categories.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.shift_categories') }}
            </Link>
            <Link
                :href="route('time-schedules.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.time_schedules_title') }}
            </Link>
            <Link
                :href="route('shifts.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.title') }}
            </Link>
            <Link
                :href="route('shift-assignments.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.shift_assignments') }}
            </Link>
        </nav>

        <div class="card p-6 mb-4">
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

        <DataTable :columns="columns" :data="users">
            <template #cell-select="{ row }">
                <div class="flex justify-center" @click.stop>
                    <FormCheckbox
                        :model-value="selectedIds.includes(row.id)"
                        :value="row.id"
                        :array-value="selectedIds"
                        @update:model-value="toggleSelect(row.id)"
                    />
                </div>
            </template>

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
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" variant="primary" :href="route('users.edit', row.id)" />
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
    </AppLayout>
</template>
