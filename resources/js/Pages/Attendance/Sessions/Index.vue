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
import FormDatepicker from '@/Components/ui/FormDatepicker.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    sessions: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedSession = ref(null);

const fromValue = ref(props.filters?.from ?? '');
const toValue = ref(props.filters?.to ?? '');

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

const userOptions = computed(() => [
    { value: '', label: t('attendance.placeholders.select_user') },
    ...props.users.map((u) => ({ value: u.id, label: `${u.name} (${u.employee_code})` })),
]);

const shiftOptions = computed(() => [
    { value: '', label: t('attendance.placeholders.select_shift') },
    ...props.shifts.map((s) => ({ value: s.id, label: `${s.shift_name} (${s.shift_code})` })),
]);

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
    { key: 'user', label: t('attendance.fields.user'), sortable: true },
    { key: 'attendance_date', label: t('attendance.fields.attendance_date'), sortable: true },
    { key: 'check_in_at', label: t('attendance.fields.check_in_at') },
    { key: 'check_out_at', label: t('attendance.fields.check_out_at') },
    { key: 'work_human', label: t('attendance.fields.work_human'), cellClass: 'text-center' },
    { key: 'late_human', label: t('attendance.fields.late_human'), cellClass: 'text-center' },
    { key: 'status', label: t('attendance.fields.status'), cellClass: 'text-center' },
    { key: 'session_type', label: t('attendance.fields.session_type'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

function onSearch(value) {
    router.get(
        route('attendance.sessions.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function applyFilter(key, value) {
    const next = { ...props.filters };
    if (value === '' || value === null || value === undefined) {
        delete next[key];
    } else {
        next[key] = value;
    }
    router.get(route('attendance.sessions.index'), next, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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

        <div class="card p-6 mb-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <SearchInput
                        v-model="search"
                        :placeholder="t('common.search')"
                        @search="onSearch"
                    />
                    <FormSelect
                        :model-value="filters.user_id ?? ''"
                        :options="userOptions"
                        class="max-w-[200px]"
                        @update:model-value="(v) => applyFilter('user_id', v)"
                    />
                    <FormSelect
                        :model-value="filters.shift_id ?? ''"
                        :options="shiftOptions"
                        class="max-w-[200px]"
                        @update:model-value="(v) => applyFilter('shift_id', v)"
                    />
                    <FormSelect
                        :model-value="filters.status ?? ''"
                        :options="[
                            { value: '', label: t('attendance.placeholders.select_status') },
                            ...statusOptions,
                        ]"
                        class="max-w-[180px]"
                        @update:model-value="(v) => applyFilter('status', v)"
                    />
                    <FormSelect
                        :model-value="filters.session_type ?? ''"
                        :options="[
                            { value: '', label: t('attendance.placeholders.select_session_type') },
                            ...sessionTypeOptions,
                        ]"
                        class="max-w-[180px]"
                        @update:model-value="(v) => applyFilter('session_type', v)"
                    />
                    <FormDatepicker
                        v-model="fromValue"
                        class="max-w-[170px]"
                        @change="applyFilter('from', fromValue)"
                    />
                    <FormDatepicker
                        v-model="toValue"
                        class="max-w-[170px]"
                        @change="applyFilter('to', toValue)"
                    />
                </div>
            </div>
        </div>

        <DataTable :columns="columns" :data="sessions" :empty-title="t('attendance.messages.empty_sessions')">
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
