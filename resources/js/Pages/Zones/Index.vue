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

const { t, locale } = useTranslations();
const page = usePage();

const props = defineProps({
    zones: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedZone = ref(null);

const displayName = (z) => locale.value === 'en' && z.name_en ? z.name_en : z.name_ar;

const zoneTypeOptions = [
    { value: '', label: t('common.all') },
    { value: 'geographic', label: t('zones.zone_type_geographic') },
    { value: 'operational', label: t('zones.zone_type_operational') },
    { value: 'security', label: t('zones.zone_type_security') },
    { value: 'sales', label: t('zones.zone_type_sales') },
    { value: 'logistics', label: t('zones.zone_type_logistics') },
];

const statusOptions = [
    { value: '', label: t('common.all_statuses') },
    { value: '1', label: t('common.active') },
    { value: '0', label: t('common.inactive') },
];

const columns = computed(() => [
    { key: 'code', label: t('zones.code'), sortable: true },
    { key: 'name_ar', label: t('zones.name_ar') },
    { key: 'name_en', label: t('zones.name_en') },
    { key: 'zone_type', label: t('zones.zone_type') },
    { key: 'city', label: t('zones.city') },
    { key: 'branches_count', label: t('zones.branches'), cellClass: 'text-center' },
    { key: 'is_active', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[200px]' },
]);

function onSearch(value) {
    router.get(route('zones.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true });
}

function applyFilter(key, value) {
    const filters = { ...props.filters };
    if (value === '' || value === null) {
        delete filters[key];
    } else {
        filters[key] = value;
    }
    router.get(route('zones.index'), filters, { preserveState: true, preserveScroll: true, replace: true });
}

function confirmDelete(zone) {
    selectedZone.value = zone;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedZone.value) return;
    router.delete(route('zones.destroy', selectedZone.value.id), { preserveScroll: true });
}

const flashSuccess = computed(() => page.props.flash?.success);

function zoneTypeLabel(value) {
    const match = zoneTypeOptions.find((o) => o.value === value);
    return match ? match.label : value;
}
</script>

<template>
    <AppLayout :title="t('zones.title')">
        <PageHeader :title="t('zones.title')" :description="t('zones.index_description')">
            <template #actions>
                <Button variant="secondary" icon="fas fa-chart-pie" :href="route('zones.dashboard')">
                    {{ t('zones.title') }} · Dashboard
                </Button>
                <Button variant="primary" icon="fas fa-plus" :href="route('zones.create')">
                    {{ t('zones.add_zone') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <div class="card p-6 mb-4"">
            <div class="flex items-center gap-3 flex-wrap">
                <SearchInput v-model="search" :placeholder="t('common.search')" @search="onSearch" />
                <FormSelect
                    :model-value="filters.zone_type ?? ''"
                    :options="zoneTypeOptions"
                    class="max-w-[200px]"
                    @update:model-value="(v) => applyFilter('zone_type', v)"
                />
                <FormSelect
                    :model-value="filters.is_active ?? ''"
                    :options="statusOptions"
                    class="max-w-[180px]"
                    @update:model-value="(v) => applyFilter('is_active', v)"
                />
            </div>
        </div>

        <DataTable :columns="columns" :data="zones">
            <template #cell-name_ar="{ row }">
                <a :href="route('zones.show', row.id)" class="font-medium text-mistral-primary hover:underline">
                    {{ row.name_ar }}
                </a>
            </template>
            <template #cell-zone_type="{ row }">
                <Badge :text="zoneTypeLabel(row.zone_type)" variant="info" />
            </template>
            <template #cell-is_active="{ row }">
                <Badge v-if="row.is_active" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('zones.show', row.id)" />
                    <IconButton icon="fas fa-code-branch" :aria-label="t('zones.manage_branches')" :href="route('zones.branches', row.id)" />
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('zones.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('zones.delete_confirm_title')"
            :message="t('zones.delete_confirm_message', { name: selectedZone?.name_ar })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
