<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
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
    requests: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');

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
    { key: 'status', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[120px]' },
]);

function onSearch(value) {
    router.get(route('vacations.requests.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true });
}

function applyFilter(key, value) {
    router.get(route('vacations.requests.index'), { ...props.filters, [key]: value }, { preserveState: true, preserveScroll: true, replace: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('vacations.vacation_requests')">
        <PageHeader :title="t('vacations.vacation_requests')" :description="t('vacations.requests_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('vacations.requests.create')">
                    {{ t('vacations.new_request') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <div class="card p-6 mb-4">
            <div class="flex items-center gap-3 flex-wrap">
                <SearchInput v-model="search" :placeholder="t('common.search')" @search="onSearch" />
                <FormSelect
                    :model-value="filters.status ?? ''"
                    :options="[
                        { value: '', label: t('common.all_statuses') },
                        { value: 'pending', label: t('vacations.pending') },
                        { value: 'approved', label: t('vacations.approved') },
                        { value: 'rejected', label: t('vacations.rejected') },
                        { value: 'cancelled', label: t('vacations.cancelled') },
                    ]"
                    class="max-w-[180px]"
                    @update:model-value="(v) => applyFilter('status', v)"
                />
            </div>
        </div>

        <DataTable :columns="columns" :data="requests">
            <template #cell-status="{ row }">
                <Badge :text="t('vacations.' + row.status)" :variant="statusVariant(row.status)" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1.5">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" variant="info" :href="route('vacations.requests.show', row.id)" />
                </div>
            </template>
        </DataTable>
    </AppLayout>
</template>
