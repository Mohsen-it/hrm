<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, DataTable, Badge, IconButton, ConfirmDialog } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    shifts: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDeleteDialog = ref(false);
const deletingShift = ref(null);

const columns = [
    { key: 'alias', label: t('attendance.fields.alias'), sortable: true },
    { key: 'cycle_unit', label: t('attendance.fields.cycle_unit'), cellClass: 'text-center' },
    { key: 'shift_cycle', label: t('attendance.fields.shift_cycle'), cellClass: 'text-center' },
    { key: 'details_count', label: t('attendance.fields.details'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[150px]' },
];

const formatCycleUnit = (unit) => {
    const units = { 1: 'يومي', 2: 'أسبوعي', 3: 'شهري' };
    return units[unit] || unit;
};

function onSearch(value) {
    router.get(route('attendance.shifts.index'), { search: value }, { preserveState: true });
}

function onPageChange(page) {
    router.get(route('attendance.shifts.index', { page }));
}

function onPerPageChange(perPage) {
    router.get(route('attendance.shifts.index', { per_page: perPage }));
}

const confirmDelete = (shift) => {
    deletingShift.value = shift;
    showDeleteDialog.value = true;
};

const deleteShift = () => {
    router.delete(route('attendance.shifts.destroy', deletingShift.value.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
            deletingShift.value = null;
        },
    });
};
</script>

<template>
    <AppLayout :title="t('attendance.attendance_shifts')">
        <PageHeader
            :title="t('attendance.attendance_shifts')"
            :description="t('attendance.attendance_shifts_description', 'مناوبات الحضور والانصراف')"
        >
            <template #actions>
                <Button variant="primary" :href="route('attendance.shifts.create')" icon="fas fa-plus">
                    {{ t('attendance.create_attendance_shift', 'إنشاء مناوبة') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <DataTable
                :columns="columns"
                :data="shifts"
                storage-key="attendance-shifts"
                @search="onSearch"
                @page-change="onPageChange"
                @per-page-change="onPerPageChange"
            >
                <template #cell-alias="{ row }">
                    <span class="text-[13px] font-medium">{{ row.alias }}</span>
                </template>

                <template #cell-cycle_unit="{ row }">
                    <span class="text-[13px]">{{ formatCycleUnit(row.cycle_unit) }}</span>
                </template>

                <template #cell-shift_cycle="{ row }">
                    <span class="text-[13px]">{{ row.shift_cycle }}</span>
                </template>

                <template #cell-details_count="{ row }">
                    <Badge :text="String(row.details?.length ?? 0)" variant="info" />
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-center gap-1">
                        <IconButton
                            icon="fas fa-eye"
                            variant="ghost"
                            size="sm"
                            :aria-label="t('common.view')"
                            :href="route('attendance.shifts.show', row.id)"
                        />
                        <IconButton
                            icon="fas fa-edit"
                            variant="ghost"
                            size="sm"
                            :aria-label="t('common.edit')"
                            :href="route('attendance.shifts.edit', row.id)"
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
            :message="t('attendance.messages.delete_confirm_message', { name: deletingShift?.alias })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="deleteShift"
        />
    </AppLayout>
</template>
