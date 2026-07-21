<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, Card, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();
const page = usePage();

const props = defineProps({
    holidays: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    upcoming: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedHoliday = ref(null);

const displayName = (h) => locale.value === 'en' && h.name_en ? h.name_en : h.name_ar;

const columns = computed(() => [
    { key: 'name_ar', label: t('holidays.name_ar'), sortable: true },
    { key: 'name_en', label: t('holidays.name_en') },
    { key: 'date', label: t('holidays.date'), sortable: true },
    { key: 'category', label: t('holidays.category') },
    { key: 'is_recurring', label: t('holidays.recurring'), cellClass: 'text-center' },
    {
        key: 'is_active',
        label: t('common.status'),
        cellClass: 'text-center',
        filterable: true,
        filterType: 'select',
        filterOptions: [
            { value: '', label: t('common.all_statuses') },
            { value: '1', label: t('common.active') },
            { value: '0', label: t('common.inactive') },
        ],
    },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(route('holidays.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true, only: ['holidays'] });
}

function onFilterChange(filters) {
    router.get(route('holidays.index'), { ...props.filters, ...filters }, { preserveState: true, preserveScroll: true, replace: true, only: ['holidays'] });
}

function confirmDelete(holiday) {
    selectedHoliday.value = holiday;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedHoliday.value) return;
    router.delete(route('holidays.destroy', selectedHoliday.value.id), { preserveScroll: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('holidays.title')">
        <PageHeader :title="t('holidays.title')" :description="t('holidays.index_description')">
            <template #actions>
                <Button
                    variant="secondary"
                    icon="fas fa-sync"
                    @click="router.post(route('holidays.sync'), {}, { preserveScroll: true })"
                >
                    {{ t('holidays.sync') }}
                </Button>
                <Button variant="primary" icon="fas fa-plus" :href="route('holidays.create')">
                    {{ t('holidays.add_holiday') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <Card v-if="upcoming.length > 0" variant="base" padding="none" class="mb-4">
            <div class="p-5 sm:p-6">
                <h3 class="text-[14px] font-semibold text-mistral-ink mb-3 flex items-center gap-2">
                    <i class="fas fa-calendar-day text-mistral-primary"></i>
                    {{ t('holidays.upcoming') }}
                </h3>
                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="h in upcoming.slice(0, 6)"
                        :key="h.id + '-' + h.date"
                        class="px-3 py-2 rounded-md bg-mistral-surface border border-mistral-hairline text-[13px]"
                    >
                        <span class="font-medium">{{ displayName(h) }}</span>
                        <span class="text-mistral-steel ms-2">{{ h.date }}</span>
                    </div>
                </div>
            </div>
        </Card>

        <DataTable
            :columns="columns"
            :data="holidays"
            :filters="filters"
            :route-name="'holidays.index'"
            :only="['holidays']"
            storage-key="holidays"
            @search="onSearch"
            @filter-change="onFilterChange"
        >
            <template #cell-is_recurring="{ row }">
                <Badge v-if="row.is_recurring" :text="t('holidays.yes_recurring')" variant="info" />
                <span v-else class="text-mistral-stone">—</span>
            </template>

            <template #cell-is_active="{ row }">
                <Badge v-if="row.is_active" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('holidays.show', row.id)" />
                    <IconButton icon="fas fa-pen" :aria-label="t('common.edit')" :href="route('holidays.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('holidays.delete_confirm_title')"
            :message="t('holidays.delete_confirm_message', { name: selectedHoliday?.name_ar })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
