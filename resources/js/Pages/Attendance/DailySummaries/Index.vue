<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, DataTable, FormModal, FormInput, FormSelect, FormCheckbox, Badge, Alert, EmptyState } from '@/Components/ui';
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
    { value: 'rest', label: t('attendance.status.rest') },
    { value: 'unassigned', label: t('attendance.status.unassigned') },
];

const statusVariant = (status) => {
    return {
        present: 'active',
        late: 'pending',
        early_leave: 'info',
        missing_punch: 'absent',
        absent: 'inactive',
        rest: 'info',
        unassigned: 'warning',
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

// ------------------------------------------------------------------
// Violation sections
// ------------------------------------------------------------------

const today = new Date().toISOString().slice(0, 10);
const thirtyDaysAgo = new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10);

// Section 1: Late check-ins
const lateCheckInForm = ref({ from: thirtyDaysAgo, to: today, cutoff_time: '08:00', user_id: '' });
const lateCheckInData = ref([]);
const lateCheckInLoading = ref(false);

// Section 2: Missing check-outs
const missingCheckOutForm = ref({ from: thirtyDaysAgo, to: today, cutoff_time: '15:30', user_id: '' });
const missingCheckOutData = ref([]);
const missingCheckOutLoading = ref(false);

// Section 3: Late for vacation
const lateForVacationForm = ref({ from: thirtyDaysAgo, to: today, cutoff_time: '08:00', user_id: '' });
const lateForVacationData = ref([]);
const lateForVacationLoading = ref(false);

async function fetchLateCheckIns() {
    lateCheckInLoading.value = true;
    try {
        const params = new URLSearchParams({ ...lateCheckInForm.value });
        if (!lateCheckInForm.value.user_id) params.delete('user_id');
        const res = await fetch(route('attendance.daily-summaries.late-check-ins') + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        lateCheckInData.value = json.data || [];
    } catch {
        lateCheckInData.value = [];
    } finally {
        lateCheckInLoading.value = false;
    }
}

async function fetchMissingCheckOuts() {
    missingCheckOutLoading.value = true;
    try {
        const params = new URLSearchParams({ ...missingCheckOutForm.value });
        if (!missingCheckOutForm.value.user_id) params.delete('user_id');
        const res = await fetch(route('attendance.daily-summaries.missing-check-outs') + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        missingCheckOutData.value = json.data || [];
    } catch {
        missingCheckOutData.value = [];
    } finally {
        missingCheckOutLoading.value = false;
    }
}

async function fetchLateForVacation() {
    lateForVacationLoading.value = true;
    try {
        const params = new URLSearchParams({ ...lateForVacationForm.value });
        if (!lateForVacationForm.value.user_id) params.delete('user_id');
        const res = await fetch(route('attendance.daily-summaries.late-for-vacation') + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        lateForVacationData.value = json.data || [];
    } catch {
        lateForVacationData.value = [];
    } finally {
        lateForVacationLoading.value = false;
    }
}

function exportLateCheckIns() {
    const params = new URLSearchParams({ ...lateCheckInForm.value });
    window.location.href = route('attendance.daily-summaries.export-late-check-ins') + '?' + params.toString();
}

function exportMissingCheckOuts() {
    const params = new URLSearchParams({ ...missingCheckOutForm.value });
    window.location.href = route('attendance.daily-summaries.export-missing-check-outs') + '?' + params.toString();
}

function exportLateForVacation() {
    const params = new URLSearchParams({ ...lateForVacationForm.value });
    window.location.href = route('attendance.daily-summaries.export-late-for-vacation') + '?' + params.toString();
}

function onSearch(value) {
    router.get(
        route('attendance.daily-summaries.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true, only: ['summaries'] },
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
        only: ['summaries'],
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
                <Button variant="secondary" icon="fas fa-rotate" @click="showRecalc = true">
                    {{ t('attendance.actions.recalculate') }}
                </Button>
                <Button variant="primary" icon="fas fa-calendar-week" @click="showRangeRecalc = true">
                    {{ t('attendance.actions.recalculate_range') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <!-- Main summaries table -->
        <DataTable
            :columns="columns"
            :data="summaries"
            :filters="filters"
            :route-name="'attendance.daily-summaries.index'"
            :only="['summaries']"
            :empty-title="t('attendance.messages.empty_summaries')"
            storage-key="attendance-summaries"
            @search="onSearch"
            @filter-change="onFilterChange"
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

        <!-- ============================================================ -->
        <!-- Section 1: Late Check-ins                                     -->
        <!-- ============================================================ -->
        <Card variant="base" class="mt-8">
            <template #header>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-mistral-ink">
                            {{ t('attendance.violations.late_check_ins') }}
                        </h3>
                        <p class="text-sm text-mistral-stone mt-1">
                            {{ t('attendance.violations.late_check_ins_description') }}
                        </p>
                    </div>
                    <Button
                        v-if="lateCheckInData.length > 0"
                        variant="secondary"
                        icon="fas fa-file-excel"
                        @click="exportLateCheckIns"
                    >
                        {{ t('attendance.violations.export') }}
                    </Button>
                </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <FormInput
                    v-model="lateCheckInForm.from"
                    :label="t('attendance.violations.from_date')"
                    type="date"
                />
                <FormInput
                    v-model="lateCheckInForm.to"
                    :label="t('attendance.violations.to_date')"
                    type="date"
                />
                <FormInput
                    v-model="lateCheckInForm.cutoff_time"
                    :label="t('attendance.violations.cutoff_time')"
                    type="time"
                />
                <div class="flex items-end">
                    <Button variant="primary" icon="fas fa-search" :loading="lateCheckInLoading" class="w-full" @click="fetchLateCheckIns">
                        {{ t('attendance.violations.search') }}
                    </Button>
                </div>
            </div>

            <div v-if="lateCheckInData.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-mistral-border bg-mistral-cream/30">
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">#</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.employee_name') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.employee_code') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.date') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.check_in_time') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.cutoff') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.late_minutes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in lateCheckInData" :key="row.id" class="border-b border-mistral-border hover:bg-mistral-cream/20">
                            <td class="px-4 py-3 text-mistral-stone">{{ idx + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-mistral-ink">{{ row.user_name }}</td>
                            <td class="px-4 py-3 text-mistral-stone">{{ row.employee_code }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.summary_date }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.first_check_in_at }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.cutoff_time }}</td>
                            <td class="px-4 py-3">
                                <Badge :text="`${row.late_minutes} ${t('attendance.units.minutes_short')}`" variant="pending" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else-if="!lateCheckInLoading && lateCheckInData.length === 0"
                :title="t('attendance.violations.no_data')"
                icon="fas fa-clock"
            />
        </Card>

        <!-- ============================================================ -->
        <!-- Section 2: Missing Check-outs                                 -->
        <!-- ============================================================ -->
        <Card variant="base" class="mt-8">
            <template #header>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-mistral-ink">
                            {{ t('attendance.violations.missing_check_outs') }}
                        </h3>
                        <p class="text-sm text-mistral-stone mt-1">
                            {{ t('attendance.violations.missing_check_outs_description') }}
                        </p>
                    </div>
                    <Button
                        v-if="missingCheckOutData.length > 0"
                        variant="secondary"
                        icon="fas fa-file-excel"
                        @click="exportMissingCheckOuts"
                    >
                        {{ t('attendance.violations.export') }}
                    </Button>
                </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <FormInput
                    v-model="missingCheckOutForm.from"
                    :label="t('attendance.violations.from_date')"
                    type="date"
                />
                <FormInput
                    v-model="missingCheckOutForm.to"
                    :label="t('attendance.violations.to_date')"
                    type="date"
                />
                <FormInput
                    v-model="missingCheckOutForm.cutoff_time"
                    :label="t('attendance.violations.cutoff_time')"
                    type="time"
                />
                <div class="flex items-end">
                    <Button variant="primary" icon="fas fa-search" :loading="missingCheckOutLoading" class="w-full" @click="fetchMissingCheckOuts">
                        {{ t('attendance.violations.search') }}
                    </Button>
                </div>
            </div>

            <div v-if="missingCheckOutData.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-mistral-border bg-mistral-cream/30">
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">#</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.employee_name') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.employee_code') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.date') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.check_in_time') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.cutoff') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.missing_checkout_duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in missingCheckOutData" :key="row.id" class="border-b border-mistral-border hover:bg-mistral-cream/20">
                            <td class="px-4 py-3 text-mistral-stone">{{ idx + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-mistral-ink">{{ row.user_name }}</td>
                            <td class="px-4 py-3 text-mistral-stone">{{ row.employee_code }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.summary_date }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.first_check_in_at }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.cutoff_time }}</td>
                            <td class="px-4 py-3">
                                <Badge :text="`${row.late_minutes} ${t('attendance.units.minutes_short')}`" variant="absent" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else-if="!missingCheckOutLoading && missingCheckOutData.length === 0"
                :title="t('attendance.violations.no_data')"
                icon="fas fa-sign-out-alt"
            />
        </Card>

        <!-- ============================================================ -->
        <!-- Section 3: Late for Vacation                                  -->
        <!-- ============================================================ -->
        <Card variant="base" class="mt-8 mb-8">
            <template #header>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-mistral-ink">
                            {{ t('attendance.violations.late_for_vacation') }}
                        </h3>
                        <p class="text-sm text-mistral-stone mt-1">
                            {{ t('attendance.violations.late_for_vacation_description') }}
                        </p>
                    </div>
                    <Button
                        v-if="lateForVacationData.length > 0"
                        variant="secondary"
                        icon="fas fa-file-excel"
                        @click="exportLateForVacation"
                    >
                        {{ t('attendance.violations.export') }}
                    </Button>
                </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <FormInput
                    v-model="lateForVacationForm.from"
                    :label="t('attendance.violations.from_date')"
                    type="date"
                />
                <FormInput
                    v-model="lateForVacationForm.to"
                    :label="t('attendance.violations.to_date')"
                    type="date"
                />
                <FormInput
                    v-model="lateForVacationForm.cutoff_time"
                    :label="t('attendance.violations.cutoff_time')"
                    type="time"
                />
                <div class="flex items-end">
                    <Button variant="primary" icon="fas fa-search" :loading="lateForVacationLoading" class="w-full" @click="fetchLateForVacation">
                        {{ t('attendance.violations.search') }}
                    </Button>
                </div>
            </div>

            <div v-if="lateForVacationData.length > 0" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-mistral-border bg-mistral-cream/30">
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">#</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.employee_name') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.employee_code') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.date') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.check_in_time') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.cutoff') }}</th>
                            <th class="px-4 py-3 text-start font-semibold text-mistral-ink">{{ t('attendance.violations.late_minutes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in lateForVacationData" :key="row.id" class="border-b border-mistral-border hover:bg-mistral-cream/20">
                            <td class="px-4 py-3 text-mistral-stone">{{ idx + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-mistral-ink">{{ row.user_name }}</td>
                            <td class="px-4 py-3 text-mistral-stone">{{ row.employee_code }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.summary_date }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.first_check_in_at }}</td>
                            <td class="px-4 py-3 text-mistral-stone" dir="ltr">{{ row.cutoff_time }}</td>
                            <td class="px-4 py-3">
                                <Badge :text="`${row.late_minutes} ${t('attendance.units.minutes_short')}`" variant="pending" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else-if="!lateForVacationLoading && lateForVacationData.length === 0"
                :title="t('attendance.violations.no_data')"
                icon="fas fa-calendar-times"
            />
        </Card>

        <!-- Recalculate modals -->
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
