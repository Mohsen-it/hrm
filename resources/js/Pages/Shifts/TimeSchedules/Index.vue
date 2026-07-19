<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
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
    schedules: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDelete = ref(false);
const selectedSchedule = ref(null);

const columns = computed(() => [
    { key: 'name', label: t('shifts.schedule_name'), sortable: true },
    { key: 'in_time', label: t('shifts.in_time') },
    { key: 'out_time', label: t('shifts.out_time') },
    { key: 'is_multi_day', label: t('shifts.is_multi_day'), cellClass: 'text-center' },
    { key: 'late_margin', label: t('shifts.late_margin'), cellClass: 'text-center' },
    { key: 'early_margin', label: t('shifts.early_margin'), cellClass: 'text-center' },
    { key: 'linked_category', label: t('shifts.linked_category') },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[180px]' },
]);

function onSearch(value) {
    router.get(
        route('time-schedules.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onPageChange(page) {
    router.get(
        route('time-schedules.index'),
        { ...props.filters, page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onPerPageChange(perPage) {
    router.get(
        route('time-schedules.index'),
        { ...props.filters, per_page: perPage },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(schedule) {
    selectedSchedule.value = schedule;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedSchedule.value) return;
    router.delete(route('time-schedules.destroy', selectedSchedule.value.id), {
        preserveScroll: true,
    });
}

function copySchedule(schedule) {
    router.post(
        route('time-schedules.copy', schedule.id),
        { name: schedule.name + ' (' + t('shifts.copy_suffix') + ')' },
        { preserveScroll: true },
    );
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('shifts.time_schedules_title')">
        <PageHeader
            :title="t('shifts.time_schedules_title')"
            :description="t('shifts.time_schedules_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('shift-categories.index')" icon="fas fa-layer-group">
                    {{ t('shifts.shift_categories') }}
                </Button>
                <Button variant="primary" :href="route('time-schedules.create')" icon="fas fa-plus">
                    {{ t('shifts.add_schedule') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" dismissible class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" dismissible class="mb-4" />

        <DataTable
            :columns="columns"
            :data="schedules"
            storage-key="time-schedules"
            @search="onSearch"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
        >
            <template #cell-in_time="{ row }">
                <span dir="ltr">{{ row.in_time ? String(row.in_time).slice(0, 5) : '—' }}</span>
            </template>

            <template #cell-out_time="{ row }">
                <span dir="ltr">{{ row.out_time ? String(row.out_time).slice(0, 5) : '—' }}</span>
            </template>

            <template #cell-is_multi_day="{ row }">
                <Badge
                    v-if="row.is_multi_day"
                    :text="t('shifts.continuous')"
                    variant="active"
                />
                <Badge
                    v-else
                    :text="t('shifts.daily')"
                    variant="inactive"
                />
            </template>

            <template #cell-late_margin="{ row }">
                <span>{{ row.late_margin ?? 0 }}</span>
            </template>

            <template #cell-early_margin="{ row }">
                <span>{{ row.early_margin ?? 0 }}</span>
            </template>

            <template #cell-linked_category="{ row }">
                <Link
                    v-if="row.linked_category_id"
                    :href="route('shift-categories.show', row.linked_category_id)"
                    class="text-mistral-primary hover:underline"
                >
                    {{ row.linked_category_name }}
                </Link>
                <span v-else class="text-mistral-muted">—</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton
                        icon="fas fa-eye"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.view')"
                        :href="route('time-schedules.show', row.id)"
                    />
                    <IconButton
                        icon="fas fa-edit"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.edit')"
                        :href="route('time-schedules.edit', row.id)"
                    />
                    <IconButton
                        icon="fas fa-copy"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('shifts.copy')"
                        @click="copySchedule(row)"
                    />
                    <IconButton
                        icon="fas fa-trash"
                        variant="danger"
                        size="sm"
                        :aria-label="t('common.delete')"
                        @click="confirmDelete(row)"
                    />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('shifts.delete_schedule_confirm_title')"
            :message="t('shifts.delete_schedule_confirm_message', { name: selectedSchedule?.name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
