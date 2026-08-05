<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Badge, Button, Card, DataTable, IconButton, PageHeader } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    users: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const columns = computed(() => [
    { key: 'employee_code', label: t('users.employee_code'), sortable: true },
    { key: 'name', label: t('users.name'), sortable: true },
    { key: 'branch', label: t('branches.branch'), sortable: false },
    { key: 'department', label: t('departments.department'), sortable: false },
    { key: 'status', label: t('common.status'), cellClass: 'text-center', sortable: true },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[100px]', sortable: false },
]);

function searchUsers(search) {
    router.get(
        route('attendance.reports.user.index'),
        { search },
        { preserveState: true, preserveScroll: true, replace: true, only: ['users', 'filters'] },
    );
}

function openReport(user) {
    router.get(route('attendance.reports.user', user.id));
}
</script>

<template>
    <AppLayout :title="t('attendance.monthly_employee_report')">
        <PageHeader
            :title="t('attendance.monthly_employee_report')"
            :description="t('attendance.user_report')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-chart-line" :href="route('attendance.reports.index')">
                    {{ t('attendance.reports') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <DataTable
                :columns="columns"
                :data="users"
                :filters="filters"
                route-name="attendance.reports.user.index"
                :only="['users', 'filters']"
                storage-key="attendance-monthly-employee-report"
                :selectable="false"
                :enable-filters="false"
                :enable-export="false"
                :enable-density="false"
                :enable-column-visibility="false"
                row-clickable
                @search="searchUsers"
                @row-click="openReport"
            >
                <template #cell-branch="{ row }">
                    {{ row.branch?.branch_name || '—' }}
                </template>

                <template #cell-department="{ row }">
                    {{ row.department?.department_name || '—' }}
                </template>

                <template #cell-status="{ row }">
                    <Badge
                        :text="row.status === 1 ? t('common.active') : t('common.inactive')"
                        :variant="row.status === 1 ? 'active' : 'inactive'"
                    />
                </template>

                <template #cell-actions="{ row }">
                    <IconButton
                        icon="fas fa-chart-line"
                        :aria-label="t('attendance.monthly_employee_report')"
                        @click.stop="openReport(row)"
                    />
                </template>
            </DataTable>
        </Card>
    </AppLayout>
</template>
