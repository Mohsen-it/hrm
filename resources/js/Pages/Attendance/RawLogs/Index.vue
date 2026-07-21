<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    logs: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedLog = ref(null);
let pollHandle = null;

const punchTypeOptions = [
    { value: 'check_in', label: t('attendance.punch_type.check_in') },
    { value: 'check_out', label: t('attendance.punch_type.check_out') },
    { value: 'unknown', label: t('attendance.punch_type.unknown') },
];

const sourceOptions = [
    { value: 'device', label: t('attendance.source.device') },
    { value: 'adms', label: t('attendance.source.adms') },
    { value: 'manual', label: t('attendance.source.manual') },
    { value: 'api', label: t('attendance.source.api') },
];

const processedOptions = [
    { value: '1', label: t('common.yes') },
    { value: '0', label: t('common.no') },
];

const userFilterOptions = computed(() =>
    props.users.map((u) => ({ value: u.id, label: u.name })),
);

const columns = computed(() => [
    { key: 'punch_time', label: t('attendance.fields.punch_time'), sortable: true },
    { key: 'user', label: t('attendance.fields.user'), filterable: true, filterType: 'select', filterOptions: userFilterOptions.value, filterKey: 'user_id' },
    { key: 'device_user_id', label: t('attendance.fields.device_user_id') },
    { key: 'punch_type', label: t('attendance.fields.punch_type'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: punchTypeOptions, filterKey: 'punch_type' },
    { key: 'source', label: t('attendance.fields.source'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: sourceOptions, filterKey: 'source' },
    { key: 'processed', label: t('attendance.fields.processed'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: processedOptions, filterKey: 'processed' },
    { key: 'from', label: t('attendance.fields.from'), filterable: true, filterType: 'date', filterKey: 'from' },
    { key: 'to', label: t('attendance.fields.to'), filterable: true, filterType: 'date', filterKey: 'to' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(
        route('attendance.raw-logs.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true, only: ['logs'] },
    );
}

function onFilterChange(filters) {
    const next = { ...props.filters };
    Object.entries(filters).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) {
            delete next[key];
        } else {
            next[key] = value;
        }
    });
    router.get(route('attendance.raw-logs.index'), next, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['logs'],
    });
}

function confirmDelete(log) {
    selectedLog.value = log;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedLog.value) return;
    router.delete(route('attendance.raw-logs.destroy', selectedLog.value.id), {
        preserveScroll: true,
    });
}

function processAll() {
    router.post(route('attendance.raw-logs.process-all'), { chunk_size: 200 }, {
        preserveScroll: true,
    });
}

function markProcessed(id) {
    router.post(route('attendance.raw-logs.mark-processed', id), {}, {
        preserveScroll: true,
    });
}

function poll() {
    router.reload({
        only: ['logs'],
        preserveScroll: true,
        preserveState: true,
    });
}

onMounted(() => {
    pollHandle = setInterval(poll, 10000);
});

onBeforeUnmount(() => {
    if (pollHandle) clearInterval(pollHandle);
});

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('attendance.raw_logs')">
        <PageHeader
            :title="t('attendance.raw_logs')"
            :description="t('attendance.index_description')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-cogs" @click="processAll">
                    {{ t('attendance.actions.process_all') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="logs"
            :filters="filters"
            :route-name="'attendance.raw-logs.index'"
            :only="['logs']"
            :empty-title="t('attendance.messages.empty_logs')"
            storage-key="attendance-raw-logs"
            @search="onSearch"
            @filter-change="onFilterChange"
        >
            <template #cell-punch_time="{ row }">
                <span dir="ltr" class="text-[12px]">{{ row.punch_time }}</span>
            </template>
            <template #cell-user="{ row }">
                <span>{{ row.user?.name || '—' }}</span>
            </template>
            <template #cell-punch_type="{ row }">
                <Badge
                    :text="t(`attendance.punch_type.${row.punch_type}`, row.punch_type)"
                    :variant="row.punch_type === 'check_in' ? 'active' : 'info'"
                />
            </template>
            <template #cell-source="{ row }">
                <Badge :text="t(`attendance.source.${row.source}`, row.source)" variant="info" />
            </template>
            <template #cell-processed="{ row }">
                <Badge
                    :text="row.processed ? t('common.yes') : t('common.no')"
                    :variant="row.processed ? 'active' : 'pending'"
                />
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1.5">
                    <IconButton icon="fas fa-eye" :aria-label="t('attendance.actions.view')" variant="info" :href="route('attendance.raw-logs.show', row.id)" />
                    <IconButton
                        v-if="!row.processed"
                        icon="fas fa-check"
                        :aria-label="t('attendance.actions.mark_processed')"
                        variant="success"
                        @click="markProcessed(row.id)"
                    />
                    <IconButton icon="fas fa-trash" :aria-label="t('attendance.actions.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('attendance.messages.delete_confirm_title')"
            :message="t('attendance.messages.delete_confirm_message', { name: t('attendance.raw_log') })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
