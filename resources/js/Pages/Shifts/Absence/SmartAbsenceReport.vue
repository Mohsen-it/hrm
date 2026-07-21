<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    PageHeader, Card, Button, DataTable, FormInput, FormSelect, FormMultiSelect,
    StatCard, Badge, EmptyState, LoadingSpinner,
} from '@/Components/ui'
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    dailyData: { type: Object, default: () => ({ expected: [], absent: [], total_expected: 0, total_absent: 0, attendance_rate: 100, date: '' }) },
    monthlyData: { type: Array, default: () => [] },
    rotations: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const activeTab = ref(props.dailyData?.date ? 'daily' : 'monthly')

const selectedDate = ref(props.filters?.date || new Date().toISOString().split('T')[0])
const selectedDepartmentId = ref(props.filters?.department_id || null)
const selectedRotationIds = ref(Array.isArray(props.filters?.rotation_ids) ? props.filters.rotation_ids : (props.filters?.rotation_id ? [props.filters.rotation_id] : []))
const selectedRotationGroupIds = ref(Array.isArray(props.filters?.rotation_group_ids) ? props.filters.rotation_group_ids : (props.filters?.rotation_group_id ? [props.filters.rotation_group_id] : []))
const selectedMonth = ref(props.filters?.month || new Date().getMonth() + 1)
const selectedYear = ref(props.filters?.year || new Date().getFullYear())

const selectedEmployeeId = ref(props.filters?.employee_id || null)
const employeeSearch = ref(props.filters?.employee_name || '')
const employeeResults = ref([])
const searchingEmployees = ref(false)
const showEmployeeDropdown = ref(false)
const selectedEmployeeName = ref(props.filters?.employee_name || '')

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

watch(selectedRotationIds, () => {
    if (selectedRotationIds.value.length === 0) {
        selectedRotationGroupIds.value = []
    } else {
        const validGroupIds = new Set(multiGroupOptions.value.map((g) => g.value))
        selectedRotationGroupIds.value = selectedRotationGroupIds.value.filter((id) => validGroupIds.has(id))
    }
    loadDaily()
})

watch(selectedRotationGroupIds, () => {
    loadDaily()
})

watch(selectedDepartmentId, () => {
    loadDaily()
})

