<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import DataTable from '@/Components/ui/DataTable.vue'
import Badge from '@/Components/ui/Badge.vue'
import StatCard from '@/Components/StatCard.vue'
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
    { key: 'date', label: 'التاريخ', cellClass: 'text-center' },
    { key: 'expected_time', label: 'الوقت المتوقع', cellClass: 'text-center' },
    { key: 'status', label: 'الحالة', cellClass: 'text-center' },
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
        absent: 'غياب',
        late: 'متأخر',
        early: 'خروج مبكر',
        present: 'حضور',
        on_leave: 'إجازة',
    }
    return map[status] || status
}

const monthNames = [
    'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
]

const monthLabel = computed(() => `${monthNames[selectedMonth - 1]} ${selectedYear}`)
</script>

<template>
    <AppLayout :title="'غياباتي'">
        <PageHeader :title="'غياباتي'" />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <StatCard
                label="إجمالي أيام العمل"
                :value="stats.totalWorkDays"
                icon="fas fa-briefcase"
                color="info"
            />
            <StatCard
                label="أيام الغياب"
                :value="stats.absentDays"
                icon="fas fa-user-times"
                color="danger"
            />
            <StatCard
                label="نسبة الحضور"
                :value="stats.attendanceRate + '%'"
                icon="fas fa-chart-pie"
                :color="stats.attendanceRate >= 90 ? 'success' : stats.attendanceRate >= 75 ? 'warning' : 'danger'"
            />
        </div>

        <div class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
            <button @click="changeMonth(-1)" class="btn btn-sm btn-outline">&laquo;</Button>
            <span class="text-sm font-semibold min-w-[140px] text-center">{{ monthLabel }}</span>
            <button @click="changeMonth(1)" class="btn btn-sm btn-outline">&raquo;</Button>
        </div>

        <DataTable :columns="columns" :data="{ data: monthlyData }" :empty-title="'لا توجد غيابات لهذا الشهر'">
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
