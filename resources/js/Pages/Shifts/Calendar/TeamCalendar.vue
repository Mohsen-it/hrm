<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    teamIds: { type: Array, default: () => [] },
    month: { type: Number, default: () => new Date().getMonth() + 1 },
    year: { type: Number, default: () => new Date().getFullYear() },
})

const selectedMonth = ref(props.month)
const selectedYear = ref(props.year)

function loadWeek() {
    router.get(
        route('team-calendar'),
        { month: selectedMonth.value, year: selectedYear.value },
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

function statusColor(status) {
    const map = {
        work: 'bg-green-500 text-white',
        rest: 'bg-gray-200 text-gray-600',
        absent: 'bg-red-500 text-white',
        holiday: 'bg-yellow-400 text-white',
        present: 'bg-green-600 text-white',
        on_leave: 'bg-blue-400 text-white',
    }
    return map[status] || 'bg-gray-200 text-gray-600'
}

function dayLabel(status) {
    const map = {
        work: 'د',
        rest: 'ر',
        absent: 'غ',
        holiday: 'ع',
        present: 'ح',
        on_leave: 'إ',
    }
    return map[status] || ''
}

const dayNames = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة']
</script>

<template>
    <AppLayout :title="'تقويم الفريق'">
        <PageHeader :title="'تقويم الفريق'" />

        <div class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
            <button @click="selectedMonth--; if(selectedMonth < 1) { selectedMonth = 12; selectedYear-- }; loadWeek()" class="btn btn-sm btn-outline">&laquo;</Button>
            <span class="font-bold min-w-[120px] text-center">{{ month }} / {{ year }}</span>
            <button @click="selectedMonth++; if(selectedMonth > 12) { selectedMonth = 1; selectedYear++ }; loadWeek()" class="btn btn-sm btn-outline">&raquo;</Button>
        </div>

        <CalendarLegend class="mb-4" />

        <div class="card overflow-x-auto">
            <table class="w-full text-right border-collapse" dir="rtl">
                <thead>
                    <tr class="bg-[var(--color-surface-1)] border-b border-[var(--color-hairline)]">
                        <th class="px-4 py-3 text-[13px] font-semibold text-[var(--color-ink-muted)] min-w-[160px]">
                            الموظف
                        </th>
                        <th
                            v-for="(d, i) in (dates.length ? dates : props.date ? [props.date] : [])"
                            :key="d"
                            class="px-3 py-3 text-[13px] font-semibold text-[var(--color-ink-muted)] text-center min-w-[80px]"
                        >
                            <div>{{ dayNames[i] || dayNames[new Date(d).getDay()] }}</div>
                            <div class="text-[10px] text-[var(--color-ink-subtle)]">{{ d.slice(5) }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="emp in team"
                        :key="emp.id"
                        class="border-b border-[var(--color-hairline)] hover:bg-[var(--color-surface-3)] transition-colors"
                    >
                        <td class="px-4 py-2">
                            <div class="text-[14px] font-medium">{{ emp.name }}</div>
                            <div class="text-[11px] text-[var(--color-ink-subtle)]">{{ emp.emp_code || '' }}</div>
                        </td>
                        <td
                            v-for="(d, j) in emp.daily_status || []"
                            :key="d.date || j"
                            class="px-2 py-0"
                        >
                            <div
                                :class="[statusColor(d.status), 'w-9 h-9 rounded-lg flex items-center justify-center mx-auto text-[12px] font-bold']"
                                :title="(d.date || '') + ': ' + (d.status || '')"
                            >
                                {{ dayLabel(d.status) }}
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!teamIds.length">
                        <td :colspan="(dates.length || 1) + 1" class="py-12 text-center text-[var(--color-ink-muted)]">
                            {{ t('shifts.no_data') || 'لا توجد بيانات' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
