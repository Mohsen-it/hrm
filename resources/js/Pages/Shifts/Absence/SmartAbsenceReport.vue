<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import DataTable from '@/Components/ui/DataTable.vue'
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
    { key: 'name', label: 'الاسم' },
    { key: 'employee_code', label: 'الكود' },
    { key: 'department_id', label: 'القسم' },
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
        absent: 'bg-red-500 text-white',
        present: 'bg-green-500 text-white',
        on_leave: 'bg-blue-400 text-white',
    }
    return map[status] || 'bg-gray-200'
}
</script>

<template>
    <AppLayout :title="'تقرير الغياب الذكي'">
        <PageHeader title="تقرير الغياب الذكي" />

        <!-- Tabs -->
        <div class="flex gap-1 mb-4 border-b">
            <button
                @click="activeTab = 'daily'"
                :class="activeTab === 'daily' ? 'border-b-2 border-blue-500 text-blue-600 font-bold' : 'text-gray-500'"
                class="px-4 py-2 text-sm"
            >
                يومي
            </Button>
            <button
                @click="activeTab = 'monthly'"
                :class="activeTab === 'monthly' ? 'border-b-2 border-blue-500 text-blue-600 font-bold' : 'text-gray-500'"
                class="px-4 py-2 text-sm"
            >
                شهري
            </Button>
        </div>

        <!-- Daily tab -->
        <div v-if="activeTab === 'daily'">
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <input
                    type="date"
                    v-model="selectedDate"
                    class="form-input max-w-[200px]"
                    @change="loadDaily"
                />
                <div class="flex gap-3 text-sm">
                    <span class="text-gray-600">المتوقع: <strong>{{ dailyData.total_expected || 0 }}</strong></span>
                    <span class="text-red-600">الغائب: <strong>{{ dailyData.total_absent || 0 }}</strong></span>
                </div>
            </div>

            <DataTable :columns="columns" :data="{ data: dailyData.absent || [] }">
                <template #cell-department_id="{ row }">
                    <span>{{ row.department_id || '—' }}</span>
                </template>
            </DataTable>
        </div>

        <!-- Monthly tab -->
        <div v-if="activeTab === 'monthly'">
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <input
                    type="number"
                    v-model.number="selectedEmployeeId"
                    placeholder="رقم الموظف"
                    class="form-input max-w-[160px]"
                />
                <select v-model.number="selectedMonth" class="form-input max-w-[140px]" @change="loadMonthly">
                    <option v-for="m in 12" :key="m" :value="m">{{ m }}</option>
                </select>
                <input
                    type="number"
                    v-model.number="selectedYear"
                    class="form-input max-w-[100px]"
                    @change="loadMonthly"
                />
                <button @click="loadMonthly" class="btn btn-primary btn-sm">عرض</Button>
            </div>

            <CalendarLegend showRest class="mb-4" />

            <!-- Calendar grid for monthly -->
            <div v-if="monthlyData.length" class="grid grid-cols-7 gap-1 text-center">
                <div class="text-xs font-bold text-gray-500 py-2">سبت</div>
                <div class="text-xs font-bold text-gray-500 py-2">أحد</div>
                <div class="text-xs font-bold text-gray-500 py-2">إثنين</div>
                <div class="text-xs font-bold text-gray-500 py-2">ثلاثاء</div>
                <div class="text-xs font-bold text-gray-500 py-2">أربعاء</div>
                <div class="text-xs font-bold text-gray-500 py-2">خميس</div>
                <div class="text-xs font-bold text-gray-500 py-2">جمعة</div>

                <div
                    v-for="(cell, i) in monthlyData"
                    :key="cell.date"
                    :class="[statusColor(cell.status), 'rounded-lg p-2 min-h-[50px] flex items-center justify-center']"
                    :title="cell.date + ': ' + cell.status"
                >
                    <span class="text-xs">{{ new Date(cell.date).getDate() }}</span>
                </div>
            </div>
            <div v-else class="text-gray-400 text-sm mt-4">لم يتم تحديد شهر أو موظف.</div>
        </div>
    </AppLayout>
</template>
