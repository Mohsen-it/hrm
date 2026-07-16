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
    absent: { type: Array, default: () => [] },
    date: { type: String, default: () => new Date().toISOString().split('T')[0] },
    total_absent: { type: Number, default: 0 },
})

const selectedDate = ref(props.date)

const totalTeam = computed(() => props.absent.length)
const absentCount = computed(() => props.total_absent)

function loadData() {
    router.get(
        route('smart-absence.team'),
        { date: selectedDate.value },
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

const columns = computed(() => [
    { key: 'name', label: 'الموظف' },
    { key: 'department', label: 'القسم' },
    { key: 'category', label: 'الفئة' },
    { key: 'expected_in', label: 'وقت الحضور المتوقع', cellClass: 'text-center' },
    { key: 'status', label: 'الحالة', cellClass: 'text-center' },
])

function statusVariant(status) {
    const map = {
        present: 'active',
        absent: 'absent',
        late: 'pending',
        early_leave: 'info',
        on_leave: 'vacation',
    }
    return map[status] || 'inactive'
}
</script>

<template>
    <AppLayout :title="'غياب الفريق'">
        <PageHeader :title="'غياب الفريق'" />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <StatCard
                label="إجمالي أعضاء الفريق"
                :value="totalTeam"
                icon="fas fa-users"
                color="info"
            />
            <StatCard
                label="الغائبون اليوم"
                :value="absentCount"
                icon="fas fa-user-times"
                color="danger"
            />
            <StatCard
                label="نسبة الحضور"
                :value="totalTeam ? Math.round(((totalTeam - absentCount) / totalTeam) * 100) + '%' : '0%'"
                icon="fas fa-chart-pie"
                :color="absentCount > totalTeam / 2 ? 'danger' : 'success'"
            />
        </div>

        <div class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
            <label class="flex items-center gap-2 text-sm">
                <span class="text-gray-600">التاريخ:</span>
                <input
                    type="date"
                    v-model="selectedDate"
                    class="form-input max-w-[180px]"
                    @change="loadData"
                />
            </label>
        </div>

        <DataTable :columns="columns" :data="{ data: absent }" :empty-title="'لا توجد بيانات غياب'">
            <template #cell-name="{ row }">
                <div class="text-[14px] font-medium">{{ row.name }}</div>
            </template>

            <template #cell-department="{ row }">
                <span>{{ row.department_name || '—' }}</span>
            </template>

            <template #cell-category="{ row }">
                <span>{{ row.category_name || '—' }}</span>
            </template>

            <template #cell-expected_in="{ row }">
                <span dir="ltr">{{ row.expected_in_time || '—' }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge
                    :text="row.status_label || row.status || '—'"
                    :variant="statusVariant(row.status)"
                />
            </template>
        </DataTable>
    </AppLayout>
</template>
