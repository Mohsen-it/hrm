<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import FormModal from '@/Components/ui/FormModal.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    summaries: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
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

const columns = computed(() => [
    { key: 'user', label: t('attendance.fields.user'), sortable: true },
    { key: 'summary_date', label: t('attendance.fields.summary_date'), sortable: true },
    { key: 'first_check_in_at', label: t('attendance.fields.first_check_in_at') },
    { key: 'last_check_out_at', label: t('attendance.fields.last_check_out_at') },
    { key: 'work_human', label: t('attendance.fields.work_human'), cellClass: 'text-center' },
    { key: 'overtime_human', label: t('attendance.fields.overtime_human'), cellClass: 'text-center' },
    { key: 'late_human', label: t('attendance.fields.late_human'), cellClass: 'text-center' },
    { key: 'status', label: t('attendance.fields.status'), cellClass: 'text-center' },
]);

function onSearch(value) {
    router.get(
        route('attendance.daily-summaries.index'),
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
    router.get(route('attendance.daily-summaries.index'), next, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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
                <button type="button" class="btn btn-ghost" @click="showRecalc = true">
                    <i class="fas fa-rotate"></i>
                    <span>{{ t('attendance.actions.recalculate') }}</span>
                </Button>
                <button type="button" class="btn btn-primary" @click="showRangeRecalc = true">
                    <i class="fas fa-calendar-week"></i>
                    <span>{{ t('attendance.actions.recalculate_range') }}</span>
                </Button>
            </template>
        </PageHeader>

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>

        <div class="card p-4 mb-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 flex-wrap">
                <SearchInput
                    v-model="search"
                    :placeholder="t('common.search')"
                    @search="onSearch"
                />
                <select
                    class="form-input max-w-[200px]"
                    :value="filters.user_id ?? ''"
                    @change="applyFilter('user_id', $event.target.value)"
                >
                    <option value="">{{ t('attendance.placeholders.select_user') }}</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">
                        {{ u.name }} ({{ u.employee_code }})
                    </option>
                </select>
                <select
                    class="form-input max-w-[180px]"
                    :value="filters.status ?? ''"
                    @change="applyFilter('status', $event.target.value)"
                >
                    <option value="">{{ t('attendance.placeholders.select_status') }}</option>
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <input
                    type="date"
                    class="form-input max-w-[170px]"
                    :value="filters.from ?? ''"
                    @change="applyFilter('from', $event.target.value)"
                />
                <input
                    type="date"
                    class="form-input max-w-[170px]"
                    :value="filters.to ?? ''"
                    @change="applyFilter('to', $event.target.value)"
                />
            </div>
        </div>

        <DataTable :columns="columns" :data="summaries" :empty-title="t('attendance.messages.empty_summaries')">
            <template #cell-user="{ row }">
                <div>
                    <div class="font-semibold text-[var(--color-ink)]">
                        {{ row.user?.name || '—' }}
                    </div>
                    <div class="text-[11px] text-[var(--color-ink-subtle)]">
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
                <div>
                    <label class="block text-[12px] font-semibold text-[var(--color-ink-muted)] mb-1">
                        {{ t('attendance.fields.user') }}
                    </label>
                    <select v-model="recalcForm.user_id" class="form-input" required>
                        <option value="">{{ t('attendance.placeholders.select_user') }}</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">
                            {{ u.name }} ({{ u.employee_code }})
                        </option>
                    </select>
                </div>
                <FormInput
                    v-model="recalcForm.date"
                    :label="t('attendance.fields.date')"
                    type="date"
                />
            </div>
            <template #footer>
                <button type="button" class="btn btn-ghost" @click="showRecalc = false">
                    {{ t('common.cancel') }}
                </Button>
                <button type="button" class="btn btn-primary" @click="performRecalc">
                    <i class="fas fa-rotate"></i>
                    <span>{{ t('attendance.actions.recalculate') }}</span>
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
                <label class="col-span-2 flex items-center gap-2 text-[13px]">
                    <input type="checkbox" v-model="rangeForm.missing_only" />
                    <span>{{ t('attendance.filters.processed') === 'معالجة' ? 'إعادة حساب المفقود فقط' : 'Only missing' }}</span>
                </label>
            </div>
            <template #footer>
                <button type="button" class="btn btn-ghost" @click="showRangeRecalc = false">
                    {{ t('common.cancel') }}
                </Button>
                <button type="button" class="btn btn-primary" @click="performRangeRecalc">
                    <i class="fas fa-calendar-week"></i>
                    <span>{{ t('attendance.actions.recalculate_range') }}</span>
                </Button>
            </template>
        </FormModal>
    </AppLayout>
</template>
