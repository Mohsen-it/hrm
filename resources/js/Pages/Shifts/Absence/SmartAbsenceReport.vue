<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    PageHeader, Card, Button, DataTable, FormInput, FormSelect, FormMultiSelect,
    StatCard, Badge, EmptyState,
} from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    dailyData: { type: Object, default: () => ({ expected: [], absent: { data: [] }, total_expected: 0, total_absent: 0, attendance_rate: 100, date: '' }) },
    monthlyData: { type: Array, default: () => [] },
    monthlyReportData: { type: Object, default: () => ({ employees: { data: [], links: [] }, total_expected_days: 0, total_absent_days: 0, total_present_days: 0, attendance_rate: 100, from_date: '', to_date: '' }) },
    rotations: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const activeTab = ref(props.dailyData?.date ? 'daily' : 'monthly')

const selectedDate = ref(props.filters?.date || new Date().toISOString().split('T')[0])
const selectedDepartmentId = ref(props.filters?.department_id || null)
const selectedRotationIds = ref(Array.isArray(props.filters?.rotation_ids) ? props.filters.rotation_ids : (props.filters?.rotation_id ? [props.filters.rotation_id] : []))
const selectedRotationGroupIds = ref(Array.isArray(props.filters?.rotation_group_ids) ? props.filters.rotation_group_ids : (props.filters?.rotation_group_id ? [props.filters.rotation_group_id] : []))

const today = new Date()
const selectedMonth = ref(Number(props.filters?.month) || today.getMonth() + 1)
const selectedYear = ref(Number(props.filters?.year) || today.getFullYear())
const fromDate = ref(props.filters?.from_date || firstOfMonth(selectedMonth.value, selectedYear.value))
const toDate = ref(props.filters?.to_date || lastOfMonth(selectedMonth.value, selectedYear.value))

function firstOfMonth(month, year) {
    return `${year}-${String(month).padStart(2, '0')}-01`
}

function lastOfMonth(month, year) {
    const lastDay = new Date(year, month, 0).getDate()
    return `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`
}

// All groups (across selected rotations) for multi-select
const allGroupOptions = computed(() => {
    const groups = []
    const seen = new Set()
    for (const r of props.rotations) {
        for (const g of (r.groups || [])) {
            if (!seen.has(g.id)) {
                seen.add(g.id)
                groups.push({ value: g.id, label: `${r.name} - ${g.name}` })
            }
        }
    }
    return groups
})

const rotationOptions = computed(() => [
    { value: '', label: t('shifts.all_rotations') },
    ...props.rotations.map((r) => ({ value: r.id, label: r.name })),
])

const departmentOptions = computed(() => [
    { value: '', label: t('shifts.all_departments') },
    ...props.departments.map((d) => ({ value: d.id, label: d.name })),
])

const multiRotationOptions = computed(() =>
    props.rotations.map((r) => ({ value: r.id, label: r.name }))
)

const multiGroupOptions = computed(() => {
    if (selectedRotationIds.value.length === 0) return allGroupOptions.value
    const groups = []
    for (const r of props.rotations) {
        if (selectedRotationIds.value.includes(r.id)) {
            for (const g of (r.groups || [])) {
                groups.push({ value: g.id, label: `${r.name} - ${g.name}` })
            }
        }
    }
    return groups
})

const filterParams = computed(() => ({
    date: selectedDate.value,
    department_id: selectedDepartmentId.value || null,
    rotation_ids: selectedRotationIds.value,
    rotation_group_ids: selectedRotationGroupIds.value,
}))

const monthlyFilterParams = computed(() => ({
    from_date: fromDate.value,
    to_date: toDate.value,
    department_id: selectedDepartmentId.value || null,
    rotation_ids: selectedRotationIds.value,
    rotation_group_ids: selectedRotationGroupIds.value,
}))

const hasActiveFilters = computed(() =>
    selectedDepartmentId.value
    || selectedRotationIds.value.length > 0
    || selectedRotationGroupIds.value.length > 0
)

