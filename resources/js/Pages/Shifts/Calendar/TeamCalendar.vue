<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { PageHeader, Card, Button } from '@/Components/ui'
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    team: { type: Array, default: () => [] },
    dates: { type: Array, default: () => [] },
    date: { type: String, default: null },
    month: { type: Number, default: () => new Date().getMonth() + 1 },
    year: { type: Number, default: () => new Date().getFullYear() },
})

const selectedMonth = ref(props.month)
const selectedYear = ref(props.year)

const monthNames = computed(() => [
    t('shifts.january'), t('shifts.february'), t('shifts.march'), t('shifts.april'),
    t('shifts.may'), t('shifts.june'), t('shifts.july'), t('shifts.august'),
    t('shifts.september'), t('shifts.october'), t('shifts.november'), t('shifts.december'),
])

const monthLabel = computed(() => `${monthNames.value[selectedMonth.value - 1]} ${selectedYear.value}`)

function prevMonth() {
    if (selectedMonth.value === 1) {
        selectedMonth.value = 12
        selectedYear.value--
    } else {
        selectedMonth.value--
    }
    loadData()
}

function nextMonth() {
    if (selectedMonth.value === 12) {
        selectedMonth.value = 1
        selectedYear.value++
    } else {
        selectedMonth.value++
    }
    loadData()
}

function loadData() {
    router.get(
        route('team-calendar'),
        { month: selectedMonth.value, year: selectedYear.value },
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

function statusColor(status) {
    const map = {
        work: 'bg-mistral-success text-white',
        rest: 'bg-mistral-surface text-mistral-steel',
        absent: 'bg-mistral-danger text-white',
        holiday: 'bg-mistral-warning text-white',
        present: 'bg-mistral-success text-white',
        on_leave: 'bg-mistral-info text-white',
    }
    return map[status] || 'bg-mistral-surface text-mistral-steel'
}

function dayLabel(status) {
    const map = {
        work: t('shifts.day_work_short'),
        rest: t('shifts.day_rest_short'),
        absent: t('shifts.day_absent_short'),
        holiday: t('shifts.day_holiday_short'),
        present: t('shifts.day_present_short'),
        on_leave: t('shifts.day_on_leave_short'),
    }
    return map[status] || ''
}

const dayNames = computed(() => [
    t('shifts.saturday'), t('shifts.sunday'), t('shifts.monday'),
    t('shifts.tuesday'), t('shifts.wednesday'), t('shifts.thursday'), t('shifts.friday'),
])
</script>

<template>
    <AppLayout :title="t('shifts.team_calendar')">
        <PageHeader :title="t('shifts.team_calendar')" />

        <Card variant="base" padding="none" class="mb-6">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-3 flex-wrap">
                    <Button variant="secondary" size="sm" icon="fas fa-chevron-right" @click="prevMonth" />
                    <span class="text-sm font-semibold min-w-[140px] text-center text-mistral-ink">{{ monthLabel }}</span>
                    <Button variant="secondary" size="sm" icon-left="fas fa-chevron-left" @click="nextMonth" />
                </div>
            </div>
        </Card>

        <CalendarLegend class="mb-4" />

        <Card variant="base" padding="none">
            <div class="overflow-x-auto">
                <table class="w-full text-end border-collapse" dir="rtl">
                    <thead>
                        <tr class="bg-mistral-surface/60 border-b border-mistral-hairline-soft">
                            <th class="px-4 py-3 text-[11px] font-semibold text-mistral-slate uppercase tracking-wider min-w-[160px]">
                                {{ t('shifts.employee_name') }}
                            </th>
                            <th
                                v-for="(d, i) in (dates.length ? dates : date ? [date] : [])"
                                :key="d"
                                class="px-3 py-3 text-[11px] font-semibold text-mistral-slate uppercase tracking-wider text-center min-w-[80px]"
                            >
                                <div>{{ dayNames[i] || dayNames[new Date(d).getDay()] }}</div>
                                <div class="text-[10px] normal-case tracking-normal text-mistral-muted font-normal">{{ d.slice(5) }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(emp, empIndex) in team"
                            :key="emp.id"
                            :class="[
                                'border-b border-mistral-hairline-soft/60 last:border-0 transition-colors',
                                empIndex % 2 === 1 ? 'bg-mistral-surface/30' : 'bg-white',
                                'hover:bg-mistral-cream-light/40',
                            ]"
                        >
                            <td class="px-4 py-2">
                                <div class="text-[14px] font-medium text-mistral-ink">{{ emp.name }}</div>
                                <div class="text-[11px] text-mistral-muted">{{ emp.emp_code || '' }}</div>
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
                        <tr v-if="!team.length">
                            <td :colspan="(dates.length || 1) + 1" class="py-12 text-center text-mistral-muted">
                                {{ t('shifts.no_data') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
