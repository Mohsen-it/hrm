<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, Badge, Button, IconButton, Card, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    balances: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const statusVariant = (status) => {
    const map = { pending: 'pending', approved: 'active', rejected: 'inactive', cancelled: 'inactive' };
    return map[status] || 'pending';
};

const columns = computed(() => [
    { key: 'vacation_type.name_ar', label: t('vacations.vacation_type') },
    { key: 'start_date', label: t('vacations.start_date'), sortable: true },
    { key: 'end_date', label: t('vacations.end_date'), sortable: true },
    { key: 'total_days', label: t('vacations.total_days'), cellClass: 'text-center' },
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

function onFilterChange(key, value) {
    router.get(route('vacations.my.index'), { ...props.filters, [key]: value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPageChange(page) {
    router.get(route('vacations.my.index'), { ...props.filters, page }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPerPageChange(perPage) {
    router.get(route('vacations.my.index'), { ...props.filters, per_page: perPage, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('vacations.my_vacations')">
        <PageHeader :title="t('vacations.my_vacations')" :description="t('vacations.my_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('vacations.my.create')">
                    {{ t('vacations.new_request') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <div v-if="balances.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Card v-for="balance in balances" :key="balance.id" variant="base" padding="sm">
                <p class="text-[12px] text-mistral-steel mb-1">{{ balance.vacation_type?.name_ar || t('vacations.vacation') }}</p>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[20px] font-bold text-mistral-ink">{{ balance.remaining_days || 0 }}</p>
                        <p class="text-[11px] text-mistral-stone">{{ t('vacations.remaining') }}</p>
                    </div>
                    <div class="text-left">
                        <p class="text-[14px] text-mistral-steel">{{ balance.total_days || 0 }}</p>
                        <p class="text-[11px] text-mistral-stone">{{ t('vacations.total') }}</p>
                    </div>
                </div>
            </Card>
        </div>

        <DataTable
            :columns="columns"
            :data="requests"
            storage-key="my-vacations"
            @filter-change="onFilterChange"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
        >
            <template #cell-status="{ row }">
                <Badge :text="t('vacations.' + row.status)" :variant="statusVariant(row.status)" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1.5">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('vacations.my.show', row.id)" />
                </div>
            </template>
        </DataTable>
    </AppLayout>
</template>