const summary = computed(() => {
    const totalExpected = props.dailyData?.total_expected || 0
    const totalAbsent = props.dailyData?.total_absent || 0
    const rate = totalExpected > 0
        ? Math.round(((totalExpected - totalAbsent) / totalExpected) * 100)
        : 100
    return { totalExpected, totalAbsent, rate }
})

const monthlySummary = computed(() => {
    const expected = Number(props.monthlyReportData?.total_expected_days) || 0
    const absent = Number(props.monthlyReportData?.total_absent_days) || 0
    const rawPresent = Number(props.monthlyReportData?.total_present_days)
    const present = Math.max(Number.isFinite(rawPresent) ? rawPresent : (expected - absent), 0)
    const rate = expected > 0 ? Math.round((present / expected) * 100) : 100
    return { expected, absent, present, rate }
})

let reloadTimer = null
function reloadActiveTab() {
    clearTimeout(reloadTimer)
    reloadTimer = setTimeout(() => {
        if (activeTab.value === 'monthly') loadMonthly()
        else loadDaily()
    }, 200)
}

watch(activeTab, (tab) => {
    if (tab === 'monthly') loadMonthly()
    else loadDaily()
})

watch(selectedRotationIds, () => {
    if (selectedRotationIds.value.length === 0) {
        selectedRotationGroupIds.value = []
    } else {
        const validGroupIds = new Set(multiGroupOptions.value.map((g) => g.value))
        selectedRotationGroupIds.value = selectedRotationGroupIds.value.filter((id) => validGroupIds.has(id))
    }
    reloadActiveTab()
})

watch(selectedRotationGroupIds, () => {
    reloadActiveTab()
})

watch(selectedDepartmentId, () => {
    reloadActiveTab()
})

watch([selectedMonth, selectedYear], () => {
    fromDate.value = firstOfMonth(selectedMonth.value, selectedYear.value)
    toDate.value = lastOfMonth(selectedMonth.value, selectedYear.value)
})

watch([fromDate, toDate], () => {
    reloadActiveTab()
})

