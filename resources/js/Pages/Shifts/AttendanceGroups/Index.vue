<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, DataTable, Badge, IconButton, ConfirmDialog } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    groups: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showDeleteDialog = ref(false);
const deletingGroup = ref(null);

const columns = [
    { key: 'code', label: t('attendance.fields.code'), sortable: true, filterable: true },
    { key: 'name', label: t('attendance.fields.name'), sortable: true, filterable: true },
    { key: 'employees_count', label: t('attendance.fields.employees_count'), cellClass: 'text-center' },
    { key: 'status', label: t('attendance.fields.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[150px]' },
];

function onSearch(value) {
    router.get(
        route('attendance.groups.index'),
        { search: value },
        { preserveState: true, preserveScroll: true, replace: true, only: ['groups'] },
    );
}

const confirmDelete = (group) => {
    deletingGroup.value = group;
    showDeleteDialog.value = true;
};

const deleteGroup = () => {
    router.delete(route('attendance.groups.destroy', deletingGroup.value.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
            deletingGroup.value = null;
        },
    });
};
</script>

<template>
    <AppLayout :title="t('attendance.attendance_groups')">
        <PageHeader
            :title="t('attendance.attendance_groups')"
            :description="t('attendance.attendance_groups_description', 'فئات الحضور والانصراف')"
        >
            <template #actions>
                <Button variant="primary" :href="route('attendance.groups.create')" icon="fas fa-plus">
                    {{ t('attendance.create_attendance_group', 'إنشاء فئة حضور') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <DataTable
                :columns="columns"
                :data="groups"
                :filters="filters"
                :route-name="'attendance.groups.index'"
            :only="['groups']"
                storage-key="attendance-groups"
                @search="onSearch"
            >
                <template #cell-code="{ row }">
                    <span class="text-[13px] font-medium">{{ row.code }}</span>
                </template>

                <template #cell-name="{ row }">
                    <span class="text-[13px]">{{ row.name }}</span>
                </template>

                <template #cell-employees_count="{ row }">
                    <Badge :text="String(row.employees?.length ?? 0)" variant="info" />
                </template>

                <template #cell-status="{ row }">
                    <Badge
                        :text="row.status === 1 ? t('common.active') : t('common.inactive')"
                        :variant="row.status === 1 ? 'active' : 'inactive'"
                    />
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-center gap-1">
                        <IconButton
                            icon="fas fa-eye"
                            variant="ghost"
                            size="sm"
                            :aria-label="t('common.view')"
                            :href="route('attendance.groups.show', row.id)"
                        />
                        <IconButton
                            icon="fas fa-edit"
                            variant="ghost"
                            size="sm"
                            :aria-label="t('common.edit')"
                            :href="route('attendance.groups.edit', row.id)"
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
            :message="t('attendance.messages.delete_confirm_message', { name: deletingGroup?.name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="deleteGroup"
        />
    </AppLayout>
</template>
