<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, DataTable, IconButton, ConfirmDialog } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    schedules: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDeleteDialog = ref(false);
const deletingSchedule = ref(null);

const columns = [
    { key: 'group', label: t('attendance.fields.group') },
    { key: 'shift', label: t('attendance.fields.shift') },
    { key: 'start_date', label: t('attendance.fields.start_date') },
    { key: 'end_date', label: t('attendance.fields.end_date') },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[150px]' },
];

function onSearch(value) {
    router.get(
        route('attendance.group-schedules.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function handlePageChange(page) {
    router.get(
        route('attendance.group-schedules.index'),
        { ...props.filters, page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function handlePerPageChange(perPage) {
    router.get(
        route('attendance.group-schedules.index'),
        { ...props.filters, per_page: perPage },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const confirmDelete = (schedule) => {
    deletingSchedule.value = schedule;
    showDeleteDialog.value = true;
};

const deleteSchedule = () => {
    router.delete(route('attendance.group-schedules.destroy', deletingSchedule.value.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
            deletingSchedule.value = null;
        },
    });
};
</script>

<template>
    <AppLayout :title="t('attendance.group_schedules')">
        <PageHeader
            :title="t('attendance.group_schedules')"
            :description="t('attendance.group_schedules_description', 'جدول فئات الحضور')"
        >
            <template #actions>
                <Button variant="primary" :href="route('attendance.group-schedules.create')" icon="fas fa-plus">
                    {{ t('attendance.create_group_schedule', 'إنشاء جدول فئة') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <DataTable
                :columns="columns"
                :data="schedules"
                storage-key="group-schedules"
                @search="onSearch"
                @page-change="handlePageChange"
                @per-page-change="handlePerPageChange"
            >
                <template #cell-group="{ row }">
                    <span class="text-[13px] font-medium">{{ row.group?.name }}</span>
                </template>

                <template #cell-shift="{ row }">
                    <span class="text-[13px]">{{ row.shift?.alias }}</span>
                </template>

                <template #cell-start_date="{ row }">
                    <span class="text-[13px]">{{ row.start_date }}</span>
                </template>

                <template #cell-end_date="{ row }">
                    <span class="text-[13px]">{{ row.end_date }}</span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-center gap-1">
                        <IconButton
                            icon="fas fa-eye"
                            variant="ghost"
                            size="sm"
                            :aria-label="t('common.view')"
                            :href="route('attendance.group-schedules.show', row.id)"
                        />
                        <IconButton
                            icon="fas fa-edit"
                            variant="ghost"
                            size="sm"
                            :aria-label="t('common.edit')"
                            :href="route('attendance.group-schedules.edit', row.id)"
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
        </Card>

        <ConfirmDialog
            v-model="showDeleteDialog"
            :title="t('attendance.messages.delete_confirm_title', 'تأكيد الحذف')"
            :message="t('attendance.messages.delete_confirm_message', { name: deletingSchedule?.group?.name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="deleteSchedule"
        />
    </AppLayout>
</template>