function loadDaily() {
    router.get(route('smart-absence.daily'), {
        date: selectedDate.value,
        department_id: selectedDepartmentId.value || null,
        rotation_ids: selectedRotationIds.value,
        rotation_group_ids: selectedRotationGroupIds.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function loadMonthly() {
    router.get(route('smart-absence.monthly.report'), {
        from_date: fromDate.value,
        to_date: toDate.value,
        department_id: selectedDepartmentId.value || null,
        rotation_ids: selectedRotationIds.value,
        rotation_group_ids: selectedRotationGroupIds.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function clearFilters() {
    selectedDepartmentId.value = null
    selectedRotationIds.value = []
    selectedRotationGroupIds.value = []
}

function buildExportParams() {
    const params = new URLSearchParams()
    params.set('date', selectedDate.value)
    if (selectedDepartmentId.value) params.set('department_id', String(selectedDepartmentId.value))
    selectedRotationIds.value.forEach((id) => params.append('rotation_ids[]', String(id)))
    selectedRotationGroupIds.value.forEach((id) => params.append('rotation_group_ids[]', String(id)))
    return params
}

function handleExport(payload) {
    const format = payload?.format === 'csv' ? 'csv' : 'excel'
    if (format !== 'excel') return
    const url = route('smart-absence.daily.export') + '?' + buildExportParams().toString()
    window.location.href = url
}

function buildMonthlyExportParams() {
    const params = new URLSearchParams()
    params.set('from_date', fromDate.value)
    params.set('to_date', toDate.value)
    if (selectedDepartmentId.value) params.set('department_id', String(selectedDepartmentId.value))
    selectedRotationIds.value.forEach((id) => params.append('rotation_ids[]', String(id)))
    selectedRotationGroupIds.value.forEach((id) => params.append('rotation_group_ids[]', String(id)))
    return params
}

function handleMonthlyExport(payload) {
    const format = payload?.format === 'csv' ? 'csv' : 'excel'
    if (format !== 'excel') return
    const url = route('smart-absence.monthly.export') + '?' + buildMonthlyExportParams().toString()
    window.location.href = url
}

const monthOptions = computed(() =>
    Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: String(i + 1) }))
)

const columns = computed(() => [
    {
        key: 'name', label: t('shifts.employee_name'), sortable: true, filterable: true,
        cellClass: 'min-w-[180px]',
    },
    { key: 'employee_code', label: t('shifts.employee_code'), sortable: true, filterable: true, cellClass: 'min-w-[110px]' },
    { key: 'department_name', label: t('shifts.department'), sortable: true, filterable: true },
    { key: 'branch_name', label: t('shifts.branch'), sortable: true, filterable: true },
    { key: 'position_name', label: t('shifts.position'), sortable: true, filterable: true },
    { key: 'phone', label: t('shifts.phone'), sortable: false, filterable: true, cellClass: 'min-w-[120px]' },
    {
        key: 'rotation_name', label: t('shifts.rotation'), sortable: true, filterable: true,
        filterType: 'select', filterOptions: rotationOptions.value,
    },
    {
        key: 'rotation_group_name', label: t('shifts.rotation_group'), sortable: true, filterable: true,
        filterType: 'select', filterOptions: allGroupOptions.value,
    },
    {
        key: 'status', label: t('common.status'), sortable: true, filterable: true, cellClass: 'text-center',
    },
])

const monthlyColumns = computed(() => [
    {
        key: 'name', label: t('shifts.employee_name'), sortable: true, filterable: true,
        cellClass: 'min-w-[180px]',
    },
    { key: 'employee_code', label: t('shifts.employee_code'), sortable: true, filterable: true, cellClass: 'min-w-[110px]' },
    { key: 'department_name', label: t('shifts.department'), sortable: true, filterable: true },
    {
        key: 'rotation_name', label: t('shifts.rotation'), sortable: true, filterable: true,
        filterType: 'select', filterOptions: rotationOptions.value,
    },
    {
        key: 'rotation_group_name', label: t('shifts.rotation_group'), sortable: true, filterable: true,
        filterType: 'select', filterOptions: allGroupOptions.value,
    },
    { key: 'expected_days', label: t('shifts.expected_days'), sortable: true, cellClass: 'text-center min-w-[90px]' },
    { key: 'present_days', label: t('shifts.present_days'), sortable: true, cellClass: 'text-center min-w-[80px]' },
    { key: 'absent_days', label: t('shifts.absent_days'), sortable: true, cellClass: 'text-center min-w-[80px]' },
    { key: 'absent_dates', label: t('shifts.absent_dates'), sortable: false, cellClass: 'min-w-[180px]' },
    { key: 'day_details', label: t('shifts.day_details'), sortable: false, cellClass: 'min-w-[300px]' },
])

const MAX_VISIBLE_DETAILS = 6

function shortDateStr(date) {
    return String(date ?? '').length >= 10 ? String(date).substring(5) : String(date ?? '')
}

function dayDetailChip(detail) {
    return {
        ...detail,
        shortDate: shortDateStr(detail.date),
        fullLabel: `${detail.date} — ${detail.label || ''}`,
    }
}

function visibleDayDetails(row) {
    return (row.day_details || []).slice(0, MAX_VISIBLE_DETAILS).map(dayDetailChip)
}

function allDayDetailsLabel(row) {
    return (row.day_details || []).map((d) => `${d.date} — ${d.label || ''}`).join('، ')
}

function dayDetailStatusClass(status) {
    return {
        present: 'bg-mistral-success/10 text-mistral-success border-mistral-success/30',
        vacation: 'bg-mistral-info/10 text-mistral-info border-mistral-info/30',
        exception: 'bg-mistral-primary/10 text-mistral-primary border-mistral-primary/30',
        holiday: 'bg-mistral-cream-deeper text-mistral-ink border-mistral-hairline',
        absent: 'bg-mistral-danger/10 text-mistral-danger border-mistral-danger/30',
    }[status] || 'bg-mistral-surface text-mistral-steel border-mistral-hairline'
}

function formatTime(value) {
    if (!value) return '—'
    const s = String(value)
    return s.length >= 5 ? s.substring(0, 5) : s
}

const filterPills = computed(() => {
    const pills = []
    if (selectedDepartmentId.value) {
        const dept = props.departments.find((d) => d.id === selectedDepartmentId.value)
        if (dept) pills.push({ key: 'department', label: dept.name, clear: () => { selectedDepartmentId.value = null } })
    }
    selectedRotationIds.value.forEach((id) => {
        const r = props.rotations.find((rot) => rot.id === id)
        if (r) pills.push({
            key: `rot-${id}`,
            label: t('shifts.rotation') + ': ' + r.name,
            clear: () => { selectedRotationIds.value = selectedRotationIds.value.filter((x) => x !== id) },
        })
    })
    selectedRotationGroupIds.value.forEach((id) => {
        const g = allGroupOptions.value.find((grp) => grp.value === id)
        if (g) pills.push({
            key: `grp-${id}`,
            label: t('shifts.rotation_group') + ': ' + g.label,
            clear: () => { selectedRotationGroupIds.value = selectedRotationGroupIds.value.filter((x) => x !== id) },
        })
    })
    return pills
})
</script>

<template>
    <AppLayout :title="t('shifts.smart_absence_report')">
        <PageHeader
            :title="t('shifts.smart_absence_report')"
            :description="t('shifts.absent_list_subtitle')"
        >
            <template #actions>
                <Button
                    v-if="hasActiveFilters"
                    variant="secondary"
                    size="sm"
                    icon="fas fa-filter-circle-xmark"
                    @click="clearFilters"
                >
                    {{ t('shifts.clear_filters') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none" class="mb-5">
            <nav class="flex items-center gap-0 border-b border-mistral-hairline-soft overflow-x-auto" role="tablist">
                <button
                    @click="activeTab = 'daily'"
                    :class="activeTab === 'daily' ? 'border-b-2 border-mistral-primary text-mistral-primary font-bold' : 'text-mistral-steel border-transparent hover:text-mistral-ink'"
                    class="px-5 py-3 text-[13px] font-medium transition-colors border-b-2"
                    role="tab"
                    :aria-selected="activeTab === 'daily'"
                >
                    <i class="fas fa-calendar-day ms-1.5 text-[12px]"></i>
                    {{ t('shifts.daily') }}
                </button>
                <button
                    @click="activeTab = 'monthly'"
                    :class="activeTab === 'monthly' ? 'border-b-2 border-mistral-primary text-mistral-primary font-bold' : 'text-mistral-steel border-transparent hover:text-mistral-ink'"
                    class="px-5 py-3 text-[13px] font-medium transition-colors border-b-2"
                    role="tab"
                    :aria-selected="activeTab === 'monthly'"
                >
                    <i class="fas fa-calendar ms-1.5 text-[12px]"></i>
                    {{ t('shifts.monthly') }}
                </button>
            </nav>
        </Card>

        <!-- Daily tab -->
        <div v-if="activeTab === 'daily'">
            <!-- Hero stat cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <StatCard
                    :label="t('shifts.expected')"
                    :value="summary.totalExpected"
                    icon="fas fa-users"
                    color="info"
                />
                <StatCard
                    :label="t('shifts.absent')"
                    :value="summary.totalAbsent"
                    icon="fas fa-user-xmark"
                    color="danger"
                />
                <StatCard
                    :label="t('shifts.present')"
                    :value="summary.totalExpected - summary.totalAbsent"
                    icon="fas fa-user-check"
                    color="success"
                />
                <StatCard
                    :label="t('shifts.attendance_rate')"
                    :value="summary.rate + '%'"
                    icon="fas fa-chart-pie"
                    :color="summary.rate >= 90 ? 'success' : summary.rate >= 70 ? 'warning' : 'danger'"
                />
            </div>

            <!-- Filter bar -->
            <Card variant="base" padding="none" class="mb-4">
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <FormInput
                            v-model="selectedDate"
                            type="date"
                            :label="t('shifts.date')"
                            name="selected_date"
                            @change="reloadActiveTab"
                        />
                        <FormSelect
                            v-model="selectedDepartmentId"
                            :label="t('shifts.department')"
                            name="department_id"
                            :options="departmentOptions"
                        />
                        <FormMultiSelect
                            v-model="selectedRotationIds"
                            :label="t('shifts.rotation') + ' (' + t('shifts.all_rotations') + ')'"
                            name="rotation_ids"
                            :options="multiRotationOptions"
                            :placeholder="t('shifts.all_rotations')"
                            :search-placeholder="t('shifts.employee_search_placeholder')"
                            :max-visible-tags="2"
                        />
                        <FormMultiSelect
                            v-model="selectedRotationGroupIds"
                            :label="t('shifts.rotation_group')"
                            name="rotation_group_ids"
                            :options="multiGroupOptions"
                            :placeholder="t('shifts.all_groups')"
                            :search-placeholder="t('shifts.employee_search_placeholder')"
                            :max-visible-tags="2"
                            :disabled="multiGroupOptions.length === 0"
                        />
                    </div>

                    <!-- Active filter pills -->
                    <div v-if="filterPills.length" class="mt-4 flex items-center gap-2 flex-wrap pt-4 border-t border-mistral-hairline-soft">
                        <span class="text-[12px] text-mistral-steel font-medium">
                            <i class="fas fa-filter text-[10px] ms-1"></i>
                            {{ t('shifts.selected_rotations') }}:
                        </span>
                        <button
                            v-for="pill in filterPills"
                            :key="pill.key"
                            type="button"
                            @click="pill.clear"
                            class="inline-flex items-center gap-1.5 bg-mistral-primary/10 text-mistral-primary hover:bg-mistral-primary/15 rounded-md px-2.5 py-1 text-[12px] font-medium transition-colors"
                        >
                            {{ pill.label }}
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </Card>

            <!-- Absent list table -->
            <Card variant="base" padding="none" class="overflow-hidden">
                <div class="px-5 py-4 border-b border-mistral-hairline-soft flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h3 class="text-[15px] font-bold text-mistral-ink flex items-center gap-2">
                            <i class="fas fa-user-xmark text-mistral-danger text-[14px]"></i>
                            {{ t('shifts.absent_list_title') }}
                        </h3>
                        <p class="text-[12px] text-mistral-steel mt-0.5">
                            {{ t('shifts.absent_list_subtitle') }}
                        </p>
                    </div>
                    <Badge
                        :text="summary.totalAbsent + ' / ' + summary.totalExpected"
                        :variant="summary.totalAbsent > 0 ? 'absent' : 'active'"
                        dot
                        size="lg"
                    />
                </div>

                <DataTable
                    :columns="columns"
                    :data="dailyData.absent || { data: [] }"
                    :filters="filterParams"
                    route-name="smart-absence.daily"
                    :only="['dailyData']"
                    :empty-title="t('shifts.no_absent_employees')"
                    :empty-description="t('shifts.no_absent_employees_description')"
                    storage-key="smart-absence-report-daily"
                    @search="(q) => reloadActiveTab()"
                    @export="handleExport"
                >
                    <template #cell-name="{ row }">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-mistral-primary/10 text-mistral-primary flex items-center justify-center text-[12px] font-bold shrink-0">
                                {{ (row.name || '?').charAt(0) }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-[14px] font-semibold text-mistral-ink truncate">
                                    {{ row.name }}
                                </div>
                                <div v-if="row.job_title" class="text-[11px] text-mistral-steel truncate">
                                    {{ row.job_title }}
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #cell-employee_code="{ row }">
                        <span dir="ltr" class="font-mono text-[13px] text-mistral-ink bg-mistral-surface px-2 py-0.5 rounded">
                            {{ row.employee_code || '—' }}
                        </span>
                    </template>

                    <template #cell-department_name="{ row }">
                        <span class="text-mistral-ink text-[13px]">
                            {{ row.department_name || row.department_id || '—' }}
                        </span>
                    </template>

                    <template #cell-branch_name="{ row }">
                        <span class="text-mistral-ink text-[13px]">
                            {{ row.branch_name || '—' }}
                        </span>
                    </template>

                    <template #cell-position_name="{ row }">
                        <span class="text-mistral-ink text-[13px]">
                            {{ row.position_name || '—' }}
                        </span>
                    </template>

                    <template #cell-phone="{ row }">
                        <span v-if="row.phone" dir="ltr" class="text-[13px] text-mistral-steel">
                            <i class="fas fa-phone text-[10px] text-mistral-muted ms-1"></i>
                            {{ row.phone }}
                        </span>
                        <span v-else class="text-mistral-muted text-[13px]">—</span>
                    </template>

                    <template #cell-rotation_name="{ row }">
                        <span v-if="row.rotation_name" class="inline-flex items-center gap-1.5 text-mistral-ink text-[13px]">
                            <i class="fas fa-circle-notch text-[10px] text-mistral-primary"></i>
                            {{ row.rotation_name }}
                        </span>
                        <span v-else class="text-mistral-muted text-[13px]">—</span>
                    </template>

                    <template #cell-rotation_group_name="{ row }">
                        <div v-if="row.rotation_group_name" class="flex flex-col">
                            <span class="text-mistral-ink text-[13px]">{{ row.rotation_group_name }}</span>
                            <span v-if="row.expected_in" class="text-[11px] text-mistral-steel" dir="ltr">
                                <i class="fas fa-clock text-[9px] ms-1"></i>
                                {{ formatTime(row.expected_in) }}
                            </span>
                        </div>
                        <span v-else class="text-mistral-muted text-[13px]">—</span>
                    </template>

                    <template #cell-status="{ row }">
                        <Badge
                            :text="t('shifts.absent_short')"
                            variant="absent"
                            dot
                        />
                    </template>

                    <template #empty>
                        <EmptyState
                            icon="fas fa-user-check"
                            :title="t('shifts.no_absent_employees')"
                            :description="t('shifts.no_absent_employees_description')"
                        />
                    </template>
                </DataTable>
            </Card>
        </div>

        <!-- Monthly tab -->
        <div v-if="activeTab === 'monthly'">
            <!-- Hero stat cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <StatCard
                    :label="t('shifts.expected_days')"
                    :value="monthlySummary.expected"
                    icon="fas fa-calendar-check"
                    color="info"
                />
                <StatCard
                    :label="t('shifts.absent_days')"
                    :value="monthlySummary.absent"
                    icon="fas fa-user-xmark"
                    color="danger"
                />
                <StatCard
                    :label="t('shifts.present_days')"
                    :value="monthlySummary.present"
                    icon="fas fa-user-check"
                    color="success"
                />
                <StatCard
                    :label="t('shifts.attendance_rate')"
                    :value="monthlySummary.rate + '%'"
                    icon="fas fa-chart-pie"
                    :color="monthlySummary.rate >= 90 ? 'success' : monthlySummary.rate >= 70 ? 'warning' : 'danger'"
                />
            </div>

            <!-- Filter bar -->
            <Card variant="base" padding="none" class="mb-4">
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                        <FormSelect
                            v-model="selectedMonth"
                            :label="t('shifts.month')"
                            name="selected_month"
                            :options="monthOptions"
                        />
                        <FormInput
                            v-model.number="selectedYear"
                            type="number"
                            :label="t('shifts.year')"
                            name="selected_year"
                        />
                        <FormInput
                            v-model="fromDate"
                            type="date"
                            :label="t('shifts.from_date')"
                            name="from_date"
                        />
                        <FormInput
                            v-model="toDate"
                            type="date"
                            :label="t('shifts.to_date')"
                            name="to_date"
                        />
                        <FormSelect
                            v-model="selectedDepartmentId"
                            :label="t('shifts.department')"
                            name="department_id"
                            :options="departmentOptions"
                        />
                        <FormMultiSelect
                            v-model="selectedRotationIds"
                            :label="t('shifts.rotation')"
                            name="rotation_ids"
                            :options="multiRotationOptions"
                            :placeholder="t('shifts.all_rotations')"
                            :search-placeholder="t('shifts.employee_search_placeholder')"
                            :max-visible-tags="2"
                        />
                        <FormMultiSelect
                            v-model="selectedRotationGroupIds"
                            :label="t('shifts.rotation_group')"
                            name="rotation_group_ids"
                            :options="multiGroupOptions"
                            :placeholder="t('shifts.all_groups')"
                            :search-placeholder="t('shifts.employee_search_placeholder')"
                            :max-visible-tags="2"
                            :disabled="multiGroupOptions.length === 0"
                        />
                    </div>

                    <!-- Active filter pills -->
                    <div v-if="filterPills.length" class="mt-4 flex items-center gap-2 flex-wrap pt-4 border-t border-mistral-hairline-soft">
                        <span class="text-[12px] text-mistral-steel font-medium">
                            <i class="fas fa-filter text-[10px] ms-1"></i>
                            {{ t('shifts.selected_rotations') }}:
                        </span>
                        <button
                            v-for="pill in filterPills"
                            :key="pill.key"
                            type="button"
                            @click="pill.clear"
                            class="inline-flex items-center gap-1.5 bg-mistral-primary/10 text-mistral-primary hover:bg-mistral-primary/15 rounded-md px-2.5 py-1 text-[12px] font-medium transition-colors"
                        >
                            {{ pill.label }}
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </Card>

            <!-- Absent list table -->
            <Card variant="base" padding="none" class="overflow-hidden">
                <div class="px-5 py-4 border-b border-mistral-hairline-soft flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h3 class="text-[15px] font-bold text-mistral-ink flex items-center gap-2">
                            <i class="fas fa-user-xmark text-mistral-danger text-[14px]"></i>
                            {{ t('shifts.absent_list_range_title') }}
                        </h3>
                        <p class="text-[12px] text-mistral-steel mt-0.5">
                            {{ t('shifts.absent_list_range_subtitle') }}
                        </p>
                    </div>
                    <Badge
                        :text="monthlySummary.absent + ' / ' + monthlySummary.expected"
                        :variant="monthlySummary.absent > 0 ? 'absent' : 'active'"
                        dot
                        size="lg"
                    />
                </div>

                <DataTable
                    :columns="monthlyColumns"
                    :data="monthlyReportData.employees || { data: [] }"
                    :filters="monthlyFilterParams"
                    route-name="smart-absence.monthly.report"
                    :only="['monthlyReportData']"
                    :empty-title="t('shifts.no_absence_in_range')"
                    :empty-description="t('shifts.no_absence_in_range_description')"
                    storage-key="smart-absence-report-monthly"
                    @search="(q) => reloadActiveTab()"
                    @export="handleMonthlyExport"
                >
                    <template #cell-name="{ row }">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-mistral-primary/10 text-mistral-primary flex items-center justify-center text-[12px] font-bold shrink-0">
                                {{ (row.name || '?').charAt(0) }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-[14px] font-semibold text-mistral-ink truncate">
                                    {{ row.name }}
                                </div>
                                <div v-if="row.job_title" class="text-[11px] text-mistral-steel truncate">
                                    {{ row.job_title }}
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #cell-employee_code="{ row }">
                        <span dir="ltr" class="font-mono text-[13px] text-mistral-ink bg-mistral-surface px-2 py-0.5 rounded">
                            {{ row.employee_code || '—' }}
                        </span>
                    </template>

                    <template #cell-department_name="{ row }">
                        <span class="text-mistral-ink text-[13px]">
                            {{ row.department_name || '—' }}
                        </span>
                    </template>

                    <template #cell-rotation_name="{ row }">
                        <span v-if="row.rotation_name" class="inline-flex items-center gap-1.5 text-mistral-ink text-[13px]">
                            <i class="fas fa-circle-notch text-[10px] text-mistral-primary"></i>
                            {{ row.rotation_name }}
                        </span>
                        <span v-else class="text-mistral-muted text-[13px]">—</span>
                    </template>

                    <template #cell-rotation_group_name="{ row }">
                        <div v-if="row.rotation_group_name" class="flex flex-col">
                            <span class="text-mistral-ink text-[13px]">{{ row.rotation_group_name }}</span>
                            <span v-if="row.expected_in" class="text-[11px] text-mistral-steel" dir="ltr">
                                <i class="fas fa-clock text-[9px] ms-1"></i>
                                {{ formatTime(row.expected_in) }}
                            </span>
                        </div>
                        <span v-else class="text-mistral-muted text-[13px]">—</span>
                    </template>

                    <template #cell-expected_days="{ row }">
                        <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-md bg-mistral-info/10 text-mistral-info text-[13px] font-semibold">
                            {{ row.expected_days }}
                        </span>
                    </template>

                    <template #cell-present_days="{ row }">
                        <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-md bg-mistral-success/10 text-mistral-success text-[13px] font-semibold">
                            {{ row.present_days }}
                        </span>
                    </template>

                    <template #cell-absent_days="{ row }">
                        <Badge
                            :text="row.absent_days"
                            variant="absent"
                            dot
                            size="lg"
                        />
                    </template>

                    <template #cell-absent_dates="{ row }">
                        <div v-if="row.absent_dates?.length" class="flex flex-wrap gap-1">
                            <span
                                v-for="d in (row.absent_dates.length > 5 ? row.absent_dates.slice(0, 5) : row.absent_dates)"
                                :key="d"
                                dir="ltr"
                                :title="d"
                                class="text-[11px] font-mono text-mistral-danger bg-mistral-danger/10 rounded px-1.5 py-0.5"
                            >
                                {{ d.substring(5) }}
                            </span>
                            <span
                                v-if="row.absent_dates.length > 5"
                                class="text-[11px] text-mistral-steel"
                                :title="row.absent_dates.join('، ')"
                            >
                                +{{ row.absent_dates.length - 5 }}
                            </span>
                        </div>
                        <span v-else class="text-mistral-muted text-[13px]">—</span>
                    </template>

                    <template #cell-day_details="{ row }">
                        <div v-if="row.day_details?.length" class="flex flex-col gap-1.5">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="chip in visibleDayDetails(row)"
                                    :key="chip.date + chip.status"
                                    :title="chip.fullLabel"
                                    class="inline-flex items-center gap-1 text-[11px] rounded-md border px-1.5 py-0.5 font-medium cursor-help"
                                    :class="dayDetailStatusClass(chip.status)"
                                >
                                    <span dir="ltr" class="font-mono">{{ chip.shortDate }}</span>
                                    <span class="opacity-80">{{ chip.label }}</span>
                                </span>
                                <span
                                    v-if="row.day_details.length > MAX_VISIBLE_DETAILS"
                                    class="text-[11px] text-mistral-steel self-center"
                                    :title="allDayDetailsLabel(row)"
                                >
                                    +{{ row.day_details.length - MAX_VISIBLE_DETAILS }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-[10px] text-mistral-muted">
                                <span v-if="row.vacation_days" class="inline-flex items-center gap-1">
                                    <i class="fas fa-umbrella-beach text-mistral-info"></i>
                                    {{ t('shifts.on_vacation') }} {{ row.vacation_days }}
                                </span>
                                <span v-if="row.exception_days" class="inline-flex items-center gap-1">
                                    <i class="fas fa-briefcase text-mistral-primary"></i>
                                    {{ t('shifts.on_exception') }} {{ row.exception_days }}
                                </span>
                                <span v-if="row.holiday_days" class="inline-flex items-center gap-1">
                                    <i class="fas fa-flag text-mistral-ink"></i>
                                    {{ t('shifts.official_holiday') }} {{ row.holiday_days }}
                                </span>
                            </div>
                        </div>
                        <span v-else class="text-mistral-muted text-[13px]">—</span>
                    </template>

                    <template #empty>
                        <EmptyState
                            icon="fas fa-user-check"
                            :title="t('shifts.no_absence_in_range')"
                            :description="t('shifts.no_absence_in_range_description')"
                        />
                    </template>
                </DataTable>
            </Card>
        </div>
    </AppLayout>
</template>
