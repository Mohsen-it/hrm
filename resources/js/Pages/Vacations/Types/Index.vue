<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    types: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDelete = ref(false);
const selectedType = ref(null);

const columns = computed(() => [
    { key: 'code', label: t('vacations.type_code'), sortable: true },
    { key: 'name_ar', label: t('vacations.type_name_ar'), sortable: true },
    { key: 'name_en', label: t('vacations.type_name_en') },
    { key: 'default_days_per_year', label: t('vacations.days_per_year'), cellClass: 'text-center' },
    { key: 'is_paid', label: t('vacations.is_paid'), cellClass: 'text-center' },
    { key: 'is_active', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(route('vacations.types.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true, only: ['types'] });
}

function confirmDelete(type) {
    selectedType.value = type;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedType.value) return;
    router.delete(route('vacations.types.destroy', selectedType.value.id), { preserveScroll: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('vacations.vacation_types')">
        <PageHeader :title="t('vacations.vacation_types')" :description="t('vacations.types_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('vacations.types.create')">
                    {{ t('vacations.add_type') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="types"
            :filters="filters"
            :route-name="'vacations.types.index'"
            :only="['types']"
            storage-key="vacation-types"
            @search="onSearch"
        >
            <template #cell-is_paid="{ row }">
                <Badge v-if="row.is_paid" :text="t('common.yes')" variant="active" />
                <span v-else class="text-mistral-stone">—</span>
            </template>

            <template #cell-is_active="{ row }">
                <Badge v-if="row.is_active" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1.5">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('vacations.types.show', row.id)" />
                    <IconButton icon="fas fa-pen" :aria-label="t('common.edit')" :href="route('vacations.types.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('vacations.delete_type_confirm_title')"
            :message="t('vacations.delete_type_confirm_message', { name: selectedType?.name_ar })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
