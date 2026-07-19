<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Card from '@/Components/ui/Card.vue'
import Button from '@/Components/ui/Button.vue'
import DataTable from '@/Components/ui/DataTable.vue'
import Badge from '@/Components/ui/Badge.vue'
import { StatCard } from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    monthlyData: { type: Array, default: () => [] },
    month: { type: Number, default: () => new Date().getMonth() + 1 },
    year: { type: Number, default: () => new Date().getFullYear() },
})

const selectedMonth = ref(props.month)
const selectedYear = ref(props.year)

const stats = computed(() => {
    const total = props.monthlyData.length
    const absent = props.monthlyData.filter(d => d.status === 'absent').length
    return {
        totalWorkDays: total,
        absentDays: absent,
        attendanceRate: total > 0 ? Math.round(((total - absent) / total) * 100) : 0,
    }
})

function changeMonth(delta) {
    let m = selectedMonth.value + delta
    let y = selectedYear.value
    if (m < 1) { m = 12; y-- }
    if (m > 12) { m = 1; y++ }
    selectedMonth.value = m
    selectedYear.value = y
    loadData()
}

function loadData() {
    router.get(
        route('my-absence'),
        { month: selectedMonth.value, year: selectedYear.value },
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

const columns = computed(() => [
    { key: 'date', label: t('shifts.date'), sortable: true, cellClass: 'text-center' },
    { key: 'expected_time', label: t('shifts.expected_time'), sortable: true, cellClass: 'text-center' },
    { key: 'status', label: t('shifts.status'), sortable: true, filterable: true, cellClass: 'text-center' },
])

function statusVariant(status) {
    const map = {
        absent: 'absent',
        late: 'pending',
        early: 'info',
        present: 'active',
        on_leave: 'vacation',
    }
    return map[status] || 'inactive'
}

function statusLabel(status) {
    const map = {
        absent: t('shifts.absent'),
        late: t('shifts.late'),
        early: t('shifts.early_leave'),
        present: t('shifts.present'),
        on_leave: t('shifts.on_leave'),
    }
    return map[status] || status
}

const monthNames = computed(() => [
    t('shifts.january'), t('shifts.february'), t('shifts.march'), t('shifts.april'),
    t('shifts.may'), t('shifts.june'), t('shifts.july'), t('shifts.august'),
    t('shifts.september'), t('shifts.october'), t('shifts.november'), t('shifts.december'),
])

const monthLabel = computed(() => `${monthNames.value[selectedMonth.value - 1]} ${selectedYear.value}`)
</script>

<template>
    <AppLayout :title="t('shifts.my_absence')">
        <PageHeader :title="t('shifts.my_absence')" />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <StatCard
                :label="t('shifts.total_work_days')"
                :value="stats.totalWorkDays"
                icon="fas fa-briefcase"
                color="info"
            />
            <StatCard
                :label="t('shifts.absent_days')"
                :value="stats.absentDays"
                icon="fas fa-user-times"
                color="danger"
            />
            <StatCard
                :label="t('shifts.attendance_rate')"
                :value="stats.attendanceRate + '%'"
                icon="fas fa-chart-pie"
                :color="stats.attendanceRate >= 90 ? 'success' : stats.attendanceRate >= 75 ? 'warning' : 'danger'"
            />
        </div>

        <Card variant="base" padding="none" class="mb-6">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-3 flex-wrap">
                    <Button variant="secondary" size="sm" icon="fas fa-chevron-right" @click="changeMonth(-1)" />
                    <span class="text-sm font-semibold min-w-[140px] text-center text-mistral-ink">{{ monthLabel }}</span>
                    <Button variant="secondary" size="sm" icon-left="fas fa-chevron-left" @click="changeMonth(1)" />
                </div>
            </div>
        </Card>

        <DataTable
            :columns="columns"
            :data="{ data: monthlyData }"
            :empty-title="t('shifts.no_absence_this_month')"
            storage-key="my-absence"
            @search="(q) => loadData()"
            @page-change="(p) => loadData()"
            @per-page-change="(p) => loadData()"
        >
            <template #cell-date="{ row }">
                <span dir="ltr">{{ row.date || '—' }}</span>
            </template>

            <template #cell-expected_time="{ row }">
                <span dir="ltr">{{ row.expected_time || '—' }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge
                    :text="statusLabel(row.status)"
                    :variant="statusVariant(row.status)"
                />
            </template>
        </DataTable>
    </AppLayout>
</template>
