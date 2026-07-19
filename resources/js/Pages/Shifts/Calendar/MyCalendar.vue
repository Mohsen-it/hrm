<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { PageHeader, Card, Button } from '@/Components/ui'
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    calendar: { type: Array, default: () => [] },
    month: { type: Number, default: () => new Date().getMonth() + 1 },
    year: { type: Number, default: () => new Date().getFullYear() },
})

const currentMonth = ref(props.month)
const currentYear = ref(props.year)

const monthNames = computed(() => [
    t('shifts.january'), t('shifts.february'), t('shifts.march'), t('shifts.april'),
    t('shifts.may'), t('shifts.june'), t('shifts.july'), t('shifts.august'),
    t('shifts.september'), t('shifts.october'), t('shifts.november'), t('shifts.december'),
])

const monthLabel = computed(() => `${monthNames.value[currentMonth.value - 1]} ${currentYear.value}`)

function prevMonth() {
    if (currentMonth.value === 1) {
        currentMonth.value = 12
        currentYear.value--
    } else {
        currentMonth.value--
    }
    navigate()
}

function nextMonth() {
    if (currentMonth.value === 12) {
        currentMonth.value = 1
        currentYear.value++
    } else {
        currentMonth.value++
    }
    navigate()
}

function navigate() {
    router.get(
        route('my-calendar'),
        { month: currentMonth.value, year: currentYear.value },
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
    <AppLayout :title="t('shifts.my_schedule')">
        <PageHeader :title="t('shifts.my_schedule')" />

        <Card variant="base" padding="none" class="mb-6">
            <div class="p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <Button variant="secondary" size="sm" icon="fas fa-chevron-right" @click="prevMonth">
                        {{ t('shifts.prev_month') }}
                    </Button>
                    <h3 class="text-lg font-bold text-mistral-ink">{{ monthLabel }}</h3>
                    <Button variant="secondary" size="sm" icon-right="fas fa-chevron-left" @click="nextMonth">
                        {{ t('shifts.next_month') }}
                    </Button>
                </div>

                <CalendarLegend class="mb-4" />

                <div class="grid grid-cols-7 gap-1 text-center">
                    <div
                        v-for="name in dayNames"
                        :key="name"
                        class="text-xs font-bold text-mistral-slate py-2"
                    >
                        {{ name }}
                    </div>

                    <template v-for="(cell, i) in calendar" :key="cell.date">
                        <div
                            v-if="i === 0 && cell.day_of_week !== undefined"
                            :style="{ gridColumnStart: cell.day_of_week + 2 }"
                        ></div>
                        <div
                            :class="[statusColor(cell.status), 'rounded-lg p-2 min-h-[55px] flex flex-col items-center justify-center cursor-pointer hover:opacity-80 transition']"
                            :title="cell.date + ' - ' + cell.day_name"
                        >
                            <span class="text-xs">{{ new Date(cell.date).getDate() }}</span>
                            <span class="text-[10px] mt-0.5" v-if="cell.status !== 'rest'">{{ dayLabel(cell.status) }}</span>
                        </div>
                    </template>
                </div>
            </div>
        </Card>
    </AppLayout>
</template>
