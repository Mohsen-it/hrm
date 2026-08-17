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
import { PageHeader, DataTable, Badge, Button, IconButton, Alert, ConfirmDialog } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();
const showDelete = ref(false);
const selectedRequest = ref(null);

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
});

const statusVariant = (status) => {
    const map = { pending: 'pending', approved: 'active', rejected: 'inactive', cancelled: 'inactive' };
    return map[status] || 'pending';
};

const columns = computed(() => [
    { key: 'user.name', label: t('vacations.employee'), sortable: true },
    { key: 'vacation_type.name_ar', label: t('vacations.vacation_type') },
    { key: 'start_date', label: t('vacations.start_date'), sortable: true },
    { key: 'end_date', label: t('vacations.end_date'), sortable: true },
    { key: 'total_days', label: t('vacations.total_days'), cellClass: 'text-center' },
    { key: 'created_at', label: t('vacations.created_updated_at'), sortable: true },
    {
        key: 'status',
        label: t('common.status'),
        cellClass: 'text-center',
        filterable: true,
        filterType: 'select',
        filterOptions: [
            { value: 'pending', label: t('vacations.pending') },
            { value: 'approved', label: t('vacations.approved') },
            { value: 'rejected', label: t('vacations.rejected') },
            { value: 'cancelled', label: t('vacations.cancelled') },
        ],
    },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[120px]' },
]);

function onSearch(value) {
    router.get(route('vacations.requests.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true, only: ['requests'] });
}

function onFilterChange(key, value) {
    router.get(route('vacations.requests.index'), { ...props.filters, [key]: value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true, only: ['requests'] });
}

function editRequest(request) {
    if (request.status !== 'pending') return;
    router.get(route('vacations.requests.edit', request.id));
}

function confirmDelete(request) {
    selectedRequest.value = request;
    showDelete.value = true;
}

function deleteRequest() {
    if (!selectedRequest.value) return;

    router.delete(route('vacations.requests.destroy', selectedRequest.value.id), {
        preserveScroll: true,
        onFinish: () => {
            showDelete.value = false;
            selectedRequest.value = null;
        },
    });
}

const flashSuccess = computed(() => page.props.flash?.success);


usePageTitle(t('vacations.vacation_requests'));
</script>

<template>
    
        <PageHeader :title="t('vacations.vacation_requests')" :description="t('vacations.requests_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('vacations.requests.create')">
                    {{ t('vacations.new_request') }}
                </Button>
            </template>
        </PageHeader>

        <nav class="mb-6 flex gap-5 border-b border-mistral-hairline" aria-label="أنواع الطلبات">
            <span class="border-b-2 border-mistral-primary pb-3 text-sm font-medium text-mistral-primary">الإجازات</span>
            <a :href="route('vacations.justifications.index')" class="pb-3 text-sm text-mistral-steel">التبرير</a>
        </nav>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="requests"
            :filters="filters"
            :route-name="'vacations.requests.index'"
            :only="['requests']"
            storage-key="vacation-requests"
            @search="onSearch"
            @filter-change="onFilterChange"
        >
            <template #cell-status="{ row }">
                <Badge :text="t('vacations.' + row.status)" :variant="statusVariant(row.status)" />
            </template>

            <template #cell-created_at="{ row }">
                <div class="flex items-center justify-end gap-1.5" dir="ltr">
                    <span>{{ row.created_at }}</span>
                    <span v-if="row.updated_at && row.updated_at !== row.created_at" class="flex items-center gap-1 text-[11px] text-mistral-muted">
                        <i class="fas fa-pen-to-square"></i>
                        <span>{{ row.updated_at }}</span>
                    </span>
                </div>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1.5">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('vacations.requests.show', row.id)" />
                    <IconButton
                        v-if="row.status === 'pending'"
                        icon="fas fa-pen"
                        :aria-label="t('common.edit')"
                        @click="editRequest(row)"
                    />
                    <IconButton
                        icon="fas fa-trash"
                        variant="danger"
                        :aria-label="t('common.delete')"
                        @click="confirmDelete(row)"
                    />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('common.confirm_delete')"
            :message="t('vacations.delete_request_confirm_message')"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="deleteRequest"
        />
    </template>
