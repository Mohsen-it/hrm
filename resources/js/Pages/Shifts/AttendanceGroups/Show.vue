<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, DataTable, Badge, IconButton, ConfirmDialog } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    group: { type: Object, required: true },
    employees: { type: Array, default: () => [] },
});

const showRemoveDialog = ref(false);
const removingEmployee = ref(null);

const employeeColumns = [
    { key: 'employee_code', label: t('attendance.fields.employee_code') },
    { key: 'name', label: t('attendance.fields.employee') },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[100px]' },
];

const confirmRemoveEmployee = (employee) => {
    removingEmployee.value = employee;
    showRemoveDialog.value = true;
};

const removeEmployee = () => {
    router.delete(route('attendance.groups.remove-employee', [props.group.id, removingEmployee.value.id]));
    showRemoveDialog.value = false;
    removingEmployee.value = null;
};
</script>

<template>
    <AppLayout :title="t('attendance.attendance_group', 'فئة الحضور') + ': ' + group.name">
        <PageHeader
            :title="group.name"
            :description="group.code"
        >
            <template #actions>
                <Button variant="secondary" :href="route('attendance.groups.index')">
                    {{ t('common.back') }}
                </Button>
                <Button variant="primary" :href="route('attendance.groups.edit', group.id)" icon="fas fa-edit">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[14px] font-semibold text-mistral-ink mb-4">{{ t('attendance.fields.details') }}</h3>
                    <dl class="space-y-3">
                        <div class="flex flex-col">
                            <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('attendance.fields.code') }}</dt>
                            <dd class="text-[14px] text-mistral-ink mt-1 font-medium">{{ group.code }}</dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('attendance.fields.name') }}</dt>
                            <dd class="text-[14px] text-mistral-ink mt-1 font-medium">{{ group.name }}</dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('attendance.fields.status') }}</dt>
                            <dd class="mt-1">
                                <Badge
                                    :text="group.status === 1 ? t('common.active') : t('common.inactive')"
                                    :variant="group.status === 1 ? 'active' : 'inactive'"
                                />
                            </dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('attendance.fields.employees_count') }}</dt>
                            <dd class="text-[14px] text-mistral-ink mt-1 font-medium">{{ employees.length }}</dd>
                        </div>
                    </dl>
                </div>
            </Card>

            <Card variant="base" padding="none" class="lg:col-span-2">
                <div class="px-5 sm:px-6 py-4 border-b border-mistral-hairline-soft flex items-center justify-between">
                    <h3 class="text-[14px] font-semibold text-mistral-ink">{{ t('attendance.fields.employees') }}</h3>
                    <Button variant="primary" size="sm" :href="route('attendance.groups.assign-employee', group.id)" icon="fas fa-user-plus">
                        {{ t('attendance.actions.assign_employee') }}
                    </Button>
                </div>

                <DataTable
                    :columns="employeeColumns"
                    :data="employees"
                    storage-key="attendance-group-employees"
                    enable-search="false"
                    enable-filters="false"
                    enable-pagination="false"
                >
                    <template #cell-name="{ row }">
                        <span class="text-[13px]">{{ row.employee?.name }}</span>
                    </template>

                    <template #cell-employee_code="{ row }">
                        <span class="text-[13px] font-mono">{{ row.employee?.employee_code }}</span>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-center gap-1">
                            <IconButton
                                icon="fas fa-trash"
                                variant="danger"
                                size="sm"
                                :aria-label="t('common.delete')"
                                @click="confirmRemoveEmployee(row)"
                            />
                        </div>
                    </template>
                </DataTable>
            </Card>
        </div>

        <ConfirmDialog
            v-model="showRemoveDialog"
            :title="t('attendance.messages.delete_confirm_title', 'تأكيد الحذف')"
            :message="t('attendance.messages.remove_employee_confirm', 'هل أنت متأكد من إزالة الموظف؟')"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="removeEmployee"
        />
    </AppLayout>
</template>
