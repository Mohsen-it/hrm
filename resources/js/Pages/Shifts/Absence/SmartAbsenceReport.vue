<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { PageHeader, Card, Button, DataTable, FormInput, FormSelect } from '@/Components/ui'
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    dailyData: { type: Object, default: () => ({ expected: [], absent: [], total_expected: 0, total_absent: 0, date: '' }) },
    monthlyData: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const activeTab = ref(props.dailyData?.date ? 'daily' : 'monthly')
const selectedDate = ref(props.filters?.date || new Date().toISOString().split('T')[0])
const selectedMonth = ref(props.filters?.month || new Date().getMonth() + 1)
const selectedYear = ref(props.filters?.year || new Date().getFullYear())
const selectedEmployeeId = ref(props.filters?.employee_id || null)

const columns = computed(() => [
    { key: 'name', label: t('shifts.employee_name'), sortable: true, filterable: true },
    { key: 'employee_code', label: t('shifts.code'), sortable: true, filterable: true },
    { key: 'department_id', label: t('shifts.department'), sortable: true, filterable: true },
])

function loadDaily() {
    router.get(route('smart-absence.daily'), {
        date: selectedDate.value,
        department_id: props.filters?.department_id,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function loadMonthly() {
    if (!selectedEmployeeId.value) return
    router.get(route('smart-absence.monthly', { employee: selectedEmployeeId.value }), {
        month: selectedMonth.value,
        year: selectedYear.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
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
</script>

<template>
    <AppLayout :title="t('shifts.smart_absence_report')">
        <PageHeader :title="t('shifts.smart_absence_report')" />

        <Card variant="base" padding="none" class="mb-6">
            <nav class="flex items-center gap-0 border-b border-mistral-hairline-soft overflow-x-auto" role="tablist">
                <button
                    @click="activeTab = 'daily'"
                    :class="activeTab === 'daily' ? 'border-b-2 border-mistral-primary text-mistral-primary font-bold' : 'text-mistral-steel border-transparent hover:text-mistral-ink'"
                    class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2"
                    role="tab"
                    :aria-selected="activeTab === 'daily'"
                >
                    {{ t('shifts.daily') }}
                </button>
                <button
                    @click="activeTab = 'monthly'"
                    :class="activeTab === 'monthly' ? 'border-b-2 border-mistral-primary text-mistral-primary font-bold' : 'text-mistral-steel border-transparent hover:text-mistral-ink'"
                    class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2"
                    role="tab"
                    :aria-selected="activeTab === 'monthly'"
                >
                    {{ t('shifts.monthly') }}
                </button>
            </nav>
        </Card>

        <!-- Daily tab -->
        <div v-if="activeTab === 'daily'">
            <Card variant="base" padding="none" class="mb-6">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center gap-4 flex-wrap">
                        <FormInput
                            v-model="selectedDate"
                            type="date"
                            :label="t('shifts.date')"
                            name="selected_date"
                            @change="loadDaily"
                        />
                        <div class="flex gap-4 text-sm">
                            <span class="text-mistral-slate">{{ t('shifts.expected') }}: <strong class="text-mistral-ink">{{ dailyData.total_expected || 0 }}</strong></span>
                            <span class="text-mistral-danger">{{ t('shifts.absent') }}: <strong>{{ dailyData.total_absent || 0 }}</strong></span>
                        </div>
                    </div>
                </div>
            </Card>

            <DataTable
                :columns="columns"
                :data="{ data: dailyData.absent || [] }"
                storage-key="smart-absence-report-daily"
                @search="(q) => loadDaily()"
                @page-change="(p) => loadDaily()"
                @per-page-change="(p) => loadDaily()"
            >
                <template #cell-department_id="{ row }">
                    <span class="text-mistral-ink">{{ row.department_id || '—' }}</span>
                </template>
            </DataTable>
        </div>

        <!-- Monthly tab -->
        <div v-if="activeTab === 'monthly'">
            <Card variant="base" padding="none" class="mb-6">
                <div class="p-5 sm:p-6">
                    <div class="flex items-end gap-4 flex-wrap">
                        <FormInput
                            v-model.number="selectedEmployeeId"
                            type="number"
                            :label="t('shifts.employee_id')"
                            name="employee_id"
                            :placeholder="t('shifts.employee_id')"
                        />
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
                        <Button variant="primary" size="sm" icon="fas fa-search" @click="loadMonthly">
                            {{ t('common.view') }}
                        </Button>
                    </div>
                </div>
            </Card>

            <CalendarLegend showRest class="mb-4" />

            <Card variant="base" padding="none">
                <div v-if="monthlyData.length" class="p-5 sm:p-6">
                    <div class="grid grid-cols-7 gap-1 text-center">
                        <div class="text-xs font-bold text-mistral-slate py-2">{{ t('shifts.sat_short') }}</div>
                        <div class="text-xs font-bold text-mistral-slate py-2">{{ t('shifts.sun_short') }}</div>
                        <div class="text-xs font-bold text-mistral-slate py-2">{{ t('shifts.mon_short') }}</div>
                        <div class="text-xs font-bold text-mistral-slate py-2">{{ t('shifts.tue_short') }}</div>
                        <div class="text-xs font-bold text-mistral-slate py-2">{{ t('shifts.wed_short') }}</div>
                        <div class="text-xs font-bold text-mistral-slate py-2">{{ t('shifts.thu_short') }}</div>
                        <div class="text-xs font-bold text-mistral-slate py-2">{{ t('shifts.fri_short') }}</div>

                        <div
                            v-for="(cell, i) in monthlyData"
                            :key="cell.date"
                            :class="[statusColor(cell.status), 'rounded-lg p-2 min-h-[50px] flex items-center justify-center']"
                            :title="cell.date + ': ' + cell.status"
                        >
                            <span class="text-xs">{{ new Date(cell.date).getDate() }}</span>
                        </div>
                    </div>
                </div>
                <div v-else class="p-5 sm:p-6 text-center text-mistral-muted text-sm">
                    {{ t('shifts.select_month_or_employee') }}
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
