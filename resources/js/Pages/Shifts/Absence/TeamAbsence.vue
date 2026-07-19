<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Card from '@/Components/ui/Card.vue'
import DataTable from '@/Components/ui/DataTable.vue'
import FormInput from '@/Components/ui/FormInput.vue'
import { StatCard } from '@/Components/ui'
import Badge from '@/Components/ui/Badge.vue'
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
    { key: 'name', label: t('shifts.employee_name'), sortable: true, filterable: true },
    { key: 'department', label: t('shifts.department'), sortable: true, filterable: true },
    { key: 'category', label: t('shifts.category'), sortable: true, filterable: true },
    { key: 'expected_in', label: t('shifts.expected_in_time'), sortable: true, cellClass: 'text-center' },
    { key: 'status', label: t('shifts.status'), sortable: true, filterable: true, cellClass: 'text-center' },
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
    <AppLayout :title="t('shifts.team_absence')">
        <PageHeader :title="t('shifts.team_absence')" />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <StatCard
                :label="t('shifts.total_team_members')"
                :value="totalTeam"
                icon="fas fa-users"
                color="info"
            />
            <StatCard
                :label="t('shifts.absent_today')"
                :value="absentCount"
                icon="fas fa-user-times"
                color="danger"
            />
            <StatCard
                :label="t('shifts.attendance_rate')"
                :value="totalTeam ? Math.round(((totalTeam - absentCount) / totalTeam) * 100) + '%' : '0%'"
                icon="fas fa-chart-pie"
                :color="absentCount > totalTeam / 2 ? 'danger' : 'success'"
            />
        </div>

        <Card variant="base" padding="none" class="mb-6">
            <div class="p-5 sm:p-6">
                <FormInput
                    v-model="selectedDate"
                    type="date"
                    :label="t('shifts.date')"
                    name="selected_date"
                    @change="loadData"
                />
            </div>
        </Card>

        <DataTable
            :columns="columns"
            :data="{ data: absent }"
            :empty-title="t('shifts.no_absence_data')"
            storage-key="team-absence"
            @search="(q) => loadData()"
            @page-change="(p) => loadData()"
            @per-page-change="(p) => loadData()"
        >
            <template #cell-name="{ row }">
                <div class="text-[14px] font-medium text-mistral-ink">{{ row.name }}</div>
            </template>

            <template #cell-department="{ row }">
                <span class="text-mistral-ink">{{ row.department_name || '—' }}</span>
            </template>

            <template #cell-category="{ row }">
                <span class="text-mistral-ink">{{ row.category_name || '—' }}</span>
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
