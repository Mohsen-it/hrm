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
    holidays: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    upcoming: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedHoliday = ref(null);

const displayName = (h) => locale.value === 'en' && h.name_en ? h.name_en : h.name_ar;

const columns = computed(() => [
    { key: 'name_ar', label: t('holidays.name_ar'), sortable: true },
    { key: 'name_en', label: t('holidays.name_en') },
    { key: 'date', label: t('holidays.date'), sortable: true },
    { key: 'category', label: t('holidays.category') },
    { key: 'is_recurring', label: t('holidays.recurring'), cellClass: 'text-center' },
    { key: 'is_active', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(route('holidays.index'), { ...props.filters, search: value }, { preserveState: true, preserveScroll: true, replace: true });
}

function applyFilter(key, value) {
    router.get(route('holidays.index'), { ...props.filters, [key]: value }, { preserveState: true, preserveScroll: true, replace: true });
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

        <Card v-if="upcoming.length > 0" variant="base" padding="md" class="mb-4">
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
        </Card>

        <div class="card p-6 mb-4">
            <div class="flex items-center gap-3 flex-wrap">
                <SearchInput v-model="search" :placeholder="t('common.search')" @search="onSearch" />
                <FormSelect
                    :model-value="filters.is_active ?? ''"
                    :options="[
                        { value: '', label: t('common.all_statuses') },
                        { value: '1', label: t('common.active') },
                        { value: '0', label: t('common.inactive') },
                    ]"
                    class="max-w-[180px]"
                    @update:model-value="(v) => applyFilter('is_active', v)"
                />
            </div>
        </div>

        <DataTable :columns="columns" :data="holidays">
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
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('holidays.edit', row.id)" />
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