function loadDaily() {
    router.get(route('smart-absence.daily'), {
        date: selectedDate.value,
        department_id: selectedDepartmentId.value || null,
        rotation_ids: selectedRotationIds.value,
        rotation_group_ids: selectedRotationGroupIds.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function clearFilters() {
    selectedDepartmentId.value = null
    selectedRotationIds.value = []
    selectedRotationGroupIds.value = []
    loadDaily()
}

let searchTimeout = null
async function searchEmployees(query) {
    if (!query || query.length < 2) {
        employeeResults.value = []
        return
    }
    searchingEmployees.value = true
    try {
        const response = await fetch(route('rotations.search-employees') + `?search=${encodeURIComponent(query)}`)
        const data = await response.json()
        employeeResults.value = data.employees || []
    } catch (e) {
        employeeResults.value = []
    } finally {
        searchingEmployees.value = false
    }
}

function onEmployeeSearchInput(value) {
    employeeSearch.value = value
    showEmployeeDropdown.value = true
    if (!value) selectedEmployeeId.value = null
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => searchEmployees(value), 300)
}

function selectEmployee(emp) {
    selectedEmployeeId.value = emp.id
    selectedEmployeeName.value = emp.name || emp.full_name
    employeeSearch.value = emp.name || emp.full_name
    showEmployeeDropdown.value = false
    employeeResults.value = []
}

function clearEmployeeSelection() {
    selectedEmployeeId.value = null
    selectedEmployeeName.value = ''
    employeeSearch.value = ''
    showEmployeeDropdown.value = false
}

function loadMonthly() {
    if (!selectedEmployeeId.value) return
    router.get(route('smart-absence.monthly', { employee: selectedEmployeeId.value }), {
        month: selectedMonth.value,
        year: selectedYear.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
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

function statusColor(status) {
    const map = {
        absent: 'bg-mistral-danger text-white',
        present: 'bg-mistral-success text-white',
        on_leave: 'bg-mistral-info text-white',
    }
    return map[status] || 'bg-mistral-surface text-mistral-steel'
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

function formatTime(value) {
    if (!value) return '—'
    const s = String(value)
    return s.length >= 5 ? s.substring(0, 5) : s
}

const filterPills = computed(() => {
    const pills = []
    if (selectedDepartmentId.value) {
        const dept = props.departments.find((d) => d.id === selectedDepartmentId.value)
        if (dept) pills.push({ key: 'department', label: dept.name, clear: () => { selectedDepartmentId.value = null; loadDaily() } })
    }
    selectedRotationIds.value.forEach((id) => {
        const r = props.rotations.find((rot) => rot.id === id)
        if (r) pills.push({
            key: `rot-${id}`,
            label: t('shifts.rotation') + ': ' + r.name,
            clear: () => { selectedRotationIds.value = selectedRotationIds.value.filter((x) => x !== id); loadDaily() },
        })
    })
    selectedRotationGroupIds.value.forEach((id) => {
        const g = allGroupOptions.value.find((grp) => grp.value === id)
        if (g) pills.push({
            key: `grp-${id}`,
            label: t('shifts.rotation_group') + ': ' + g.label,
            clear: () => { selectedRotationGroupIds.value = selectedRotationGroupIds.value.filter((x) => x !== id); loadDaily() },
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
                    v-if="activeTab === 'daily' && hasActiveFilters"
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
                            @change="loadDaily"
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
                            :placeholder="selectedRotationIds.length === 0 ? t('shifts.all_groups') : t('shifts.all_groups')"
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
                    :data="{ data: dailyData.absent || [] }"
                    :empty-title="t('shifts.no_absent_employees')"
                    :empty-description="t('shifts.no_absent_employees_description')"
                    storage-key="smart-absence-report-daily"
                    @search="(q) => loadDaily()"
                    @page-change="(p) => loadDaily()"
                    @per-page-change="(p) => loadDaily()"
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
            <Card variant="base" padding="none" class="mb-5">
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                        <div class="relative">
                            <label class="block text-[12px] font-semibold text-mistral-slate uppercase tracking-wider mb-1.5">
                                {{ t('shifts.employee') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <i class="fas fa-search absolute top-1/2 -translate-y-1/2 text-mistral-muted text-[12px]"
                                       style="inset-inline-start: 0.75rem;"></i>
                                    <input
                                        type="text"
                                        :value="employeeSearch"
                                        @input="onEmployeeSearchInput($event.target.value)"
                                        @focus="showEmployeeDropdown = true"
                                        :placeholder="t('shifts.employee_search_placeholder')"
                                        class="w-full h-11 text-[14px] bg-white border border-mistral-hairline-strong rounded-lg focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary"
                                        style="padding-inline-start: 2.25rem; padding-inline-end: 0.75rem;"
                                    />
                                </div>
                                <LoadingSpinner v-if="searchingEmployees" size="sm" />
                                <button
                                    v-if="selectedEmployeeId"
                                    @click="clearEmployeeSelection"
                                    class="text-mistral-steel hover:text-mistral-danger w-9 h-9 flex items-center justify-center rounded-lg hover:bg-mistral-surface"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div
                                v-if="showEmployeeDropdown && employeeResults.length > 0"
                                class="absolute z-50 mt-1 w-full bg-white border border-mistral-hairline-strong rounded-lg shadow-lg max-h-60 overflow-auto"
                            >
                                <button
                                    v-for="emp in employeeResults"
                                    :key="emp.id"
                                    @click="selectEmployee(emp)"
                                    class="w-full text-start px-3 py-2 hover:bg-mistral-surface text-[13px] border-b border-mistral-hairline-soft last:border-0"
                                >
                                    <div class="font-medium text-mistral-ink">{{ emp.name }}</div>
                                    <div class="text-[11px] text-mistral-steel" dir="ltr">{{ emp.employee_code }}</div>
                                </button>
                            </div>
                            <div
                                v-if="showEmployeeDropdown && employeeSearch.length >= 2 && employeeResults.length === 0 && !searchingEmployees"
                                class="absolute z-50 mt-1 w-full bg-white border border-mistral-hairline-strong rounded-lg shadow-lg p-3 text-[13px] text-mistral-steel"
                            >
                                {{ t('common.no_results') }}
                            </div>
                        </div>
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
                        <Button variant="primary" icon="fas fa-search" @click="loadMonthly" :disabled="!selectedEmployeeId">
                            {{ t('common.view') }}
                        </Button>
                    </div>
                </div>
            </Card>

            <CalendarLegend showRest class="mb-4" />

            <Card variant="base" padding="none">
                <div v-if="monthlyData.length" class="p-5">
                    <div class="grid grid-cols-7 gap-1 text-center">
                        <div class="text-[11px] font-bold text-mistral-slate py-2">{{ t('shifts.sat_short') }}</div>
                        <div class="text-[11px] font-bold text-mistral-slate py-2">{{ t('shifts.sun_short') }}</div>
                        <div class="text-[11px] font-bold text-mistral-slate py-2">{{ t('shifts.mon_short') }}</div>
                        <div class="text-[11px] font-bold text-mistral-slate py-2">{{ t('shifts.tue_short') }}</div>
                        <div class="text-[11px] font-bold text-mistral-slate py-2">{{ t('shifts.wed_short') }}</div>
                        <div class="text-[11px] font-bold text-mistral-slate py-2">{{ t('shifts.thu_short') }}</div>
                        <div class="text-[11px] font-bold text-mistral-slate py-2">{{ t('shifts.fri_short') }}</div>

                        <div
                            v-for="(cell, i) in monthlyData"
                            :key="cell.date"
                            :class="[statusColor(cell.status), 'rounded-lg p-2 min-h-[52px] flex flex-col items-center justify-center']"
                            :title="cell.date + ': ' + cell.status"
                        >
                            <span class="text-[12px] font-semibold">{{ new Date(cell.date).getDate() }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="p-8">
                    <EmptyState
                        icon="fas fa-calendar"
                        :title="t('shifts.select_month_or_employee')"
                    />
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
