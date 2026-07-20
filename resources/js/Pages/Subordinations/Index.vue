<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, Card, IconButton, Alert, EmptyState } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();
const page = usePage();

const props = defineProps({
    subordinations: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDelete = ref(false);
const selectedSubordination = ref(null);

const displayName = (s) => {
    if (locale.value === 'en' && s.name_en) return s.name_en;
    return s.name_ar || s.name_en || s.code;
};

const columns = computed(() => [
    { key: 'code', label: t('subordinations.code'), sortable: true },
    { key: 'name_ar', label: t('subordinations.name_ar'), sortable: true },
    { key: 'name_en', label: t('subordinations.name_en') },
    { key: 'sort_order', label: t('subordinations.sort_order'), cellClass: 'text-center' },
    {
        key: 'status',
        label: t('subordinations.status'),
        cellClass: 'text-center',
    },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(route('subordinations.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true });
}

function onFilterChange(filters) {
    router.get(route('subordinations.index'), { ...props.filters, ...filters }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPageChange(pageNum) {
    router.get(route('subordinations.index'), { ...props.filters, page: pageNum }, { preserveState: true, preserveScroll: true, replace: true });
}

function onPerPageChange(perPage) {
    router.get(route('subordinations.index'), { ...props.filters, per_page: perPage }, { preserveState: true, preserveScroll: true, replace: true });
}

function confirmDelete(sub) {
    selectedSubordination.value = sub;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedSubordination.value) return;
    router.delete(route('subordinations.destroy', selectedSubordination.value.id), { preserveScroll: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
const isEmpty = computed(() => !props.subordinations?.data?.length && !props.filters?.search);
</script>

<template>
    <AppLayout :title="t('subordinations.title')">
        <PageHeader :title="t('subordinations.title')" :description="t('subordinations.index_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('subordinations.create')">
                    {{ t('subordinations.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <EmptyState
            v-if="isEmpty"
            :title="t('subordinations.no_subordinations_found')"
            :action-text="t('subordinations.add_new')"
            :action-href="route('subordinations.create')"
            icon="fa-solid fa-map-location-dot"
            class="mb-4"
        />

        <DataTable
            v-else
            :columns="columns"
            :data="subordinations"
            storage-key="subordinations"
            @search="onSearch"
            @filter-change="onFilterChange"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
        >
            <template #cell-name_ar="{ row }">
                <div class="font-medium text-mistral-ink">{{ displayName(row) }}</div>
                <div v-if="row.name_en && locale !== 'en'" class="text-[12px] text-mistral-steel">{{ row.name_en }}</div>
            </template>

            <template #cell-status="{ row }">
                <Badge v-if="row.status === 1" :text="t('subordinations.active')" variant="active" />
                <Badge v-else :text="t('subordinations.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('subordinations.show', row.id)" />
                    <IconButton icon="fas fa-pen" :aria-label="t('common.edit')" :href="route('subordinations.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('subordinations.delete_confirm_title')"
            :message="t('subordinations.delete_confirm_message', { name: selectedSubordination?.name_ar })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
