<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    sessions: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedSession = ref(null);

const statusOptions = [
    { value: 'present', label: t('attendance.status.present') },
    { value: 'absent', label: t('attendance.status.absent') },
    { value: 'late', label: t('attendance.status.late') },
    { value: 'early_leave', label: t('attendance.status.early_leave') },
    { value: 'missing_punch', label: t('attendance.status.missing_punch') },
    { value: 'holiday', label: t('attendance.status.holiday') },
    { value: 'vacation', label: t('attendance.status.vacation') },
    { value: 'weekend', label: t('attendance.status.weekend') },
];

const sessionTypeOptions = [
    { value: 'normal', label: t('attendance.session_type.normal') },
    { value: 'overtime', label: t('attendance.session_type.overtime') },
    { value: 'make_up', label: t('attendance.session_type.make_up') },
];

const userFilterOptions = computed(() =>
    props.users.map((u) => ({ value: u.id, label: `${u.name} (${u.employee_code})` })),
);

const shiftFilterOptions = computed(() =>
    props.shifts.map((s) => ({ value: s.id, label: `${s.shift_name} (${s.shift_code})` })),
);

const statusVariant = (status) => {
    return {
        present: 'active',
        late: 'pending',
        early_leave: 'info',
        missing_punch: 'absent',
        absent: 'inactive',
        holiday: 'vacation',
        vacation: 'vacation',
        weekend: 'info',
    }[status] || 'inactive';
};

const sessionTypeVariant = (type) => {
    return {
        normal: 'active',
        overtime: 'overtime',
        make_up: 'info',
    }[type] || 'inactive';
};

const columns = computed(() => [
    { key: 'user', label: t('attendance.fields.user'), sortable: true, filterable: true, filterType: 'select', filterOptions: userFilterOptions.value, filterKey: 'user_id' },
    { key: 'attendance_date', label: t('attendance.fields.attendance_date'), sortable: true },
    { key: 'check_in_at', label: t('attendance.fields.check_in_at') },
    { key: 'check_out_at', label: t('attendance.fields.check_out_at') },
    { key: 'work_human', label: t('attendance.fields.work_human'), cellClass: 'text-center' },
    { key: 'late_human', label: t('attendance.fields.late_human'), cellClass: 'text-center' },
    { key: 'status', label: t('attendance.fields.status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: statusOptions, filterKey: 'status' },
    { key: 'session_type', label: t('attendance.fields.session_type'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: sessionTypeOptions, filterKey: 'session_type' },
    { key: 'shift', label: t('attendance.fields.shift'), filterable: true, filterType: 'select', filterOptions: shiftFilterOptions.value, filterKey: 'shift_id' },
    { key: 'from', label: t('attendance.fields.from'), filterable: true, filterType: 'date', filterKey: 'from' },
    { key: 'to', label: t('attendance.fields.to'), filterable: true, filterType: 'date', filterKey: 'to' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(
        route('attendance.sessions.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
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
    router.get(route('attendance.sessions.index'), next, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onPageChange(page) {
    router.get(
        route('attendance.sessions.index'),
        { ...props.filters, page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onPerPageChange(perPage) {
    router.get(
        route('attendance.sessions.index'),
        { ...props.filters, per_page: perPage },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(session) {
    selectedSession.value = session;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedSession.value) return;
    router.delete(route('attendance.sessions.destroy', selectedSession.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('attendance.sessions')">
        <PageHeader
            :title="t('attendance.sessions')"
            :description="t('attendance.index_description')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('attendance.sessions.create')">
                    {{ t('attendance.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="sessions"
            :empty-title="t('attendance.messages.empty_sessions')"
            storage-key="attendance-sessions"
            @search="onSearch"
            @filter-change="onFilterChange"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
        >
            <template #cell-user="{ row }">
                <div>
                    <div class="font-semibold text-mistral-ink">
                        {{ row.user?.name || '—' }}
                    </div>
                    <div class="text-[11px] text-mistral-stone">
                        {{ row.user?.employee_code || '' }}
                    </div>
                </div>
            </template>

            <template #cell-check_in_at="{ row }">
                <span dir="ltr" class="text-[12px]">{{ row.check_in_at || '—' }}</span>
            </template>

            <template #cell-check_out_at="{ row }">
                <span dir="ltr" class="text-[12px]">{{ row.check_out_at || '—' }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge
                    :text="t(`attendance.status.${row.status}`, row.status)"
                    :variant="statusVariant(row.status)"
                />
            </template>

            <template #cell-session_type="{ row }">
                <Badge
                    :text="t(`attendance.session_type.${row.session_type}`, row.session_type)"
                    :variant="sessionTypeVariant(row.session_type)"
                />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1.5">
                    <IconButton icon="fas fa-eye" :aria-label="t('attendance.actions.view')" variant="info" :href="route('attendance.sessions.show', row.id)" />
                    <IconButton icon="fas fa-edit" :aria-label="t('attendance.actions.edit')" variant="primary" :href="route('attendance.sessions.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('attendance.actions.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('attendance.messages.delete_confirm_title')"
            :message="t('attendance.messages.delete_confirm_message', { name: t('attendance.session') })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
