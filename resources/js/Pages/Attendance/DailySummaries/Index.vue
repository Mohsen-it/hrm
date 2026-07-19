<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, DataTable, FormModal, FormInput, FormSelect, FormCheckbox, Badge, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    summaries: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
});

const showRecalc = ref(false);
const showRangeRecalc = ref(false);

const recalcForm = ref({ user_id: '', date: new Date().toISOString().slice(0, 10) });
const rangeForm = ref({
    from: new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
    missing_only: false,
});

const statusOptions = [
    { value: 'present', label: t('attendance.status.present') },
    { value: 'absent', label: t('attendance.status.absent') },
    { value: 'late', label: t('attendance.status.late') },
    { value: 'early_leave', label: t('attendance.status.early_leave') },
    { value: 'missing_punch', label: t('attendance.status.missing_punch') },
];

const statusVariant = (status) => {
    return {
        present: 'active',
        late: 'pending',
        early_leave: 'info',
        missing_punch: 'absent',
        absent: 'inactive',
    }[status] || 'inactive';
};

const userFilterOptions = computed(() =>
    props.users.map((u) => ({ value: u.id, label: `${u.name} (${u.employee_code})` })),
);

const userOptions = computed(() => [
    { value: '', label: t('attendance.placeholders.select_user') },
    ...userFilterOptions.value,
]);

const columns = computed(() => [
    { key: 'user', label: t('attendance.fields.user'), sortable: true, filterable: true, filterType: 'select', filterOptions: userFilterOptions.value, filterKey: 'user_id' },
    { key: 'summary_date', label: t('attendance.fields.summary_date'), sortable: true },
    { key: 'first_check_in_at', label: t('attendance.fields.first_check_in_at') },
    { key: 'last_check_out_at', label: t('attendance.fields.last_check_out_at') },
    { key: 'work_human', label: t('attendance.fields.work_human'), cellClass: 'text-center' },
    { key: 'overtime_human', label: t('attendance.fields.overtime_human'), cellClass: 'text-center' },
    { key: 'late_human', label: t('attendance.fields.late_human'), cellClass: 'text-center' },
    { key: 'status', label: t('attendance.fields.status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: statusOptions, filterKey: 'status' },
    { key: 'from', label: t('attendance.fields.from'), filterable: true, filterType: 'date', filterKey: 'from' },
    { key: 'to', label: t('attendance.fields.to'), filterable: true, filterType: 'date', filterKey: 'to' },
]);

function onSearch(value) {
    router.get(
        route('attendance.daily-summaries.index'),
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
    router.get(route('attendance.daily-summaries.index'), next, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onPageChange(page) {
    router.get(
        route('attendance.daily-summaries.index'),
        { ...props.filters, page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onPerPageChange(perPage) {
    router.get(
        route('attendance.daily-summaries.index'),
        { ...props.filters, per_page: perPage },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function performRecalc() {
    router.post(route('attendance.daily-summaries.recalculate'), recalcForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showRecalc.value = false;
        },
    });
}

function performRangeRecalc() {
    router.post(route('attendance.daily-summaries.recalculate-range'), rangeForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showRangeRecalc.value = false;
        },
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('attendance.summaries')">
        <PageHeader
            :title="t('attendance.summaries')"
            :description="t('attendance.index_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-rotate" @click="showRecalc = true">
                    {{ t('attendance.actions.recalculate') }}
                </Button>
                <Button variant="primary" icon="fas fa-calendar-week" @click="showRangeRecalc = true">
                    {{ t('attendance.actions.recalculate_range') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="summaries"
            :empty-title="t('attendance.messages.empty_summaries')"
            storage-key="attendance-summaries"
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
            <template #cell-first_check_in_at="{ row }">
                <span dir="ltr" class="text-[12px]">{{ row.first_check_in_at || '—' }}</span>
            </template>
            <template #cell-last_check_out_at="{ row }">
                <span dir="ltr" class="text-[12px]">{{ row.last_check_out_at || '—' }}</span>
            </template>
            <template #cell-status="{ row }">
                <Badge
                    :text="t(`attendance.status.${row.status}`, row.status)"
                    :variant="statusVariant(row.status)"
                />
            </template>
        </DataTable>

        <FormModal v-model="showRecalc" :title="t('attendance.actions.recalculate')" size="sm">
            <div class="grid grid-cols-1 gap-3">
                <FormSelect
                    v-model="recalcForm.user_id"
                    :label="t('attendance.fields.user')"
                    :options="userOptions"
                    :placeholder="t('attendance.placeholders.select_user')"
                />
                <FormInput
                    v-model="recalcForm.date"
                    :label="t('attendance.fields.date')"
                    type="date"
                />
            </div>
            <template #footer>
                <Button variant="secondary" @click="showRecalc = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button variant="primary" icon="fas fa-rotate" @click="performRecalc">
                    {{ t('attendance.actions.recalculate') }}
                </Button>
            </template>
        </FormModal>

        <FormModal v-model="showRangeRecalc" :title="t('attendance.actions.recalculate_range')" size="sm">
            <div class="grid grid-cols-2 gap-3">
                <FormInput
                    v-model="rangeForm.from"
                    :label="t('attendance.fields.from')"
                    type="date"
                />
                <FormInput
                    v-model="rangeForm.to"
                    :label="t('attendance.fields.to')"
                    type="date"
                />
                <FormCheckbox
                    v-model="rangeForm.missing_only"
                    :label="t('attendance.filters.processed') === 'معالجة' ? 'إعادة حساب المفقود فقط' : 'Only missing'"
                    class="col-span-2"
                />
            </div>
            <template #footer>
                <Button variant="secondary" @click="showRangeRecalc = false">
                    {{ t('common.cancel') }}
                </Button>
                <Button variant="primary" icon="fas fa-calendar-week" @click="performRangeRecalc">
                    {{ t('attendance.actions.recalculate_range') }}
                </Button>
            </template>
        </FormModal>
    </AppLayout>
</template>
