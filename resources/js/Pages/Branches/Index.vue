<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
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
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    branches: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedBranch = ref(null);

const columns = computed(() => [
    { key: 'branch_code', label: t('branches.code'), sortable: true },
    { key: 'branch_name', label: t('branches.name'), sortable: true },
    { key: 'company', label: t('branches.company') },
    { key: 'phone', label: t('branches.phone') },
    { key: 'city', label: t('branches.city') },
    { key: 'manager_name', label: t('branches.manager_name') },
    { key: 'is_main', label: t('branches.main'), cellClass: 'text-center' },
    { key: 'status', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

const companyOptions = computed(() => [
    { value: '', label: t('branches.select_company') },
    ...props.companies.map((c) => ({ value: c.id, label: c.company_name })),
]);

function onSearch(value) {
    router.get(
        route('branches.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function applyFilter(key, value) {
    router.get(
        route('branches.index'),
        { ...props.filters, [key]: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(branch) {
    selectedBranch.value = branch;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedBranch.value) return;
    router.delete(route('branches.destroy', selectedBranch.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('branches.title')">
        <PageHeader
            :title="t('branches.title')"
            :description="t('branches.index_description')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('branches.create')">
                    {{ t('branches.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <div class="card p-6 mb-4"">
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
                        class="max-w-[220px]"
                        @update:model-value="(v) => applyFilter('company_id', v)"
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

        <DataTable :columns="columns" :data="branches">
            <template #cell-company="{ row }">
                <span>{{ row.company?.company_name || '—' }}</span>
            </template>

            <template #cell-is_main="{ row }">
                <Badge v-if="row.is_main" :text="t('branches.main')" variant="info" />
                <span v-else class="text-mistral-muted">—</span>
            </template>

            <template #cell-status="{ row }">
                <Badge v-if="row.status === 1" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('branches.show', row.id)" />
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('branches.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('branches.delete_confirm_title')"
            :message="t('branches.delete_confirm_message', { name: selectedBranch?.branch_name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
